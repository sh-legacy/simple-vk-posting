<?php


namespace Commands;


use GuzzleHttp\Client;
use Services\ConfigProvider;
use Services\GroupsProvider;
use Services\ImageToText;
use Services\ListProvider;
use Services\Log;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class RunCommand extends Command
{
    protected function configure()
    {
        $this->setName('run')
            ->setDescription('Launch posting')
            ->setHelp('This command launches posting.')
            ->addOption('no-captcha', null, InputOption::VALUE_OPTIONAL, 'Send posts without captcha only, and skip groups that require entering captcha code', false);
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $output->writeln('<fg=green>Постинг запущен.</>');

        $config = ConfigProvider::getConfig();
        $goals = json_decode(file_get_contents('goals.json'), true);

        $anticaptcha = new ImageToText();
        $anticaptcha->setKey($config['anticaptcha_key']);

        $client = new Client([
            'base_uri' => 'https://api.vk.com/method/',
        ]);

        while (true) {
            $allGoalsCompleted = true;
            $allLists = ListProvider::getListTexts();

            foreach ($allLists as $namespace => $lists) {
                $groups = GroupsProvider::getGroups($namespace);

                if ($groups == false) {
                    $output->writeln('<error>Не удалось получить список групп: ' . GroupsProvider::getErrorMessage() . '</>');
                    continue;
                }

                foreach ($lists as $listName => $list) {
                    if (isset($goals[$namespace][$listName]) && $goals[$namespace][$listName]['done'] >= $goals[$namespace][$listName]['goal']) {
                        continue;
                    }

                    foreach ($groups as $group) {
                        sleep(1);
                        $output->writeln('<fg=blue>' . date('[H:i:s]') . "</> - Попытка размещения поста списка $namespace.$listName в группе $group...", OutputInterface::VERBOSITY_VERBOSE);

                        $withCaptcha = false;

                        $response = $client->request('POST', 'wall.post', [
                            'form_params' => [
                                'access_token' => $config['vk_access_token'],
                                'v' => '5.95',
                                'owner_id' => $group,
                                'message' => $list,
                            ],
                        ]);
                        $response = json_decode($response->getBody()->getContents(), true);

                        if ((@$response['error']['error_code'] == 14) && ($input->getOption('no-captcha') === null)) {
                            $output->writeln("    Необходимо ввести капчу, но выбран режим без капчи. Пост пропускается.\n    Результат: [<fg=yellow>SKIP</>]", OutputInterface::VERBOSITY_VERBOSE);
                            $allGoalsCompleted = false;
                            continue;
                        }

                        if (@$response['error']['error_code'] == 14) {
                            $output->writeln('    Необходимо решить капчу. Обращение к API anti-captcha.com...', OutputInterface::VERBOSITY_VERBOSE);

                            $withCaptcha = true;

                            $captchaSessionId = $response['error']['captcha_sid'];
                            $anticaptcha->setFile($response['error']['captcha_img']);

                            if (!$anticaptcha->createTask()) {
                                $output->writeln('    Произошла ошибка: ' . $anticaptcha->getErrorMessage() . "\n    Результат: [<fg=red>ERROR</>]");
                                continue;
                            }
                            $taskId = $anticaptcha->getTaskId();

                            if (!$anticaptcha->waitForResult()) {
                                $output->writeln('    Не удалось получить ответ: ' . $anticaptcha->getErrorMessage() . "\n    Результат: [<fg=red>ERROR</>]");
                                continue;
                            }
                            $captchaSolution = $anticaptcha->getTaskSolution();

                            $output->writeln("    Получено решение капчи: <fg=magenta>$captchaSolution</>\n    Повторный запрос к vk API...", OutputInterface::VERBOSITY_VERBOSE);

                            $response = $client->request('POST', 'wall.post', [
                                'form_params' => [
                                    'access_token' => $config['vk_access_token'],
                                    'v' => '5.95',
                                    'owner_id' => $group,
                                    'message' => $list,
                                    'captcha_sid' => $captchaSessionId,
                                    'captcha_key' => $captchaSolution,
                                ],
                            ]);
                            $response = json_decode($response->getBody()->getContents(), true);
                        } elseif (@$response['error']['error_code'] == 15) { // Закрыта стена
                            $output->writeln("    В этой группе закрыта стена.\n    Результат: [<fg=yellow>SKIP</>]", OutputInterface::VERBOSITY_VERBOSE);
                            $allGoalsCompleted = false;
                            continue;
                        }

                        if (@$response['error']['error_code'] == 14) {
                            $output->writeln("    Ошибка 'Captcha needed'. Возможно, капча была решена неправильно, taskId = $taskId.\n    Результат: [<fg=red>ERROR</>]", OutputInterface::VERBOSITY_VERBOSE);
                            Log::write("Не удалось разместить пост списка $namespace.$listName в группе $group. Возможно, капча была решена неправильно, taskId = " . $taskId);
                            continue;
                        }

                        if (isset($response['error'])) {
                            $output->writeln('    VK API вернул ошибку: ' . $response['error']['error_msg'] . "\n    Результат: [<fg=red>ERROR</>]", OutputInterface::VERBOSITY_VERBOSE);
                            continue;
                        }

                        if (isset($goals[$namespace][$listName])) {
                            $goals[$namespace][$listName]['done']++;
                            file_put_contents('goals.json', json_encode($goals));
                        }

                        $output->writeln("    Пост размещен успешно, ссылка на пост: vk.com/wall{$group}_{$response['response']['post_id']}\n    Результат: [<fg=green>OK</>]", OutputInterface::VERBOSITY_VERBOSE);
                        Log::write((($withCaptcha) ? 'Отправлен пост с капчей' : 'Отправлен пост без капчи') . ": vk.com/wall{$group}_{$response['response']['post_id']}");

                        if (isset($goals[$namespace][$listName]) && $goals[$namespace][$listName]['done'] >= $goals[$namespace][$listName]['goal']) {
                            $output->writeln("<fg=green>Достигнута цель по постам ({$goals[$namespace][$listName]['goal']}) для списка $namespace.$listName!</>", OutputInterface::VERBOSITY_VERBOSE);
                            Log::write("Достигнута цель по постам ({$goals[$namespace][$listName]['goal']}) для списка $namespace.$listName!");
                            continue 2;
                        }
                        $allGoalsCompleted = false;

                        sleep(rand($config['min_interval'], $config['max_interval']));
                    }
                }
            }
            if ($allGoalsCompleted) {
                $output->writeln("<fg=green>Все цели достигнуты! Постинг завершает работу.</>", OutputInterface::VERBOSITY_VERBOSE);
                Log::write("Все цели достигнуты! Постинг завершает работу.");
                break;
            }
        }
    }
}
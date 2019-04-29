<?php


namespace Commands;


use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ShowGoalCommand extends Command
{
    protected function configure()
    {
        $this->setName('goals:show')
            ->setDescription('Show existing goals')
            ->setHelp('This command shows existing goals.')
            ->addArgument('namespace', InputArgument::OPTIONAL, 'List`s namespace. If set to "*", all lists will be displayed', '*')
            ->addArgument('list', InputArgument::OPTIONAL, 'List name. If set to "*", all lists in the specified namespace will be shown.', '*');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $goals = json_decode(file_get_contents('goals.json'), true);

        $namespace = $input->getArgument('namespace');
        $list = $input->getArgument('list');

        if ($namespace == '*' && $list == '*') {
            if (empty($goals)) {
                $output->writeln('<error>Нет активных целей!</>');
                return;
            }

            $output->writeln("<fg=green>Список активных целей:</>");
            foreach ($goals as $namespaceName => $listGoals) {
                $output->writeln("<fg=green>Цели для namespace '{$namespaceName}':</>");
                foreach ($listGoals as $listName => $listGoal) {
                    $output->writeln("  {$listName} - {$listGoal['goal']} (выполнено {$listGoal['done']}).");
                }
            }
        } elseif ($namespace != '*' && $list == '*') {
            if (!isset($goals[$namespace])) {
                $output->writeln('<error>Цели для этого namespace не заданы!</>');
                return;
            }

            $output->writeln("<fg=green>Цели для namespace '{$namespace}':</>");
            foreach ($goals[$namespace] as $listName => $listGoal) {
                $output->writeln("{$listName} - {$listGoal['goal']} (выполнено {$listGoal['done']}).");
            }
        } elseif ($namespace == '*' && $list != '*') {
            $output->writeln('<error>Если задано имя списка, то пространство имен тоже должно быть передано!</>');
            return;
        } else {
            if (!isset($goals[$namespace][$list])) {
                $output->writeln('<error>Цель для этого namespace и списка не задана!</>');
                return;
            }

            $output->writeln("<fg=green>Цель для списка {$namespace}.{$list} - {$goals[$namespace][$list]['goal']} (выполнено {$goals[$namespace][$list]['done']}).</>");
        }
    }
}
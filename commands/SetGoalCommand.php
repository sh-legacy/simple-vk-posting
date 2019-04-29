<?php


namespace Commands;


use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class SetGoalCommand extends Command
{
    protected function configure()
    {
        $this->setName('goals:set')
             ->setDescription('Create or edit goals.')
             ->setHelp('This command can be used to create new or edit existing goals.')
             ->addArgument('namespace', InputArgument::REQUIRED, 'List`s namespace')
             ->addArgument('list', InputArgument::REQUIRED, 'List name')
             ->addArgument('posts_count', InputArgument::REQUIRED, 'The number of posts at which the goal is considered to be completed.')
             ->addArgument('done', InputArgument::OPTIONAL, 'The number of posts that are already done. Default: 0, or previous value.');

    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $goals = json_decode(file_get_contents('goals.json'), true);

        if (!is_numeric($input->getArgument('posts_count'))) {
            $output->writeln('<error>Параметр posts_count должен быть числом!</>');
            return;
        }
        $postsDoneCount = $input->getArgument('done');
        if (!isset($postsDoneCount)) {
            $postsDoneCount = $goals[$input->getArgument('namespace')][$input->getArgument('list')]['done'] ?? 0;
        }
        if (!is_numeric($postsDoneCount)) {
            $output->writeln('<error>Параметр done должен быть числом!</>');
            return;
        }

        $goals[$input->getArgument('namespace')][$input->getArgument('list')]['goal'] = $input->getArgument('posts_count');
        $goals[$input->getArgument('namespace')][$input->getArgument('list')]['done'] = $postsDoneCount;

        file_put_contents('goals.json', json_encode($goals));

        $output->writeln('<fg=green>Цель успешно задана!</>');
    }
}
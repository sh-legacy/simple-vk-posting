<?php


namespace Commands;


use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class UnsetGoalCommand extends Command
{
    protected function configure()
    {
        $this->setName('goals:unset')
            ->setDescription('Unset existing goal')
            ->setHelp('This command can be used to delete existing goals.')
            ->addArgument('namespace', InputArgument::REQUIRED, 'List`s namespace')
            ->addArgument('list', InputArgument::REQUIRED, 'Name of list, which should be deleted');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $goals = json_decode(file_get_contents('goals.json'), true);

        if (!isset($goals[$input->getArgument('namespace')][$input->getArgument('list')])) {
            $output->writeln('<error>Цели для списка с таким namespace и именем не заданы!</>');
            return;
        }

        unset($goals[$input->getArgument('namespace')][$input->getArgument('list')]);

        if (empty($goals[$input->getArgument('namespace')])) {
            unset($goals[$input->getArgument('namespace')]);
        }

        file_put_contents('goals.json', json_encode($goals));

        $output->writeln('<fg=green>Цель успешно удалена!</>');
    }
}
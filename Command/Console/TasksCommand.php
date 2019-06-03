<?php

declare(strict_types=1);

namespace RevisionTen\CMS\Command\Console;

use RevisionTen\CMS\Services\TaskService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputInterface;

/**
 * Class TasksCommand.
 */
class TasksCommand extends Command
{
    /** @var TaskService */
    private $taskService;

    /**
     * IndexCommand constructor.
     *
     * @param TaskService $taskService
     */
    public function __construct(TaskService $taskService)
    {
        $this->taskService = $taskService;

        parent::__construct();
    }

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setName('cms:tasks:run')
            ->setDescription('Run due tasks.')
        ;
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->taskService->runTasks($output);
    }
}

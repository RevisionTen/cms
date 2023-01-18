<?php

declare(strict_types=1);

namespace RevisionTen\CMS\Command\Console;

use Exception;
use RevisionTen\CMS\Services\TaskService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputInterface;

class TasksCommand extends Command
{
    private TaskService $taskService;

    public function __construct(TaskService $taskService)
    {
        $this->taskService = $taskService;

        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setName('cms:tasks:run')
            ->setDescription('Run due tasks.')
        ;
    }

    /**
     * @throws Exception
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->taskService->runTasks($output);

        return 0;
    }
}

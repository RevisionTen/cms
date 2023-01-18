<?php

declare(strict_types=1);

namespace RevisionTen\CMS\Command\Console;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputInterface;
use RevisionTen\CMS\Services\IndexService;

class IndexCommand extends Command
{
    private IndexService $indexService;

    public function __construct(IndexService $indexService)
    {
        $this->indexService = $indexService;

        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setName('cms:solr:index')
            ->setDescription('Index pages.')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->indexService->index($output);

        return 0;
    }
}

<?php

declare(strict_types=1);

namespace RevisionTen\CMS\Command\Console;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputInterface;
use RevisionTen\CMS\Services\IndexService;

class SolrClearCommand extends Command
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
            ->setName('cms:solr:clear')
            ->setDescription('Clear solr index.')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->indexService->clear($output);

        return 0;
    }
}

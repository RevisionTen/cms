<?php

declare(strict_types=1);

namespace RevisionTen\CMS\Command\Console;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputInterface;
use RevisionTen\CMS\Services\IndexService;

/**
 * Class IndexCommand.
 */
class IndexCommand extends Command
{
    /** @var IndexService */
    private $indexService;

    /**
     * IndexCommand constructor.
     *
     * @param IndexService $indexService
     */
    public function __construct(IndexService $indexService)
    {
        $this->indexService = $indexService;

        parent::__construct();
    }

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setName('cms:solr:index')
            ->setDescription('Index pages.')
        ;
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->indexService->index($output);
    }
}

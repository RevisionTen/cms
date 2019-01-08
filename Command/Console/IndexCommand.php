<?php

declare(strict_types=1);

namespace RevisionTen\CMS\Command\Console;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputInterface;
use RevisionTen\CMS\Services\IndexService;

/**
 * Class IndexCommand.
 */
class IndexCommand extends ContainerAwareCommand
{
    /** @var IndexService $indexService */
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

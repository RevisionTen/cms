<?php

declare(strict_types=1);

namespace RevisionTen\CMS\Command\Console;

use Doctrine\ORM\EntityManagerInterface;
use RevisionTen\CMS\Model\Alias;
use RevisionTen\CMS\Model\Website;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputInterface;

/**
 * Class AliasUpdateCommand.
 *
 * Use this command to update your aliases based on the state of the pages.
 */
class AliasUpdateCommand extends Command
{
    /** @var EntityManagerInterface $entityManager */
    private $entityManager;

    /**
     * AliasUpdateCommand constructor.
     *
     * @param EntityManagerInterface $entityManager
     */
    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;

        parent::__construct();
    }

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setName('cms:alias:update')
            ->setDescription('Update your aliases based on the state of the pages.')
        ;
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        /** @var Alias[] $aliases */
        $aliases = $this->entityManager->getRepository(Alias::class)->findAll();

        foreach ($aliases as $alias) {
            $pageStreamRead = $alias->getPageStreamRead();

            if (null !== $pageStreamRead) {
                // Update status of the alias.
                $alias->setEnabled($pageStreamRead->isPublished());
                // Update language and website of the alias.
                $alias->setLanguage($pageStreamRead->getLanguage());
                $alias->setWebsite($this->entityManager->getReference(Website::class, $pageStreamRead->getWebsite()));
            }

            $this->entityManager->persist($alias);
        }

        $this->entityManager->flush();
        $output->writeln('Aliases updated.');
    }
}

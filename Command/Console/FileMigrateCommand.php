<?php

declare(strict_types=1);

namespace RevisionTen\CMS\Command\Console;

use RevisionTen\CMS\Command\FileUpdateCommand;
use RevisionTen\CMS\Model\File;
use RevisionTen\CMS\Model\FileRead;
use Doctrine\ORM\EntityManagerInterface;
use RevisionTen\CMS\Model\Website;
use RevisionTen\CQRS\Services\AggregateFactory;
use RevisionTen\CQRS\Services\CommandBus;
use RevisionTen\CQRS\Services\MessageBus;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;

/**
 * Class FileMigrateCommand.
 *
 * Use this command to create read models for existing file aggregates.
 */
class FileMigrateCommand extends Command
{
    /** @var EntityManagerInterface $entityManager */
    private $entityManager;

    /** @var AggregateFactory $aggregateFactory */
    private $aggregateFactory;

    /** @var CommandBus $commandBus */
    private $commandBus;

    /** @var MessageBus $messageBus */
    private $messageBus;

    /** @var array $config */
    private $config;

    /**
     * FileMigrateCommand constructor.
     *
     * @param EntityManagerInterface $entityManager
     * @param AggregateFactory       $aggregateFactory
     * @param CommandBus             $commandBus
     * @param MessageBus             $messageBus
     * @param array                  $config
     */
    public function __construct(EntityManagerInterface $entityManager, AggregateFactory $aggregateFactory, CommandBus $commandBus, MessageBus $messageBus, array $config)
    {
        $this->entityManager = $entityManager;
        $this->aggregateFactory = $aggregateFactory;
        $this->commandBus = $commandBus;
        $this->messageBus = $messageBus;
        $this->config = $config;

        parent::__construct();
    }

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setName('cms:file:migrate')
            ->setDescription('Create read models for existing file aggregates.')
        ;
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $helper = $this->getHelper('question');

        /**
         * Get a choice list of all websites.
         *
         * @var Website[] $websiteEntities
         */
        $websiteEntities = $this->entityManager->getRepository(Website::class)->findAll();
        $websites = [];
        foreach ($websiteEntities as $websiteEntity) {
            $websites[$websiteEntity->getTitle()] = $websiteEntity->getId();
        }

        // Aks what website the file aggregates belong to.
        $websiteQuestion = new ChoiceQuestion('What website do your files belong to? ', array_keys($websites));
        $websiteQuestion->setErrorMessage('Answer %s is invalid.');
        $websiteQuestion->setAutocompleterValues(array_keys($websites));
        $websiteQuestion->setValidator(function ($answer) use ($websites) {
            if (!isset($websites[$answer])) {
                throw new \RuntimeException('This website does not exist.');
            }

            return $answer;
        });
        $websiteQuestion->setMaxAttempts(5);
        $websiteAnswer = $helper->ask($input, $output, $websiteQuestion);
        $website = $websites[$websiteAnswer];

        // Aks what language the file aggregates belong to.
        $languages = $this->config['page_languages'];
        $languageQuestion = new ChoiceQuestion('What language do your files belong to? ', array_keys($languages));
        $languageQuestion->setErrorMessage('Answer %s is invalid.');
        $languageQuestion->setAutocompleterValues(array_keys($languages));
        $languageQuestion->setValidator(function ($answer) use ($languages) {
            if (!isset($languages[$answer])) {
                throw new \RuntimeException('This language does not exist.');
            }

            return $answer;
        });
        $languageQuestion->setMaxAttempts(5);
        $languageAnswer = $helper->ask($input, $output, $languageQuestion);
        $language = $languages[$languageAnswer];


        /**
         * Find all file aggregates.
         *
         * @var File[] $files
         */
        $files = $this->aggregateFactory->findAggregates(File::class);

        // Filter out files that already have a complete read model.
        $files = array_filter($files, function ($file) {
            return null === $file->language && null === $file->website && null === $this->entityManager->getRepository(FileRead::class)->findOneByUuid($file->getUuid());
        });

        foreach ($files as $file) {
            // Update the aggregate.
            $success = false;
            $successCallback = function ($commandBus, $event) use (&$success) { $success = true; };
            $this->commandBus->dispatch(new FileUpdateCommand(-1, null, $file->getUuid(), $file->getVersion(), [
                'website' => (int) $website,
                'language' => (string) $language,
            ], $successCallback), false);

            if ($success) {
                // Return info about the user.
                $output->writeln('File migrated: "'.$file->title.'"');
            } else {
                $messages = $this->messageBus->getMessagesJson();
                $output->writeln('Migrating failed: "'.$file->title.'"');
                print_r($messages);
            }
        }
    }
}

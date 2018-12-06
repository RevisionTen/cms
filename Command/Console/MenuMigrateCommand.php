<?php

declare(strict_types=1);

namespace RevisionTen\CMS\Command\Console;

use RevisionTen\CMS\Command\MenuEditCommand;
use RevisionTen\CMS\Model\Menu;
use RevisionTen\CMS\Model\MenuRead;
use Doctrine\ORM\EntityManagerInterface;
use RevisionTen\CMS\Model\Site;
use RevisionTen\CQRS\Services\AggregateFactory;
use RevisionTen\CQRS\Services\CommandBus;
use RevisionTen\CQRS\Services\MessageBus;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;

/**
 * Class MenuMigrateCommand.
 *
 * Use this command to create read models for existing menu aggregates.
 */
class MenuMigrateCommand extends Command
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
     * UserCreateCommand constructor.
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
            ->setName('cms:menu:migrate')
            ->setDescription('Create read models for existing menu aggregates.')
        ;
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $helper = $this->getHelper('question');

        /**
         * Find all menu aggregates.
         *
         * @var Menu[] $menus
         */
        $menus = $this->aggregateFactory->findAggregates(Menu::class);

        // Check If each menu aggregate has a read model.
        foreach ($menus as $menu) {
            $hasReadModel = $this->entityManager->getRepository(MenuRead::class)->findOneByUuid($menu->getUuid());

            if ($hasReadModel && null !== $menu->language && null !== $menu->website) {
                continue;
            }

            $output->writeln('Found menu "'.$menu->name.'"');

            /**
             * Get a choice list of all websites.
             *
             * @var Site[] $websiteEntities
             */
            $websiteEntities = $this->entityManager->getRepository(Site::class)->findAll();
            $websites = [];
            foreach ($websiteEntities as $websiteEntity) {
                $websites[$websiteEntity->getTitle()] = $websiteEntity->getId();
            }

            // Aks what website the menu aggregate belongs to.
            $websiteQuestion = new ChoiceQuestion('What website does this menu belong to? ', array_keys($websites));
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

            // Aks what language the menu aggregate belongs to.
            $languages = $this->config['page_languages'];
            $languageQuestion = new ChoiceQuestion('What language does this menu belong to? ', array_keys($languages));
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

            // Update the aggregate.
            $success = false;
            $successCallback = function ($commandBus, $event) use (&$success) { $success = true; };
            $this->commandBus->dispatch(new MenuEditCommand(-1, null, $menu->getUuid(), $menu->getVersion(), [
                'website' => (int) $website,
                'language' => (string) $language,
            ], $successCallback), false);

            if ($success) {
                // Return info about the user.
                $output->writeln('Menu "'.$menu->name.'" migrated.');
            } else {
                $messages = $this->messageBus->getMessagesJson();
                $output->writeln('Migrating "'.$menu->name.'" failed.');
                print_r($messages);
            }
        }
    }
}

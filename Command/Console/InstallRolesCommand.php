<?php

declare(strict_types=1);

namespace RevisionTen\CMS\Command\Console;

use Ramsey\Uuid\Uuid;
use RevisionTen\CMS\Command\RoleCreateCommand;
use RevisionTen\CMS\Command\UserEditCommand;
use RevisionTen\CMS\Model\Domain;
use Doctrine\ORM\EntityManagerInterface;
use RevisionTen\CMS\Model\RoleRead;
use RevisionTen\CMS\Model\UserAggregate;
use RevisionTen\CMS\Model\UserRead;
use RevisionTen\CMS\Model\Website;
use RevisionTen\CQRS\Services\AggregateFactory;
use RevisionTen\CQRS\Services\CommandBus;
use RevisionTen\CQRS\Services\MessageBus;
use RuntimeException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;
use function in_array;

class InstallRolesCommand extends Command
{
    private EntityManagerInterface $entityManager;

    private CommandBus $commandBus;

    private MessageBus $messageBus;

    private AggregateFactory $aggregateFactory;

    private string $locale;

    public function __construct(EntityManagerInterface $entityManager, CommandBus $commandBus, MessageBus $messageBus, AggregateFactory $aggregateFactory, string $locale)
    {
        $this->entityManager = $entityManager;
        $this->commandBus = $commandBus;
        $this->messageBus = $messageBus;
        $this->aggregateFactory = $aggregateFactory;
        $this->locale = $locale;

        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setName('cms:install:roles')
            ->setDescription('Install the default roles.')
        ;
    }

    private function runCommand(string $commandClass, string $aggregateUuid, int $onVersion, array $payload): bool
    {
        $command = new $commandClass(-1, null, $aggregateUuid, $onVersion, $payload);

        return $this->commandBus->dispatch($command);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        // Install default website.
        $websites = $this->entityManager->getRepository(Website::class)->findAll();
        if (empty($websites)) {
            $defaultWebsite = new Website();
            $defaultWebsite->setTitle('Localhost');
            $defaultWebsite->setDefaultLanguage(trim($this->locale, "'"));
            $defaultDomain = new Domain();
            $defaultDomain->setDomain('localhost');
            $defaultWebsite->setDomains([$defaultDomain]);

            $this->entityManager->persist($defaultWebsite);
            $this->entityManager->flush();
            $this->entityManager->clear();
        }

        $helper = $this->getHelper('question');

        // Check if there is an admin role.
        $adminRole = $this->entityManager->getRepository(RoleRead::class)->findOneByTitle('Administrator');
        if (null === $adminRole) {
            // Install the admin role.
            $adminRoleUuid = Uuid::uuid1()->toString();
            $adminRoleInstalled = $this->runCommand(RoleCreateCommand::class, $adminRoleUuid, 0, [
                'title' => 'Administrator',
                'permissions' => ['do_everything'],
            ]);
            if ($adminRoleInstalled) {
                $output->writeln('Admin role installed.');
            } else {
                $messages = $this->messageBus->getMessagesJson();
                $output->writeln('Admin role installation failed.');
                print_r($messages);
                return 500;
            }
        }

        // Check if there is an editor role.
        $editorRole = $this->entityManager->getRepository(RoleRead::class)->findOneByTitle('Editor');
        if (null === $editorRole) {
            // Install the editor role.
            $editorRoleUuid = Uuid::uuid1()->toString();
            $editorRoleInstalled = $this->runCommand(RoleCreateCommand::class, $editorRoleUuid, 0, [
                'title' => 'Editor',
                'permissions' => [
                    'page_list',
                    'page_search',
                    'page_create',
                    'page_edit',
                    'page_delete',
                    'page_clone',
                    'page_publish',
                    'page_unpublish',
                    'page_submit_changes',
                    'alias_list',
                    'alias_search',
                    'alias_create',
                    'alias_edit',
                    'alias_delete',
                    'menu_list',
                    'menu_search',
                    'menu_edit',
                    'file_list',
                    'file_search',
                    'file_create',
                    'file_edit',
                ],
            ]);
            if ($editorRoleInstalled) {
                $output->writeln('Editor role installed.');
            } else {
                $messages = $this->messageBus->getMessagesJson();
                $output->writeln('Editor role installation failed.');
                print_r($messages);
                return 500;
            }
        }

        /**
         * Get a choice list of all users.
         *
         * @var UserRead[] $users
         */
        $users = $this->entityManager->getRepository(UserRead::class)->findAll();
        $userChoice = [];
        foreach ($users as $user) {
            if (null !== $user->getUuid()) {
                $userChoice[$user->getUsername()] = $user->getUuid();
            }
        }

        if (!empty($userChoice)) {
            $userQuestion = new ChoiceQuestion('Choose an admin user', array_keys($userChoice));
            $userQuestion->setErrorMessage('Answer %s is invalid.');
            $userQuestion->setAutocompleterValues(array_keys($userChoice));
            $userQuestion->setValidator(static function ($answer) use ($userChoice) {
                if (!isset($userChoice[$answer])) {
                    throw new RuntimeException('This user does not exist.');
                }

                return $answer;
            });
            $userQuestion->setMaxAttempts(5);
            $userAnswer = $helper->ask($input, $output, $userQuestion);
            $userUuid = $userChoice[$userAnswer];

            // Get admin role and assign it to the user.
            $this->entityManager->clear();
            /**
             * @var RoleRead $adminRole
             */
            $adminRole = $this->entityManager->getRepository(RoleRead::class)->findOneByTitle('Administrator');
            $adminRoleUuid = $adminRole->getUuid();
            /**
             * @var UserAggregate $user
             */
            $user = $this->aggregateFactory->build($userUuid, UserAggregate::class);

            if (!in_array($adminRoleUuid, $user->roles, true)) {
                $user->roles[] = $adminRoleUuid;
            }

            $assignedAdmin = $this->runCommand(UserEditCommand::class, $user->getUuid(), $user->getVersion(), [
                'roles' => $user->roles,
            ]);
            if ($assignedAdmin) {
                $output->writeln('Admin role assigned.');
            } else {
                $messages = $this->messageBus->getMessagesJson();
                $output->writeln('Assigning admin role failed.');
                print_r($messages);
                return 500;
            }
        }

        return 0;
    }
}

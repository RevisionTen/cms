<?php

declare(strict_types=1);

namespace RevisionTen\CMS\Command\Console;

use Ramsey\Uuid\Uuid;
use RevisionTen\CMS\Command\FileUpdateCommand;
use RevisionTen\CMS\Command\RoleCreateCommand;
use RevisionTen\CMS\Command\UserEditCommand;
use RevisionTen\CMS\Model\File;
use RevisionTen\CMS\Model\FileRead;
use Doctrine\ORM\EntityManagerInterface;
use RevisionTen\CMS\Model\RoleRead;
use RevisionTen\CMS\Model\UserAggregate;
use RevisionTen\CMS\Model\UserRead;
use RevisionTen\CMS\Model\Website;
use RevisionTen\CQRS\Services\AggregateFactory;
use RevisionTen\CQRS\Services\CommandBus;
use RevisionTen\CQRS\Services\MessageBus;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;

/**
 * Class InstallRolesCommand.
 */
class InstallRolesCommand extends Command
{
    /** @var EntityManagerInterface $entityManager */
    private $entityManager;

    /** @var CommandBus $commandBus */
    private $commandBus;

    /** @var MessageBus $messageBus */
    private $messageBus;

    /** @var AggregateFactory $aggregateFactory */
    private $aggregateFactory;

    /**
     * InstallRolesCommand constructor.
     *
     * @param EntityManagerInterface $entityManager
     * @param CommandBus             $commandBus
     * @param MessageBus             $messageBus
     */
    public function __construct(EntityManagerInterface $entityManager, CommandBus $commandBus, MessageBus $messageBus, AggregateFactory $aggregateFactory)
    {
        $this->entityManager = $entityManager;
        $this->commandBus = $commandBus;
        $this->messageBus = $messageBus;
        $this->aggregateFactory = $aggregateFactory;

        parent::__construct();
    }

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setName('cms:install:roles')
            ->setDescription('Install the default roles.')
        ;
    }

    private function runCommand(string $commandClass, string $aggregateUuid, int $onVersion, array $payload): bool
    {
        $success = false;
        $successCallback = function ($commandBus, $event) use (&$success) { $success = true; };

        $command = new $commandClass(-1, null, $aggregateUuid, $onVersion, $payload, $successCallback);

        $this->commandBus->dispatch($command, false);

        return $success;
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
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
                    'page_create',
                    'page_edit',
                    'page_delete',
                    'page_clone',
                    'alias_list',
                    'alias_create',
                    'alias_edit',
                    'alias_delete',
                    'menu_list',
                    'menu_edit',
                    'file_list',
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

        $userQuestion = new ChoiceQuestion('Choose an admin user', array_keys($userChoice));
        $userQuestion->setErrorMessage('Answer %s is invalid.');
        $userQuestion->setAutocompleterValues(array_keys($userChoice));
        $userQuestion->setValidator(function ($answer) use ($userChoice) {
            if (!isset($userChoice[$answer])) {
                throw new \RuntimeException('This user does not exist.');
            }

            return $answer;
        });
        $userQuestion->setMaxAttempts(5);
        $userAnswer = $helper->ask($input, $output, $userQuestion);
        $userUuid = $userChoice[$userAnswer];

        // Get admin role and assign it to the user.
        $this->entityManager->clear();
        /** @var RoleRead $adminRole */
        $adminRole = $this->entityManager->getRepository(RoleRead::class)->findOneByTitle('Administrator');
        $adminRoleUuid = $adminRole->getUuid();
        /** @var UserAggregate $user */
        $user = $this->aggregateFactory->build($userUuid, UserAggregate::class);

        if (!\in_array($adminRoleUuid, $user->roles, true)) {
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
        }
    }
}

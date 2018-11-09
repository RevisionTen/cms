<?php

declare(strict_types=1);

namespace RevisionTen\CMS\Command\Console;

use Ramsey\Uuid\Uuid;
use RevisionTen\CMS\Model\UserRead;
use Doctrine\ORM\EntityManagerInterface;
use RevisionTen\CQRS\Services\CommandBus;
use RevisionTen\CQRS\Services\MessageBus;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Question\Question;

/**
 * Class UserMigrateCommand.
 *
 * Use this command to create aggregates for existing non-aggregate users.
 */
class UserMigrateCommand extends Command
{
    /** @var EntityManagerInterface $entityManager */
    private $entityManager;

    /** @var CommandBus $commandBus */
    private $commandBus;

    /** @var MessageBus $messageBus */
    private $messageBus;

    /**
     * UserCreateCommand constructor.
     *
     * @param EntityManagerInterface $entityManager
     * @param CommandBus             $commandBus
     * @param MessageBus             $messageBus
     */
    public function __construct(EntityManagerInterface $entityManager, CommandBus $commandBus, MessageBus $messageBus)
    {
        $this->entityManager = $entityManager;
        $this->commandBus = $commandBus;
        $this->messageBus = $messageBus;

        parent::__construct();
    }

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setName('cms:user:migrate')
            ->setDescription('Creates a user aggregate for an existing account.')
            ->addArgument('username', InputArgument::OPTIONAL, 'The username.')
        ;
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $helper = $this->getHelper('question');

        // Get the username or ask for it.
        $username = $input->getArgument('username');
        if (!$username) {
            $usernameQuestion = new Question('Please enter a username: ');

            $usernameQuestion->setValidator(function ($answer) {
                /** @var UserRead|null $userResult */
                $userResult = $this->entityManager->getRepository(UserRead::class)->findOneByUsername($answer);
                if (!$userResult) {
                    throw new \RuntimeException('User not found.');
                } elseif ('' !== $userResult->getUuid()) {
                    throw new \RuntimeException('User was already migrated.');
                }

                return $answer;
            });
            $usernameQuestion->setMaxAttempts(5);

            $username = $helper->ask($input, $output, $usernameQuestion);
        }

        // The users new aggregate uuid.
        $userUuid = Uuid::uuid1()->toString();

        /**
         * Get the User and save the new aggregate uuid.
         *
         * @var UserRead $user
         */
        $user = $this->entityManager->getRepository(UserRead::class)->findOneBy([
            'username' => $username,
        ]);
        $user->setUuid($userUuid);
        $this->entityManager->flush();

        // Create the aggregate.
        $payload = [
            'username' => $user->getUsername(),
            'email' => $user->getEmail(),
            'avatarUrl' => $user->getAvatarUrl(),
            'password' => $user->getPassword(),
            'secret' => $user->getSecret(),
            'color' => $user->getColor(),
            'migrated' => true,
        ];
        $success = false;
        $successCallback = function ($commandBus, $event) use (&$success) { $success = true; };
        $userCreateCommand = new \RevisionTen\CMS\Command\UserCreateCommand(-1, null, $userUuid, 0, $payload, $successCallback);
        $this->commandBus->dispatch($userCreateCommand);

        if ($success) {
            // Return info about the new user.
            $output->writeln('User '.$username.' migrated.');
        } else {
            $messages = $this->messageBus->getMessagesJson();
            $output->writeln('UserCreateCommand failed.');
            $output->writeln($messages);
        }
    }
}

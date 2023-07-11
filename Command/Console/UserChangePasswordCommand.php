<?php

declare(strict_types=1);

namespace RevisionTen\CMS\Command\Console;

use RevisionTen\CMS\Model\UserAggregate;
use RevisionTen\CMS\Model\UserRead;
use Doctrine\ORM\EntityManagerInterface;
use RevisionTen\CQRS\Services\AggregateFactory;
use RevisionTen\CQRS\Services\CommandBus;
use RevisionTen\CQRS\Services\MessageBus;
use RuntimeException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class UserChangePasswordCommand extends Command
{
    private EntityManagerInterface $entityManager;

    private CommandBus $commandBus;

    private MessageBus $messageBus;

    private UserPasswordHasherInterface $passwordHasher;

    private AggregateFactory $aggregateFactory;

    public function __construct(EntityManagerInterface $entityManager, CommandBus $commandBus, MessageBus $messageBus, UserPasswordHasherInterface $passwordHasher, AggregateFactory $aggregateFactory)
    {
        $this->entityManager = $entityManager;
        $this->commandBus = $commandBus;
        $this->messageBus = $messageBus;
        $this->passwordHasher = $passwordHasher;
        $this->aggregateFactory = $aggregateFactory;

        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setName('cms:user:change_password')
            ->setDescription('Changes a users password.')
            ->addArgument('username', InputArgument::OPTIONAL, 'The username.')
            ->addArgument('password', InputArgument::OPTIONAL, 'The new password.')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $helper = $this->getHelper('question');

        // Get the username or ask for it.
        $username = $input->getArgument('username');
        if (!$username) {
            $usernameQuestion = new Question('Please enter a username: ');

            $usernameQuestion->setValidator(function ($answer) {
                if (!$this->entityManager->getRepository(UserRead::class)->findOneByUsername($answer)) {
                    throw new RuntimeException('User not found.');
                }

                return $answer;
            });
            $usernameQuestion->setMaxAttempts(5);

            $username = $helper->ask($input, $output, $usernameQuestion);
        }

        // Get the password or ask for it.
        $password = $input->getArgument('password');
        if (!$password) {
            $passwordQuestion = new Question('Please enter a password: ');

            $passwordQuestion->setValidator(static function ($answer) {
                if (empty($answer)) {
                    throw new RuntimeException('The password may not be empty.');
                }

                return $answer;
            });
            $passwordQuestion->setMaxAttempts(5);

            $password = $helper->ask($input, $output, $passwordQuestion);
        }

        // Encode the new password.
        $encodedPassword = $this->passwordHasher->hashPassword(new UserRead(), $password);

        /**
         * Get the User and UserAggregate.
         *
         * @var UserRead $user
         */
        $user = $this->entityManager->getRepository(UserRead::class)->findOneBy([
            'username' => $username,
        ]);
        $userUuid = $user->getUuid();
        /** @var UserAggregate $aggregate */
        $aggregate = $this->aggregateFactory->build($userUuid, UserAggregate::class);
        $onVersion = $aggregate->getStreamVersion();

        // Run the change password command.
        $payload = [
            'password' => $encodedPassword,
        ];
        $success = $this->commandBus->dispatch(new \RevisionTen\CMS\Command\UserChangePasswordCommand(
            -1,
            null,
            $userUuid,
            $onVersion,
            $payload
        ));

        if ($success) {
            // Return info about the user.
            $output->writeln('User '.$username.' password changed.');
        } else {
            $messages = $this->messageBus->getMessagesJson();
            $output->writeln('UserChangePasswordCommand failed.');
            print_r($messages);
            return 500;
        }

        return 0;
    }
}

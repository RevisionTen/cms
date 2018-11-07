<?php

declare(strict_types=1);

namespace RevisionTen\CMS\Command\Console;

use Ramsey\Uuid\Uuid;
use RevisionTen\CMS\Model\UserAggregate;
use RevisionTen\CMS\Model\UserRead;
use Doctrine\ORM\EntityManagerInterface;
use RevisionTen\CQRS\Services\AggregateFactory;
use RevisionTen\CQRS\Services\CommandBus;
use RevisionTen\CQRS\Services\MessageBus;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;

/**
 * Class UserChangePasswordCommand.
 */
class UserChangePasswordCommand extends Command
{
    /** @var EntityManagerInterface $entityManager */
    private $entityManager;

    /** @var CommandBus $commandBus */
    private $commandBus;

    /** @var MessageBus $messageBus */
    private $messageBus;

    /** @var UserPasswordEncoderInterface $encoder */
    private $encoder;

    /** @var AggregateFactory $aggregateFactory */
    private $aggregateFactory;

    /**
     * UserCreateCommand constructor.
     *
     * @param EntityManagerInterface       $entityManager
     * @param CommandBus                   $commandBus
     * @param MessageBus                   $messageBus
     * @param UserPasswordEncoderInterface $encoder
     * @param AggregateFactory             $aggregateFactory
     */
    public function __construct(EntityManagerInterface $entityManager, CommandBus $commandBus, MessageBus $messageBus, UserPasswordEncoderInterface $encoder, AggregateFactory $aggregateFactory)
    {
        $this->entityManager = $entityManager;
        $this->commandBus = $commandBus;
        $this->messageBus = $messageBus;
        $this->encoder = $encoder;
        $this->aggregateFactory = $aggregateFactory;

        parent::__construct();
    }

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setName('cms:user:change_password')
            ->setDescription('Changes a users password.')
            ->addArgument('username', InputArgument::OPTIONAL, 'The username.')
            ->addArgument('password', InputArgument::OPTIONAL, 'The new password.')
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
                if (!$this->entityManager->getRepository(UserRead::class)->findOneByUsername($answer)) {
                    throw new \RuntimeException('User not found.');
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

            $passwordQuestion->setValidator(function ($answer) {
                if (empty($answer)) {
                    throw new \RuntimeException('The password may not be empty.');
                }

                return $answer;
            });
            $passwordQuestion->setMaxAttempts(5);

            $password = $helper->ask($input, $output, $passwordQuestion);
        }

        // Encode the new password.
        $encodedPassword = $this->encoder->encodePassword(new UserRead(), $password);

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
        $success = false;
        $successCallback = function ($commandBus, $event) use (&$success) { $success = true; };
        $userChangePasswordCommand = new \RevisionTen\CMS\Command\UserChangePasswordCommand(-1, null, $userUuid, $onVersion, $payload, $successCallback);
        $this->commandBus->dispatch($userChangePasswordCommand);

        if ($success) {
            // Return info about the user.
            $output->writeln('User '.$username.' password changed.');
        } else {
            $messages = $this->messageBus->getMessagesJson();
            $output->writeln('UserChangePasswordCommand failed.');
            $output->writeln($messages);
        }
    }
}

<?php

declare(strict_types=1);

namespace RevisionTen\CMS\Command\Console;

use Exception;
use RevisionTen\CMS\Command\UserGenerateSecretCommand;
use RevisionTen\CMS\Model\UserAggregate;
use RevisionTen\CMS\Model\UserRead;
use Doctrine\ORM\EntityManagerInterface;
use RevisionTen\CQRS\Services\AggregateFactory;
use RevisionTen\CQRS\Services\CommandBus;
use RevisionTen\CQRS\Services\MessageBus;
use RuntimeException;
use Sonata\GoogleAuthenticator\GoogleAuthenticator;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Question\Question;

/**
 * Class UserSecretCommand.
 *
 * This command lets you create a user from the command line.
 */
class UserSecretCommand extends Command
{
    private EntityManagerInterface $entityManager;

    private CommandBus $commandBus;

    private MessageBus $messageBus;

    private AggregateFactory $aggregateFactory;

    public function __construct(EntityManagerInterface $entityManager, CommandBus $commandBus, MessageBus $messageBus, AggregateFactory $aggregateFactory)
    {
        $this->entityManager = $entityManager;
        $this->commandBus = $commandBus;
        $this->messageBus = $messageBus;
        $this->aggregateFactory = $aggregateFactory;

        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setName('cms:user:generate_secret')
            ->setDescription('Creates a user secret.')
            ->addArgument('username', InputArgument::OPTIONAL, 'The username.')
        ;
    }

    /**
     * @throws Exception
     */
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

        /**
         * Get the User.
         *
         * @var UserRead $user
         */
        $user = $this->entityManager->getRepository(UserRead::class)->findOneBy([
            'username' => $username,
        ]);

        if ($user && $user->getUuid()) {
            // Build the aggregate.
            $userUuid = $user->getUuid();
            /**
             * @var UserAggregate $aggregate
             */
            $aggregate = $this->aggregateFactory->build($userUuid, UserAggregate::class);
            $onVersion = $aggregate->getStreamVersion();

            // Generate the new secret.
            $googleAuthenticator = new GoogleAuthenticator();
            $secret = $googleAuthenticator->generateSecret();

            $success = $this->commandBus->dispatch(new UserGenerateSecretCommand(
                -1,
                null,
                $userUuid,
                $onVersion,
                [
                    'secret' => $secret,
                ]
            ));

            if ($success) {
                $output->writeln('User secret generated.');
            } else {
                $messages = $this->messageBus->getMessagesJson();
                $output->writeln('UserGenerateSecretCommand failed.');
                print_r($messages);
                return 500;
            }
        } else {
            $output->writeln('User not found.');
            return 500;
        }

        return 0;
    }
}

<?php

declare(strict_types=1);

namespace RevisionTen\CMS\Command\Console;

use RevisionTen\CMS\Command\UserGenerateSecretCommand;
use RevisionTen\CMS\Model\UserAggregate;
use RevisionTen\CMS\Model\UserRead;
use Doctrine\ORM\EntityManagerInterface;
use RevisionTen\CQRS\Services\AggregateFactory;
use RevisionTen\CQRS\Services\CommandBus;
use RevisionTen\CQRS\Services\MessageBus;
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
    /** @var EntityManagerInterface $entityManager */
    private $entityManager;

    /** @var CommandBus $commandBus */
    private $commandBus;

    /** @var MessageBus $messageBus */
    private $messageBus;

    /** @var AggregateFactory $aggregateFactory */
    private $aggregateFactory;

    /**
     * UserSecretCommand constructor.
     *
     * @param EntityManagerInterface $entityManager
     * @param CommandBus             $commandBus
     * @param MessageBus             $messageBus
     * @param AggregateFactory       $aggregateFactory
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
            ->setName('cms:user:generate_secret')
            ->setDescription('Creates a user secret.')
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
                if (!$this->entityManager->getRepository(UserRead::class)->findOneByUsername($answer)) {
                    throw new \RuntimeException('User not found.');
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
            /** @var UserAggregate $aggregate */
            $aggregate = $this->aggregateFactory->build($userUuid, UserAggregate::class);
            $onVersion = $aggregate->getStreamVersion();

            // Generate the new secret.
            $googleAuthenticator = new GoogleAuthenticator();
            $secret = $googleAuthenticator->generateSecret();

            $success = false;
            $successCallback = function ($commandBus, $event) use (&$success) { $success = true; };
            $userGenerateSecretCommand = new UserGenerateSecretCommand(-1, null, $userUuid, $onVersion, [
                'secret' => $secret,
            ], $successCallback);
            $this->commandBus->dispatch($userGenerateSecretCommand);

            if ($success) {
                $output->writeln('User secret generated.');
            } else {
                $messages = $this->messageBus->getMessagesJson();
                $output->writeln('UserGenerateSecretCommand failed.');
                $output->writeln($messages);
            }
        } else {
            $output->writeln('User not found.');
        }
    }

    /**
     * Generate a random string, using a cryptographically secure
     * pseudorandom number generator (random_int).
     *
     * For PHP 7, random_int is a PHP core function
     * For PHP 5.x, depends on https://github.com/paragonie/random_compat
     *
     * @param int    $length   How many characters do we want?
     * @param string $keyspace A string of all possible characters
     *                         to select from
     *
     * @return string
     */
    private function random_str($length, $keyspace = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ')
    {
        $pieces = [];
        $max = mb_strlen($keyspace, '8bit') - 1;
        for ($i = 0; $i < $length; ++$i) {
            $pieces[] = $keyspace[random_int(0, $max)];
        }

        return implode('', $pieces);
    }
}

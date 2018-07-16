<?php

declare(strict_types=1);

namespace RevisionTen\CMS\Command\Console;

use RevisionTen\CMS\Model\User;
use RevisionTen\CMS\Services\SecretService;
use Doctrine\ORM\EntityManagerInterface;
use Google\Authenticator\GoogleAuthenticator;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Question\Question;

/**
 * Class UserSecretCommand.
 *
 * This command lets you create a user from the command line.
 */
class UserSecretCommand extends ContainerAwareCommand
{
    /** @var EntityManagerInterface $entityManager */
    private $entityManager;

    /** @var SecretService $secretService */
    private $secretService;

    /**
     * UserSecretCommand constructor.
     *
     * @param EntityManagerInterface $entityManager
     */
    public function __construct(EntityManagerInterface $entityManager, SecretService $secretService)
    {
        $this->entityManager = $entityManager;
        $this->secretService = $secretService;

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
                if (!$this->entityManager->getRepository(User::class)->findOneByUsername($answer)) {
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
         * @var User $user
         */
        $user = $this->entityManager->getRepository(User::class)->findOneBy([
            'username' => $username,
        ]);

        if ($user) {
            $googleAuthenticator = new GoogleAuthenticator();
            $secret = $googleAuthenticator->generateSecret();
            $user->setSecret($secret);

            // Persist the user.
            $this->entityManager->persist($user);
            $this->entityManager->flush();

            // Send the secret QRCode via mail.
            $this->secretService->sendSecret($secret, $user->getUsername(), $user->getEmail());

            $output->writeln('User secret generated.');
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

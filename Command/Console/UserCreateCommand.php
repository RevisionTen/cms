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
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;

/**
 * Class UserCreateCommand.
 *
 * This command lets you create a user from the command line.
 */
class UserCreateCommand extends ContainerAwareCommand
{
    /** @var EntityManagerInterface $entityManager */
    private $entityManager;

    /** @var UserPasswordEncoderInterface $encoder */
    private $encoder;

    /** @var SecretService $secretService */
    private $secretService;

    /**
     * UserCreateCommand constructor.
     *
     * @param UserPasswordEncoderInterface $encoder
     * @param EntityManagerInterface       $entityManager
     * @param SecretService                $secretService
     */
    public function __construct(UserPasswordEncoderInterface $encoder, EntityManagerInterface $entityManager, SecretService $secretService)
    {
        $this->entityManager = $entityManager;
        $this->encoder = $encoder;
        $this->secretService = $secretService;

        parent::__construct();
    }

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setName('cms:user:create')
            ->setDescription('Creates a user account.')
            ->addArgument('username', InputArgument::OPTIONAL, 'The username.')
            ->addArgument('password', InputArgument::OPTIONAL, 'The users password.')
            ->addArgument('email', InputArgument::OPTIONAL, 'The users email.')
            ->addArgument('avatarUrl', InputArgument::OPTIONAL, 'The users avatar url.')
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
                if ($this->entityManager->getRepository(User::class)->findOneByUsername($answer)) {
                    throw new \RuntimeException('This username is already taken.');
                }

                return $answer;
            });
            $usernameQuestion->setMaxAttempts(5);

            $username = $helper->ask($input, $output, $usernameQuestion);
        }

        // Get the email or ask for it.
        $email = $input->getArgument('email');
        if (!$email) {
            $emailQuestion = new Question('Please enter an email: ');

            $emailQuestion->setValidator(function ($answer) {
                if ($this->entityManager->getRepository(User::class)->findOneByEmail($answer)) {
                    throw new \RuntimeException('This email is already taken.');
                }

                return $answer;
            });
            $emailQuestion->setMaxAttempts(5);

            $email = $helper->ask($input, $output, $emailQuestion);
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

        // Get the avatarUrl or ask for it.
        $avatarUrl = $input->getArgument('avatarUrl');
        if (!$avatarUrl) {
            $avatarUrlQuestion = new Question('(optional) Please enter an avatar url: ');
            $avatarUrl = $helper->ask($input, $output, $avatarUrlQuestion);
        }

        // Create the User.
        $user = new User();
        $user->setUsername($username);
        $user->setEmail($email);
        $user->setAvatarUrl($avatarUrl);

        // Encoded the password.
        $encodedPassword = $this->encoder->encodePassword($user, $password);
        $user->setPassword($encodedPassword);

        $googleAuthenticator = new GoogleAuthenticator();
        $secret = $googleAuthenticator->generateSecret();
        $user->setSecret($secret);

        // Persist the user.
        $this->entityManager->persist($user);
        $this->entityManager->flush();

        // Send the secret QRCode via mail.
        $this->secretService->sendSecret($secret, $user->getUsername(), $user->getEmail());

        // Return info about the new user.
        $output->writeln('User saved.');
        $output->writeln('Username: '.$username);
        $output->writeln('Password: '.$password);
        $output->writeln('Avatar url: '.$avatarUrl);
        $output->writeln('Email: '.$email);
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

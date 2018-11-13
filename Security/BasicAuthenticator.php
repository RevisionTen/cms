<?php

namespace RevisionTen\CMS\Security;

use RevisionTen\CMS\Model\UserRead;
use RevisionTen\CMS\Utilities\RandomHelpers;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Flash\FlashBag;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Guard\AbstractGuardAuthenticator;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\User\UserProviderInterface;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;
use Symfony\Component\Translation\TranslatorInterface;

class BasicAuthenticator extends AbstractGuardAuthenticator
{
    /**
     * @var \Swift_Mailer
     */
    private $swift_Mailer;

    /**
     * @var UserPasswordEncoderInterface $encoder
     */
    private $encoder;

    /**
     * @var SessionInterface
     */
    private $session;

    /**
     * @var TranslatorInterface
     */
    private $translator;

    /**
     * @var array
     */
    private $config;

    /**
     * BasicAuthenticator constructor.
     *
     * @param \Swift_Mailer                $swift_Mailer
     * @param UserPasswordEncoderInterface $encoder
     * @param RequestStack                 $requestStack
     * @param TranslatorInterface          $translator
     * @param array                        $config
     */
    public function __construct(\Swift_Mailer $swift_Mailer, UserPasswordEncoderInterface $encoder, RequestStack $requestStack, TranslatorInterface $translator, array $config)
    {
        $this->swift_Mailer = $swift_Mailer;
        $this->encoder = $encoder;
        $this->translator = $translator;
        $this->session = $this->getSession($requestStack);
        $this->config = $config;
    }

    /**
     * Returns the active session or starts one.
     *
     * @param \Symfony\Component\HttpFoundation\RequestStack $requestStack
     *
     * @return \Symfony\Component\HttpFoundation\Session\SessionInterface
     */
    private function getSession(RequestStack $requestStack): SessionInterface
    {
        $request = $requestStack->getMasterRequest();
        $session = $request->getSession();

        if (null === $session) {
            $session = new Session();
        }

        if (!$session->isStarted()) {
            $session->start();
        }

        return $session;
    }

    /**
     * {@inheritdoc}
     */
    public function supports(Request $request)
    {
        $username = $request->get('login')['username'] ?? null;
        $password = $request->get('login')['password'] ?? null;

        return $username && $password;
    }

    /**
     * Called on every request. Return whatever credentials you want to
     * be passed to getUser() as $credentials.
     *
     * @param Request $request
     *
     * @return array|bool
     */
    public function getCredentials(Request $request)
    {
        $username = $request->get('login')['username'] ?? null;
        $password = $request->get('login')['password'] ?? null;

        if ($username && $password) {
            // User loggs in.
            return [
                'username' => $username,
                'password' => $password,
            ];
        }

        return [];
    }

    /**
     * {@inheritdoc}
     */
    public function getUser($credentials, UserProviderInterface $userProvider)
    {
        $username = $credentials['username'];

        // If its a User object, checkCredentials() is called, otherwise authentication will fail.
        return null !== $username ? $userProvider->loadUserByUsername($username) : null;
    }

    /**
     * Return true to cause authentication success.
     *
     * @param array         $credentials
     * @param UserInterface $user
     *
     * @return bool
     */
    public function checkCredentials($credentials, UserInterface $user)
    {
        return $this->encoder->isPasswordValid($user, $credentials['password']);
    }

    /**
     * {@inheritdoc}
     */
    public function onAuthenticationSuccess(Request $request, TokenInterface $token, $providerKey)
    {
        // Remember the username in the session for the Code Authenticator.
        $username = $request->get('login')['username'] ?? null;
        $this->session->set('username', $username);

        // Sent a mail with the PIN code.
        $useMailCodes = $this->config['use_mail_codes'] ?? false;

        if ($useMailCodes) {
            /** @var UserRead $user */
            $user = $token->getUser();
            $code = RandomHelpers::randomString(6, '0123456789');
            $codeExpires = time() + 330;

            $this->session->set('mailCode', $code);
            $this->session->set('mailCodeExpires', $codeExpires);
            $this->sendCodeMail($user, $code);
        }

        // On success, let the request continue.
        return null;
    }

    /**
     * Sends a mail with a login code.
     *
     * @param UserRead $user
     * @param string   $code
     */
    private function sendCodeMail(UserRead $user, string $code): void
    {
        $subject = $this->translator->trans('Login Code for %username%', [
            '%username%' => $user->getUsername(),
        ]);

        $yourlogin = $this->translator->trans('Your login code is');
        $validfor = $this->translator->trans('This code is valid for 5 minutes.');

        $messageBody = <<<EOT
$yourlogin:
<pre style="font-size: 3em;font-weight: bold;">$code</pre>
$validfor
EOT;

        $mail = $user->getEmail();

        $senderConfigExists = isset($this->config['mailer_from'], $this->config['mailer_sender'], $this->config['mailer_return_path']) && $this->config['mailer_from'] && $this->config['mailer_sender'] && $this->config['mailer_return_path'];

        if ($senderConfigExists) {
            $message = (new \Swift_Message($subject))
                ->setFrom($this->config['mailer_from'])
                ->setSender($this->config['mailer_sender'])
                ->setReturnPath($this->config['mailer_return_path'])
                ->setTo($mail)
                ->setBody($messageBody, 'text/html')
            ;
        } else {
            // Attempt to send without explicitly setting the sender.
            $message = (new \Swift_Message($subject))
                ->setTo($mail)
                ->setBody($messageBody, 'text/html')
            ;
        }

        $this->swift_Mailer->send($message);
    }

    /**
     * {@inheritdoc}
     */
    public function onAuthenticationFailure(Request $request, AuthenticationException $exception)
    {
        $message = $this->translator->trans('Error during login');
        $flashBag = $this->session->getFlashBag();
        if (!$flashBag) {
            $flashBag = new FlashBag('login_errors');
            $this->session->registerBag($flashBag);
        }
        $flashBag->add('danger', $message);

        return new RedirectResponse('/login');
    }

    /**
     * Called when authentication is needed, but it's not sent.
     *
     * @param Request                      $request
     * @param AuthenticationException|null $authException
     *
     * @return RedirectResponse
     */
    public function start(Request $request, AuthenticationException $authException = null)
    {
        return new RedirectResponse('/login');
    }

    /**
     * {@inheritdoc}
     */
    public function supportsRememberMe()
    {
        return false;
    }
}

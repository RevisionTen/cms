<?php

namespace RevisionTen\CMS\Security;

use RevisionTen\CMS\Model\UserRead;
use RevisionTen\CMS\Utilities\RandomHelpers;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Flash\FlashBag;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Guard\AbstractGuardAuthenticator;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\User\UserProviderInterface;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class BasicAuthenticator extends AbstractGuardAuthenticator
{
    private MailerInterface $mailer;

    private UserPasswordEncoderInterface $encoder;

    private SessionInterface $session;

    private TranslatorInterface $translator;

    private array $config;

    private bool $isDev;

    public function __construct(MailerInterface $mailer, UserPasswordEncoderInterface $encoder, RequestStack $requestStack, TranslatorInterface $translator, array $config, string $env)
    {
        $this->mailer = $mailer;
        $this->encoder = $encoder;
        $this->translator = $translator;
        $this->session = $this->getSession($requestStack);
        $this->config = $config;
        $this->isDev = 'dev' === $env;
    }

    /**
     * Returns the active session or starts one.
     *
     * @param RequestStack $requestStack
     *
     * @return SessionInterface
     */
    private function getSession(RequestStack $requestStack): SessionInterface
    {
        $request = $requestStack->getMainRequest();
        $session = $request ? $request->getSession() : null;

        if (null === $session) {
            $session = new Session();
        }

        if (!$session->isStarted()) {
            $session->start();
        }

        return $session;
    }

    public function supports(Request $request): bool
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
            // User logs in.
            return [
                'username' => $username,
                'password' => $password,
            ];
        }

        return [];
    }

    public function getUser($credentials, UserProviderInterface $userProvider)
    {
        $username = $credentials['username'];

        // If it's a User object, checkCredentials() is called, otherwise authentication will fail.
        return null !== $username ? $userProvider->loadUserByIdentifier($username) : null;
    }

    /**
     * Return true to cause authentication success.
     *
     * @param array         $credentials
     * @param UserInterface $user
     *
     * @return bool
     */
    public function checkCredentials($credentials, UserInterface $user): bool
    {
        return $this->encoder->isPasswordValid($user, $credentials['password']);
    }

    /**
     * @throws TransportExceptionInterface
     */
    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $providerKey): ?Response
    {
        // Remember the username in the session for the Code Authenticator.
        $username = $request->get('login')['username'] ?? null;
        $this->session->set('username', $username);

        if ($this->isDev) {
            // Do not send mail code mail in dev environment, let the request continue.
            return null;
        }

        // Sent a mail with the PIN code.
        $useMailCodes = $this->config['use_mail_codes'] ?? false;

        if ($useMailCodes) {
            /**
             * @var UserRead $user
             */
            $user = $token->getUser();
            $code = RandomHelpers::randomString(6, '0123456789');
            $codeLifetime = (int) ($this->config['mail_code_lifetime'] ?? 5);
            $codeExpires = time() + ($codeLifetime * 60);

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
     *
     * @throws TransportExceptionInterface
     */
    private function sendCodeMail(UserRead $user, string $code): void
    {
        $subject = $this->translator->trans('admin.label.loginCodeFor', [
            '%username%' => $user->getUsername(),
        ], 'cms');

        $yourlogin = $this->translator->trans('admin.label.loginCodeIs', [], 'cms');
        $validfor = $this->translator->trans('admin.label.loginCodeExpires', [
            '%minutes%' => $this->config['mail_code_lifetime'] ?? 5,
        ], 'cms');

        $messageBody = <<<EOT
$yourlogin:
<pre style="font-size: 3em;font-weight: bold;">$code</pre>
$validfor
EOT;

        $mail = $user->getEmail();

        $senderConfigExists = isset($this->config['mailer_from'], $this->config['mailer_sender'], $this->config['mailer_return_path']) && $this->config['mailer_from'] && $this->config['mailer_sender'] && $this->config['mailer_return_path'];

        if ($senderConfigExists) {
            $message = (new Email())
                ->from($this->config['mailer_from'])
                ->sender($this->config['mailer_sender'])
                ->returnPath($this->config['mailer_return_path'])
                ->to($mail)
                ->subject($subject)
                ->html($messageBody)
            ;
        } else {
            // Attempt to send without explicitly setting the sender.
            $message = (new Email())
                ->to($mail)
                ->subject($subject)
                ->html($messageBody)
            ;
        }

        $this->mailer->send($message);
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        $message = $this->translator->trans('admin.label.loginError', [], 'cms');
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
    public function start(Request $request, AuthenticationException $authException = null): RedirectResponse
    {
        return new RedirectResponse('/login');
    }

    public function supportsRememberMe(): bool
    {
        return false;
    }
}

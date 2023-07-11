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
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Credentials\PasswordCredentials;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Contracts\Translation\TranslatorInterface;

class BasicAuthenticator extends AbstractAuthenticator
{
    private MailerInterface $mailer;

    private SessionInterface $session;

    private TranslatorInterface $translator;

    private array $config;

    public function __construct(MailerInterface $mailer, RequestStack $requestStack, TranslatorInterface $translator, array $config)
    {
        $this->mailer = $mailer;
        $this->translator = $translator;
        $this->session = $this->getSession($requestStack);
        $this->config = $config;
    }

    public function supports(Request $request): bool
    {
        $username = $request->get('login')['username'] ?? null;
        $password = $request->get('login')['password'] ?? null;

        return !empty($username) && !empty($password);
    }

    public function authenticate(Request $request): Passport
    {
        $username = $request->get('login')['username'] ?? null;
        $password = $request->get('login')['password'] ?? null;

        if (empty($username) || empty($password)) {
            // The login data was empty, authentication fails with HTTP Status
            // Code 401 "Unauthorized"
            throw new UnauthorizedHttpException('No login data provided');
        }

        return new Passport(new UserBadge($username), new PasswordCredentials($password));
    }

    /**
     * @throws TransportExceptionInterface
     */
    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        // Remember the username in the session for the Code Authenticator.
        $this->session->set('username', $token->getUserIdentifier());

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

        // On success, redirect to code login.
        return new RedirectResponse('/code');
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
}

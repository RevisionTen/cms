<?php

namespace RevisionTen\CMS\Security;

use Exception;
use RevisionTen\CMS\Command\UserLoginCommand;
use RevisionTen\CMS\Model\UserRead;
use RevisionTen\CQRS\Services\CommandBus;
use Sonata\GoogleAuthenticator\GoogleAuthenticator;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Flash\FlashBag;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Credentials\CustomCredentials;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use function is_object;
use function strtr;
use function time;

class CodeAuthenticator extends AbstractAuthenticator
{
    private SessionInterface $session;

    private array $config;

    private CommandBus $commandBus;

    private bool $isDev;

    public function __construct(RequestStack $requestStack, CommandBus $commandBus, array $config, string $env)
    {
        $this->session = $this->getSession($requestStack);
        $this->commandBus = $commandBus;
        $this->config = $config;
        $this->isDev = 'dev' === $env;
    }

    public function supports(Request $request): bool
    {
        $hasUsername = $this->session->has('username');
        $hasCode = !empty($request->get('code')['code'] ?? null);
        $isCodeLoginPath = $request->getPathInfo() === '/admin/dashboard';
        $isPost = $request->isMethod('POST');

        return $isCodeLoginPath && $isPost && $hasUsername && $hasCode;
    }

    public function authenticate(Request $request): Passport
    {
        $username = $this->session->get('username');
        $code = $request->get('code')['code'] ?? null;

        $hasCode = !empty($code);
        if (empty($username) || !$hasCode) {
            // The login data was empty, authentication fails with HTTP Status
            // Code 401 "Unauthorized"
            throw new UnauthorizedHttpException('No login data provided');
        }

        return new Passport(new UserBadge($username), new CustomCredentials(
            function ($code, UserRead $user) {
                // If this function returns anything else than `true`, the credentials are marked as invalid.
                return $this->isDev || $this->isCodeValid($user->getSecret(), $code);
            },
            $code
        ));
    }

    /**
     * @throws Exception
     */
    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        /**
         * @var UserRead $user
         */
        $user = $token->getUser();
        if (!is_object($user)) {
            return null;
        }

        $userId = $user->getId();
        $userUuid = $user->getUuid();

        // Check if user has an aggregate.
        if (null !== $userUuid && !$this->isDev) {
            $onVersion = $user->getVersion();

            // Dispatch login event.
            $userLoginCommand = new UserLoginCommand($userId, null, $userUuid, $onVersion, [
                'device' => $request->headers->get('User-Agent') ?? 'unknown',
                'ip' => $request->getClientIp() ?? 'unknown',
            ]);
            $this->commandBus->dispatch($userLoginCommand);
        }

        // On success, let the request continue to the dashboard.
        return null;
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        $message = strtr($exception->getMessageKey(), $exception->getMessageData());
        $flashBag = $this->session->getFlashBag();
        if (!$flashBag) {
            $flashBag = new FlashBag('login_errors');
            $this->session->registerBag($flashBag);
        }
        $flashBag->add('danger', $message);

        return new RedirectResponse('/code');
    }

    private function isCodeValid(string $secret, string $code): bool
    {
        $useMailCodes = $this->config['use_mail_codes'] ?? false;

        if ($useMailCodes) {
            $mailCode = $this->session->get('mailCode');
            $mailCodeExpires = $this->session->get('mailCodeExpires');
            $validCode = ($mailCode === $code) && (time() < $mailCodeExpires);
        } else {
            $googleAuthenticator = new GoogleAuthenticator();
            $validCode = $googleAuthenticator->checkCode($secret, $code);
        }

        return $validCode;
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

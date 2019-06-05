<?php

namespace RevisionTen\CMS\Security;

use RevisionTen\CMS\Command\UserLoginCommand;
use RevisionTen\CQRS\Services\CommandBus;
use Sonata\GoogleAuthenticator\GoogleAuthenticator;
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

class CodeAuthenticator extends AbstractGuardAuthenticator
{
    /**
     * @var SessionInterface
     */
    private $session;

    /**
     * @var array
     */
    private $config;

    /**
     * @var CommandBus
     */
    private $commandBus;

    /**
     * CodeAuthenticator constructor.
     *
     * @param RequestStack $requestStack
     * @param CommandBus   $commandBus
     * @param array        $config
     */
    public function __construct(RequestStack $requestStack, CommandBus $commandBus, array $config)
    {
        $this->session = $this->getSession($requestStack);
        $this->commandBus = $commandBus;
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
        $session = $request ? $request->getSession() : null;

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
        $code = $request->get('code')['code'] ?? null;
        $username = $this->session->has('username');

        // Returns true If a code was submitted and a username exists, otherwise skip authentication.
        return $username && $code;
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
        $username = $this->session->get('username');
        $code = $request->get('code')['code'] ?? null;

        if ($username && $code) {
            // Username and password matches, code needs to be checked.
            return [
                'username' => $username,
                'code' => $code,
            ];
        }

        return [];
    }

    /**
     * {@inheritdoc}
     */
    public function getUser($credentials, UserProviderInterface $userProvider)
    {
        $username = $credentials['username'] ?? null;

        // If its a User object, checkCredentials() is called, otherwise authentication will fail.
        return null !== $username ? $userProvider->loadUserByUsername($username) : null;
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
     * Return true to cause authentication success.
     *
     * @param array         $credentials
     * @param UserInterface $user
     *
     * @return bool
     */
    public function checkCredentials($credentials, UserInterface $user)
    {
        // Check if submitted Code is Valid.
        /** @var \RevisionTen\CMS\Model\UserRead $user */
        $secret = $user->getSecret();

        return $this->isCodeValid($secret, $credentials['code']);
    }

    /**
     * {@inheritdoc}
     */
    public function onAuthenticationSuccess(Request $request, TokenInterface $token, $providerKey)
    {
        $user = $token->getUser();

        if (\is_object($user)) {
            $userId = $user->getId();
            $userUuid = $user->getUuid();

            // Check if user has an aggregate.
            if (null !== $userUuid) {
                $onVersion = $user->getVersion();

                // Dispatch login event.
                $userLoginCommand = new UserLoginCommand($userId, null, $userUuid, $onVersion, [
                    'device' => $request->headers->get('User-Agent') ?? 'unknown',
                    'ip' => $request->getClientIp() ?? 'unknown',
                ]);
                $this->commandBus->dispatch($userLoginCommand);
            }
        } else {
            return false;
        }

        // On success, let the request continue.
        return null;
    }

    /**
     * {@inheritdoc}
     */
    public function onAuthenticationFailure(Request $request, AuthenticationException $exception)
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
        return new RedirectResponse('/code');
    }

    /**
     * {@inheritdoc}
     */
    public function supportsRememberMe()
    {
        return false;
    }
}

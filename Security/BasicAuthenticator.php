<?php

namespace RevisionTen\CMS\Security;

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

class BasicAuthenticator extends AbstractGuardAuthenticator
{
    /**
     * @var UserPasswordEncoderInterface $encoder
     */
    private $encoder;

    /**
     * @var SessionInterface
     */
    private $session;

    /**
     * BasicAuthenticator constructor.
     *
     * @param UserPasswordEncoderInterface $encoder
     * @param RequestStack                 $requestStack
     */
    public function __construct(UserPasswordEncoderInterface $encoder, RequestStack $requestStack)
    {
        $this->encoder = $encoder;
        $this->session = $this->getSession($requestStack);
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
        if ($request->get('username') && $request->get('password')) {
            return true;
        } else {
            return false;
        }
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
        if ($request->get('username') && $request->get('password')) {
            // User loggs in.
            return [
                'username' => $request->get('username'),
                'password' => $request->get('password'),
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

        if (null === $username) {
            // If null, authentication will fail.
            return null;
        } else {
            // If its a User object, checkCredentials() is called.
            return $userProvider->loadUserByUsername($username);
        }
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
        $this->session->set('username', $request->get('username'));

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

        $requestUrl = $request->getPathInfo().'?'.http_build_query($request->query->all());

        return new RedirectResponse('/login?redirectTo='.urlencode($requestUrl));
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
        $requestUrl = $request->getPathInfo().'?'.http_build_query($request->query->all());

        return new RedirectResponse('/login?redirectTo='.urlencode($requestUrl));
    }

    /**
     * {@inheritdoc}
     */
    public function supportsRememberMe()
    {
        return false;
    }
}

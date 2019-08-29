<?php

declare(strict_types=1);

namespace RevisionTen\CMS\EventListener;

use RevisionTen\CMS\Command\UserLogoutCommand;
use RevisionTen\CQRS\Services\CommandBus;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Http\Logout\LogoutHandlerInterface;
use function is_object;

class LogoutListener implements LogoutHandlerInterface
{
    /**
     * @var CommandBus
     */
    private $commandBus;

    public function __construct(CommandBus $commandBus)
    {
        $this->commandBus = $commandBus;
    }

    /**
     * @param \Symfony\Component\HttpFoundation\Request                            $request
     * @param \Symfony\Component\HttpFoundation\Response                           $response
     * @param \Symfony\Component\Security\Core\Authentication\Token\TokenInterface $token
     *
     * @throws \Exception
     */
    public function logout(Request $request, Response $response, TokenInterface $token): void
    {
        $user = $token->getUser();

        if (is_object($user)) {
            $userId = $user->getId();
            $userUuid = $user->getUuid();

            // Check if user has an aggregate.
            if (null !== $userUuid) {
                $onVersion = $user->getVersion();

                // Dispatch logout event.
                $userLogoutCommand = new UserLogoutCommand($userId, null, $userUuid, $onVersion, []);
                $this->commandBus->dispatch($userLogoutCommand);
            }
        }
    }
}

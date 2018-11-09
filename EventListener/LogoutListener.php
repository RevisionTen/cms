<?php

declare(strict_types=1);

namespace RevisionTen\CMS\EventListener;

use RevisionTen\CMS\Command\UserLogoutCommand;
use RevisionTen\CQRS\Services\CommandBus;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Http\Logout\LogoutHandlerInterface;

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

    public function logout(Request $request, Response $response, TokenInterface $token)
    {
        $user = $token->getUser();

        if (\is_object($user)) {
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

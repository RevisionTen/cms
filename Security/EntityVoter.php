<?php

namespace RevisionTen\CMS\Security;

use RevisionTen\CMS\Model\UserRead;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

class EntityVoter extends Voter
{
    protected function supports($attribute, $subject): bool
    {
        // Todo: Check if attribute is in permissions config.
        return true;
    }

    protected function voteOnAttribute($attribute, $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();

        if (!$user instanceof UserRead) {
            // the user must be logged in; if not, deny access
            return false;
        }

        // Check if the users role has this attribute as a permission.
        foreach ($user->getRoleEntities() as $role) {
            foreach ($role->getPermissions() as $permission) {
                if ($permission === $attribute || 'do_everything' === $permission) {
                    return true;
                }
            }
        }

        return false;
    }
}

<?php

declare(strict_types=1);

namespace RevisionTen\CMS\Listener;

use RevisionTen\CMS\Services\UserService;

abstract class UserBaseListener
{
    /**
     * @var UserService
     */
    protected $userService;

    /**
     * UserBaseListener constructor.
     *
     * @param UserService $userService
     */
    public function __construct(UserService $userService)
    {
        $this->userService = $userService;
    }
}

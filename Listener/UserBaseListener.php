<?php

declare(strict_types=1);

namespace RevisionTen\CMS\Listener;

use RevisionTen\CMS\Services\UserService;

abstract class UserBaseListener
{
    /** @var UserService */
    protected $userService;

    /** @var array */
    protected $config;

    /**
     * UserBaseListener constructor.
     *
     * @param UserService $userService
     * @param boolean     $useMailCodes
     */
    public function __construct(UserService $userService, array $config)
    {
        $this->userService = $userService;
        $this->config = $config;
    }
}

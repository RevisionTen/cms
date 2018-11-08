<?php

declare(strict_types=1);

namespace RevisionTen\CMS\Model;

use RevisionTen\CQRS\Model\Aggregate;

final class UserAggregate extends Aggregate
{
    /** @var string */
    public $username;

    /** @var string */
    public $email;

    /** @var string */
    public $password;

    /** @var string */
    public $secret;

    /** @var string */
    public $color;

    /** @var string */
    public $avatarUrl;

    /** @var array */
    public $permissions;

    /** @var array */
    public $devices = [];

    /** @var array */
    public $ips = [];
}

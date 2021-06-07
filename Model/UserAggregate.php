<?php

declare(strict_types=1);

namespace RevisionTen\CMS\Model;

use RevisionTen\CQRS\Model\Aggregate;

final class UserAggregate extends Aggregate
{
    public ?string $username = null;

    public ?string $email = null;

    public ?string $password = null;

    public ?string $secret = null;

    public ?string $resetToken = null;

    public ?string $color = null;

    public ?string $avatarUrl = null;

    public ?string $theme = null;

    public array $devices = [];

    public array $ips = [];

    public array $websites = [];

    public array $roles = [];

    public array $extra = [];
}

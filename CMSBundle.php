<?php

declare(strict_types=1);

namespace RevisionTen\CMS;

use RevisionTen\CMS\Entity\Alias;
use RevisionTen\CMS\Entity\Domain;
use RevisionTen\CMS\Entity\FileRead;
use RevisionTen\CMS\Entity\MenuRead;
use RevisionTen\CMS\Entity\PageRead;
use RevisionTen\CMS\Entity\PageStreamRead;
use RevisionTen\CMS\Entity\RoleRead;
use RevisionTen\CMS\Entity\Task;
use RevisionTen\CMS\Entity\UserRead;
use RevisionTen\CMS\Entity\Website;
use Symfony\Component\HttpKernel\Bundle\Bundle;
use function class_alias;

class CMSBundle extends Bundle
{
    public const VERSION = '3.1.1';
}

class_alias(Domain::class, '\\RevisionTen\\CMS\\Model\\Domain');
class_alias(Alias::class, '\\RevisionTen\\CMS\\Model\\Alias');
class_alias(FileRead::class, '\\RevisionTen\\CMS\\Model\\FileRead');
class_alias(MenuRead::class, '\\RevisionTen\\CMS\\Model\\MenuRead');
class_alias(PageRead::class, '\\RevisionTen\\CMS\\Model\\PageRead');
class_alias(PageStreamRead::class, '\\RevisionTen\\CMS\\Model\\PageStreamRead');
class_alias(RoleRead::class, '\\RevisionTen\\CMS\\Model\\RoleRead');
class_alias(Task::class, '\\RevisionTen\\CMS\\Model\\Task');
class_alias(UserRead::class, '\\RevisionTen\\CMS\\Model\\User');
class_alias(UserRead::class, '\\RevisionTen\\CMS\\Model\\UserRead');
class_alias(Website::class, '\\RevisionTen\\CMS\\Model\\Website');

<?php

declare(strict_types=1);

namespace RevisionTen\CMS\Command;

use RevisionTen\CMS\Handler\FileUpdateHandler;
use RevisionTen\CMS\Handler\MenuCreateHandler;
use RevisionTen\CMS\Model\File;
use RevisionTen\CMS\Model\Menu;
use RevisionTen\CQRS\Command\Command;
use RevisionTen\CQRS\Interfaces\CommandInterface;

final class FileUpdateCommand extends Command implements CommandInterface
{
    public const HANDLER = FileUpdateHandler::class;
    public const AGGREGATE = File::class;

    /**
     * {@inheritdoc}
     */
    public function getHandlerClass(): string
    {
        return self::HANDLER;
    }

    /**
     * {@inheritdoc}
     */
    public function getAggregateClass(): string
    {
        return self::AGGREGATE;
    }
}

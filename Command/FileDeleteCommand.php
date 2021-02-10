<?php

declare(strict_types=1);

namespace RevisionTen\CMS\Command;

use RevisionTen\CMS\Handler\FileDeleteHandler;
use RevisionTen\CMS\Model\File;
use RevisionTen\CQRS\Command\Command;
use RevisionTen\CQRS\Interfaces\CommandInterface;

final class FileDeleteCommand extends Command implements CommandInterface
{
    public const HANDLER = FileDeleteHandler::class;
    public const AGGREGATE = File::class;

    /**
     * {@inheritdoc}
     */
    public static function getHandlerClass(): string
    {
        return self::HANDLER;
    }

    /**
     * {@inheritdoc}
     */
    public static function getAggregateClass(): string
    {
        return self::AGGREGATE;
    }
}

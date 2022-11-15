<?php

declare(strict_types=1);

namespace RevisionTen\CMS\Entity;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use RevisionTen\CMS\Traits\LanguageAndWebsiteTrait;
use RevisionTen\CMS\Traits\ReadModelTrait;
use function in_array;

/**
 * @ORM\Entity
 * @ORM\Table(name="file_read", uniqueConstraints={
 *      @ORM\UniqueConstraint(name="unique_file",
 *          columns={"uuid"})
 * })
 */
class FileRead
{
    use ReadModelTrait;
    use LanguageAndWebsiteTrait;

    /**
     * @ORM\Column(type="string")
     */
    public string $title;

    /**
     * @ORM\Column(type="string")
     */
    public string $path;

    /**
     * @ORM\Column(type="boolean", options={"default": 0})
     */
    public bool $deleted;

    /**
     * @ORM\Column(type="string", options={"collation": "utf8_unicode_ci"})
     */
    public string $mimeType;

    /**
     * @ORM\Column(type="integer")
     */
    public int $size;

    /**
     * @ORM\Column(type="datetime_immutable", options={"default": "CURRENT_TIMESTAMP"})
     */
    public DateTimeImmutable $created;

    /**
     * @ORM\Column(type="datetime_immutable", options={"default": "CURRENT_TIMESTAMP"})
     */
    public DateTimeImmutable $modified;

    public function isImage(): bool
    {
        return in_array($this->mimeType, [
            'image/jpeg',
            'image/png',
            'image/gif',
            'image/svg',
            'image/avif',
            'image/webp',
        ]);
    }
}

<?php

declare(strict_types=1);

namespace RevisionTen\CMS\Model;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use RevisionTen\CMS\Traits\LanguageAndWebsiteTrait;
use RevisionTen\CMS\Traits\ReadModelTrait;
use function in_array;

/**
 * Class FileRead.
 *
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
     * @var string
     * @ORM\Column(type="string")
     */
    private $title;

    /**
     * @var string
     * @ORM\Column(type="string")
     */
    private $path;

    /**
     * @var bool
     * @ORM\Column(type="boolean", options={"default": 0})
     */
    private $deleted;

    /**
     * @var string
     * @ORM\Column(type="string", options={"collation": "utf8_unicode_ci"})
     */
    private $mimeType;

    /**
     * @var int
     * @ORM\Column(type="integer")
     */
    private $size;

    /**
     * @var \DateTimeImmutable
     * @ORM\Column(type="datetime_immutable", options={"default": "CURRENT_TIMESTAMP"})
     */
    private $created;

    /**
     * @var \DateTimeImmutable
     * @ORM\Column(type="datetime_immutable", options={"default": "CURRENT_TIMESTAMP"})
     */
    private $modified;

    public function isImage(): bool
    {
        return in_array($this->mimeType, [
            'image/jpeg',
            'image/png',
            'image/gif',
            'image/svg',
        ]);
    }

    /**
     * @return string
     */
    public function getTitle(): string
    {
        return $this->title;
    }

    /**
     * @param string $title
     * @return FileRead
     */
    public function setTitle(string $title): self
    {
        $this->title = $title;

        return $this;
    }

    /**
     * @return string
     */
    public function getPath(): string
    {
        return $this->path;
    }

    /**
     * @param string $path
     * @return FileRead
     */
    public function setPath(string $path): self
    {
        $this->path = $path;

        return $this;
    }

    /**
     * @return bool
     */
    public function isDeleted(): bool
    {
        return $this->deleted;
    }

    /**
     * @param bool $deleted
     * @return FileRead
     */
    public function setDeleted(bool $deleted): self
    {
        $this->deleted = $deleted;

        return $this;
    }

    /**
     * @return string
     */
    public function getMimeType(): string
    {
        return $this->mimeType;
    }

    /**
     * @param string $mimeType
     * @return FileRead
     */
    public function setMimeType(string $mimeType): self
    {
        $this->mimeType = $mimeType;

        return $this;
    }

    /**
     * @return int
     */
    public function getSize(): int
    {
        return $this->size;
    }

    /**
     * @param int $size
     * @return FileRead
     */
    public function setSize(int $size): self
    {
        $this->size = $size;

        return $this;
    }

    /**
     * @return \DateTimeImmutable
     */
    public function getCreated(): DateTimeImmutable
    {
        return $this->created;
    }

    /**
     * @param \DateTimeImmutable $created
     *
     * @return FileRead
     */
    public function setCreated(DateTimeImmutable $created): self
    {
        $this->created = $created;

        return $this;
    }

    /**
     * @return \DateTimeImmutable
     */
    public function getModified(): DateTimeImmutable
    {
        return $this->modified;
    }

    /**
     * @param \DateTimeImmutable $modified
     *
     * @return FileRead
     */
    public function setModified(DateTimeImmutable $modified): self
    {
        $this->modified = $modified;

        return $this;
    }
}

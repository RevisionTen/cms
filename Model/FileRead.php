<?php

declare(strict_types=1);

namespace RevisionTen\CMS\Model;

use Doctrine\ORM\Mapping as ORM;

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
    /**
     * @var int
     * @ORM\Column(type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue
     */
    private $id;

    /**
     * @var string
     * @ORM\Column(type="string")
     */
    private $uuid;

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
     * @var string
     * @ORM\Column(type="string")
     */
    private $mimeType;

    /**
     * @var int
     * @ORM\Column(type="integer")
     */
    private $size;

    /**
     * @var int
     * @ORM\Column(type="integer")
     */
    private $version;

    /**
     * @var array
     * @ORM\Column(type="json")
     */
    private $payload;

    /**
     * @var string
     * @ORM\Column(type="string", nullable=true)
     */
    private $language;

    /**
     * @var Website
     * @ORM\ManyToOne(targetEntity="Website")
     * @ORM\JoinColumn(nullable=true)
     */
    private $website;

    /**
     * @return int
     */
    public function getId(): int
    {
        return $this->id;
    }

    /**
     * @return string
     */
    public function getUuid(): string
    {
        return $this->uuid;
    }

    /**
     * @param string $uuid
     *
     * @return FileRead
     */
    public function setUuid($uuid): self
    {
        $this->uuid = $uuid;

        return $this;
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
     * @return int
     */
    public function getVersion(): int
    {
        return $this->version;
    }

    /**
     * @param int $version
     *
     * @return FileRead
     */
    public function setVersion($version): self
    {
        $this->version = $version;

        return $this;
    }

    /**
     * @return array
     */
    public function getPayload(): array
    {
        return $this->payload;
    }

    /**
     * @param array $payload
     *
     * @return FileRead
     */
    public function setPayload($payload): self
    {
        $this->payload = $payload;

        return $this;
    }

    /**
     * @return string
     */
    public function getLanguage(): ?string
    {
        return $this->language;
    }

    /**
     * @param string|null $language
     *
     * @return FileRead
     */
    public function setLanguage(string $language = null): self
    {
        $this->language = $language;

        return $this;
    }

    /**
     * @return Website
     */
    public function getWebsite(): ?Website
    {
        return $this->website;
    }

    /**
     * @param Website|null $website
     *
     * @return FileRead
     */
    public function setWebsite(Website $website = null): self
    {
        $this->website = $website;

        return $this;
    }
}

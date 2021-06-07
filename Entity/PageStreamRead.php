<?php

declare(strict_types=1);

namespace RevisionTen\CMS\Entity;

use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping as ORM;
use function is_string;
use function json_decode;
use function json_encode;

/**
 * Class PageRead.
 *
 * This entity is a representation of the page aggregate as it exists in the event stream.
 * The purpose of this class is to make the aggregate accessible to EasyAdmin.
 *
 * @ORM\Entity(repositoryClass="RevisionTen\CMS\Repository\PageStreamReadRepository")
 * @ORM\Table(name="page_stream_read", uniqueConstraints={
 *      @ORM\UniqueConstraint(name="unique_page",
 *          columns={"uuid"})
 * })
 */
class PageStreamRead
{
    /**
     * @ORM\Column(type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue
     */
    private ?int $id = null;

    /**
     * @ORM\Column(type="string", options={"collation": "utf8_unicode_ci"})
     */
    private ?string $uuid = null;

    /**
     * @ORM\Column(type="integer")
     */
    private ?int $version = null;

    /**
     * @var string
     * @ORM\Column(type="text")
     */
    private $payload;

    /**
     * @ORM\Column(type="string")
     */
    private ?string $title = null;

    /**
     * @ORM\Column(type="string")
     */
    private ?string $language = null;

    /**
     * @ORM\Column(type="string")
     */
    private ?string $template = null;

    /**
     * @var \DateTimeImmutable
     * @ORM\Column(type="datetime_immutable")
     */
    private $created;

    /**
     * @var \DateTimeImmutable
     * @ORM\Column(type="datetime_immutable")
     */
    private $modified;

    /**
     * @ORM\Column(type="boolean")
     */
    private bool $published = false;

    /**
     * @ORM\Column(type="boolean", nullable=true)
     */
    private ?bool $deleted = null;

    /**
     * @var bool
     * @ORM\Column(type="boolean", options={"default": 0})
     */
    private bool $locked = false;

    /**
     * @ORM\Column(type="string", nullable=true)
     */
    private ?string $state = null;

    /**
     * @ORM\Column(type="integer")
     */
    private ?int $website = null;

    /**
     * @var \RevisionTen\CMS\Entity\Alias[]
     * @ORM\OneToMany(targetEntity="RevisionTen\CMS\Entity\Alias", mappedBy="pageStreamRead")
     */
    private $aliases;

    public function __construct()
    {
        $this->aliases = new ArrayCollection();
    }

    /**
     * @return string
     */
    public function __toString(): string
    {
        return $this->getTitle();
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getUuid(): string
    {
        return $this->uuid;
    }

    public function setUuid(?string $uuid): self
    {
        $this->uuid = $uuid;

        return $this;
    }

    public function getVersion(): int
    {
        return $this->version;
    }

    public function setVersion(?int $version): self
    {
        $this->version = $version;

        return $this;
    }

    public function getPayload(): array
    {
        return is_string($this->payload) ? json_decode($this->payload, true) : $this->payload;
    }

    /**
     * @param array $payload
     *
     * @return self
     */
    public function setPayload($payload): self
    {
        $this->payload = json_encode($payload);

        return $this;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): self
    {
        $this->title = $title;

        return $this;
    }

    public function getLanguage(): string
    {
        return $this->language;
    }

    public function setLanguage(string $language): self
    {
        $this->language = $language;

        return $this;
    }

    public function getTemplate(): string
    {
        return $this->template;
    }

    public function setTemplate(string $template): self
    {
        $this->template = $template;

        return $this;
    }

    public function getCreated(): DateTimeImmutable
    {
        return $this->created;
    }

    public function setCreated(DateTimeImmutable $created): self
    {
        $this->created = $created;

        return $this;
    }

    public function getModified(): DateTimeImmutable
    {
        return $this->modified;
    }

    public function setModified(DateTimeImmutable $modified): self
    {
        $this->modified = $modified;

        return $this;
    }

    public function isPublished(): bool
    {
        return $this->published;
    }

    public function setPublished(bool $published): self
    {
        $this->published = $published;

        return $this;
    }

    public function getDeleted(): bool
    {
        return $this->deleted;
    }

    public function setDeleted(bool $deleted): self
    {
        $this->deleted = $deleted;

        return $this;
    }

    public function isLocked(): bool
    {
        return $this->locked;
    }

    public function setLocked(bool $locked): self
    {
        $this->locked = $locked;

        return $this;
    }

    public function getState(): ?string
    {
        return $this->state;
    }

    public function setState(?string $state): self
    {
        $this->state = $state;

        return $this;
    }

    public function getWebsite(): int
    {
        return $this->website;
    }

    public function setWebsite(int $website): self
    {
        $this->website = $website;

        return $this;
    }

    public function getAliases()
    {
        return $this->aliases;
    }
}

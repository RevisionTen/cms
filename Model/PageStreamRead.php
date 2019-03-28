<?php

declare(strict_types=1);

namespace RevisionTen\CMS\Model;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping as ORM;

/**
 * Class PageRead.
 *
 * This entity is a representation of the page aggregate as it exists in the event stream.
 * The purpose of this class is to make the aggregate accessible to EasyAdmin.
 *
 * @ORM\Entity
 * @ORM\Table(name="page_stream_read", uniqueConstraints={
 *      @ORM\UniqueConstraint(name="unique_page",
 *          columns={"uuid"})
 * })
 */
class PageStreamRead
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
     * @ORM\Column(type="string", options={"collation": "utf8_unicode_ci"})
     */
    private $uuid;

    /**
     * @var int
     * @ORM\Column(type="integer")
     */
    private $version;

    /**
     * @var array
     * @ORM\Column(type="text")
     */
    private $payload;

    /**
     * @var string
     * @ORM\Column(type="string")
     */
    private $title;

    /**
     * @var string
     * @ORM\Column(type="string")
     */
    private $language;

    /**
     * @var string
     * @ORM\Column(type="string")
     */
    private $template;

    /**
     * @var \DateTimeImmutable
     * @ORM\Column(type="datetime")
     */
    private $created;

    /**
     * @var \DateTimeImmutable
     * @ORM\Column(type="datetime")
     */
    private $modified;

    /**
     * @var bool
     * @ORM\Column(type="boolean")
     */
    private $published;

    /**
     * @var bool
     * @ORM\Column(type="boolean", nullable=true)
     */
    private $deleted;

    /**
     * @var int
     * @ORM\Column(type="integer")
     */
    private $website;

    /**
     * @var Alias[]
     * @ORM\OneToMany(targetEntity="Alias", mappedBy="pageStreamRead")
     */
    private $aliases;

    /**
     * PageStreamRead constructor.
     */
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
     * @return PageStreamRead
     */
    public function setUuid($uuid): self
    {
        $this->uuid = $uuid;

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
     * @return PageStreamRead
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
        return \is_string($this->payload) ? json_decode($this->payload, true) : $this->payload;
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

    /**
     * @return string
     */
    public function getTitle(): string
    {
        return $this->title;
    }

    /**
     * @param string $title
     *
     * @return PageStreamRead
     */
    public function setTitle(string $title): self
    {
        $this->title = $title;

        return $this;
    }

    /**
     * @return string
     */
    public function getLanguage(): string
    {
        return $this->language;
    }

    /**
     * @param string $language
     *
     * @return PageStreamRead
     */
    public function setLanguage(string $language): self
    {
        $this->language = $language;

        return $this;
    }

    /**
     * @return string
     */
    public function getTemplate(): string
    {
        return $this->template;
    }

    /**
     * @param string $template
     *
     * @return PageStreamRead
     */
    public function setTemplate(string $template): self
    {
        $this->template = $template;

        return $this;
    }

    /**
     * @return \DateTimeImmutable
     */
    public function getCreated(): \DateTimeImmutable
    {
        if ($this->created instanceof \DateTime) {
            $this->created = \DateTimeImmutable::createFromMutable($this->created);
        }
        return $this->created;
    }

    /**
     * @param \DateTimeImmutable $created
     *
     * @return PageStreamRead
     */
    public function setCreated(\DateTimeImmutable $created): self
    {
        $this->created = $created;

        return $this;
    }

    /**
     * @return \DateTimeImmutable
     */
    public function getModified(): \DateTimeImmutable
    {
        if ($this->modified instanceof \DateTime) {
            $this->modified = \DateTimeImmutable::createFromMutable($this->modified);
        }
        return $this->modified;
    }

    /**
     * @param \DateTimeImmutable $modified
     *
     * @return PageStreamRead
     */
    public function setModified(\DateTimeImmutable $modified): self
    {
        $this->modified = $modified;

        return $this;
    }

    /**
     * @return bool
     */
    public function isPublished(): bool
    {
        return $this->published;
    }

    /**
     * @param bool $published
     *
     * @return PageStreamRead
     */
    public function setPublished(bool $published): self
    {
        $this->published = $published;

        return $this;
    }

    /**
     * @return bool
     */
    public function getDeleted(): bool
    {
        return $this->deleted;
    }

    /**
     * @param bool $deleted
     *
     * @return PageStreamRead
     */
    public function setDeleted(bool $deleted): self
    {
        $this->deleted = $deleted;

        return $this;
    }

    /**
     * @return int
     */
    public function getWebsite(): int
    {
        return $this->website;
    }

    /**
     * @param int $website
     *
     * @return PageStreamRead
     */
    public function setWebsite(int $website): self
    {
        $this->website = $website;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getAliases()
    {
        return $this->aliases;
    }
}

<?php

declare(strict_types=1);

namespace RevisionTen\CMS\Model;

use Doctrine\ORM\Mapping as ORM;

/**
 * Class PageRead.
 *
 * This entity represents the page as it is visible to a visitor.
 *
 * @ORM\Entity
 * @ORM\Table(name="page_read", uniqueConstraints={
 *      @ORM\UniqueConstraint(name="unique_page",
 *          columns={"uuid"})
 * })
 */
class PageRead
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
     * @var int
     * @ORM\Column(type="integer")
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
     * @return PageRead
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
     * @return PageRead
     */
    public function setVersion($version): self
    {
        $this->version = $version;

        return $this;
    }

    /**
     * TODO: simplify method once https://github.com/doctrine/doctrine2/pull/6988 is fixed.
     *
     * @return array
     */
    public function getPayload(): array
    {
        return \is_string($this->payload) ? json_decode($this->payload, true) : $this->payload;
    }

    /**
     * TODO: simplify method once https://github.com/doctrine/doctrine2/pull/6988 is fixed.
     *
     * @param array $payload
     *
     * @return PageRead
     */
    public function setPayload($payload): self
    {
        $this->payload = json_encode($payload);

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
     * @return PageRead
     */
    public function setWebsite(int $website): self
    {
        $this->website = $website;

        return $this;
    }
}

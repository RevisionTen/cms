<?php

declare(strict_types=1);

namespace RevisionTen\CMS\Model;

use Doctrine\ORM\Mapping as ORM;

/**
 * Class MenuRead.
 *
 * This entity represents the menu as it is visible to a visitor.
 *
 * @ORM\Entity
 * @ORM\Table(name="menu_read", uniqueConstraints={
 *      @ORM\UniqueConstraint(name="unique_menu",
 *          columns={"uuid"})
 * })
 */
class MenuRead
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
     * @var int
     * @ORM\Column(type="integer")
     */
    private $website;

    /**
     * @var string
     * @ORM\Column(type="string")
     */
    private $language;

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
     * @return MenuRead
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
     * @return MenuRead
     */
    public function setTitle(string $title): self
    {
        $this->title = $title;

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
     * @return MenuRead
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
     * @return MenuRead
     */
    public function setPayload($payload): self
    {
        $this->payload = $payload;

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
     * @return MenuRead
     */
    public function setWebsite(int $website): self
    {
        $this->website = $website;

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
     * @return MenuRead
     */
    public function setLanguage(string $language): self
    {
        $this->language = $language;

        return $this;
    }
}

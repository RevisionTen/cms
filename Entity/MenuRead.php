<?php

declare(strict_types=1);

namespace RevisionTen\CMS\Entity;

use Doctrine\ORM\Mapping as ORM;
use RevisionTen\CMS\Traits\ReadModelTrait;

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
    use ReadModelTrait;

    /**
     * @var string
     * @ORM\Column(type="string")
     */
    private $title;

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
     * @return string
     */
    public function getTitle(): string
    {
        return $this->title;
    }

    /**
     * @param string $title
     *
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

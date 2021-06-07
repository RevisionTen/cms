<?php

declare(strict_types=1);

namespace RevisionTen\CMS\Traits;

use Doctrine\ORM\Mapping as ORM;
use RevisionTen\CMS\Entity\Website;

trait LanguageAndWebsiteTrait
{
    /**
     * @var string
     * @ORM\Column(type="string", nullable=true)
     */
    private $language;

    /**
     * @var Website
     * @ORM\ManyToOne(targetEntity="RevisionTen\CMS\Entity\Website")
     * @ORM\JoinColumn(nullable=true)
     */
    private $website;

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
     * @return self
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
     * @return self
     */
    public function setWebsite(Website $website = null): self
    {
        $this->website = $website;

        return $this;
    }
}

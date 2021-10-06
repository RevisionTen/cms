<?php

declare(strict_types=1);

namespace RevisionTen\CMS\Traits;

use Doctrine\ORM\Mapping as ORM;
use RevisionTen\CMS\Entity\Website;

trait LanguageAndWebsiteTrait
{
    /**
     * @ORM\Column(type="string", nullable=true)
     */
    private ?string $language = null;

    /**
     * @ORM\ManyToOne(targetEntity="RevisionTen\CMS\Entity\Website")
     * @ORM\JoinColumn(nullable=true)
     */
    private ?Website $website = null;

    public function getLanguage(): ?string
    {
        return $this->language;
    }

    public function setLanguage(string $language = null): self
    {
        $this->language = $language;

        return $this;
    }

    public function getWebsite(): ?Website
    {
        return $this->website;
    }

    public function setWebsite(Website $website = null): self
    {
        $this->website = $website;

        return $this;
    }
}

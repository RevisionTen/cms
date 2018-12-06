<?php

declare(strict_types=1);

namespace RevisionTen\CMS\Model;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * Class Site.
 *
 * @ORM\Entity
 * @ORM\Table(name="website")
 */
class Site
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
    private $title;

    /**
     * @var string
     * @ORM\Column(type="string", nullable=true)
     */
    private $siteVerification;

    /**
     * @var string
     * @ORM\Column(type="string", options={"default":"de"})
     */
    private $defaultLanguage = 'de';

    /**
     * @var Domain[]
     * @ORM\OneToMany(targetEntity="Domain", mappedBy="website", cascade={"persist"}, orphanRemoval=true)
     */
    private $domains;

    /**
     * @var Collection
     * @ORM\OneToMany(targetEntity="Alias", mappedBy="website")
     */
    private $aliases;

    /**
     * Site constructor.
     */
    public function __construct()
    {
        $this->title = '';
        $this->domains = new ArrayCollection();
    }

    /**
     * @return string
     */
    public function __toString(): string
    {
        return $this->title;
    }

    /**
     * @return int
     */
    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     * @param int $id
     *
     * @return Site
     */
    public function setId(int $id): self
    {
        $this->id = $id;

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
     * @return Site
     */
    public function setTitle(string $title): self
    {
        $this->title = $title;

        return $this;
    }

    /**
     * @return string|null
     */
    public function getSiteVerification(): ?string
    {
        return $this->siteVerification;
    }

    /**
     * @param string|null $siteVerification
     *
     * @return Site
     */
    public function setSiteVerification(string $siteVerification = null): self
    {
        $this->siteVerification = $siteVerification;

        return $this;
    }

    /**
     * @return string
     */
    public function getDefaultLanguage(): string
    {
        return $this->defaultLanguage;
    }

    /**
     * @param string $defaultLanguage
     *
     * @return Site
     */
    public function setDefaultLanguage(string $defaultLanguage): self
    {
        $this->defaultLanguage = $defaultLanguage;

        return $this;
    }

    /**
     * @return Collection
     */
    public function getDomains(): Collection
    {
        return $this->domains;
    }

    /**
     * @param Collection $domains
     *
     * @return Site
     */
    public function setDomains(Collection $domains): self
    {
        $this->domains = $domains;

        foreach ($this->domains as $domain) {
            $domain->setWebsite($this);
        }

        return $this;
    }

    /**
     * @param Domain $domain
     *
     * @return Site
     */
    public function removeDomain(Domain $domain): self
    {
        $domain->setWebsite(null);
        $this->domains->removeElement($domain);

        return $this;
    }

    /**
     * @param Domain $domain
     *
     * @return Site
     */
    public function addDomain(Domain $domain): self
    {
        $domain->setWebsite($this);

        if (!$this->domains->contains($domain)) {
            $this->domains->add($domain);
        }

        return $this;
    }

    /**
     * @return Collection
     */
    public function getAliases(): ?Collection
    {
        return $this->aliases;
    }
}

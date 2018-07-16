<?php

declare(strict_types=1);

namespace RevisionTen\CMS\Model;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * Class Website.
 *
 * @ORM\Entity
 * @ORM\Table(name="website")
 */
class Website
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
     * @var Domain[]
     * @ORM\OneToMany(targetEntity="Domain", mappedBy="website", cascade={"persist"}, orphanRemoval=true)
     */
    private $domains;

    /**
     * Website constructor.
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
     * @return Website
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
     * @return Website
     */
    public function setTitle(string $title): self
    {
        $this->title = $title;

        return $this;
    }

    /**
     * @return Domain[]
     */
    public function getDomains(): Collection
    {
        return $this->domains;
    }

    /**
     * @param Domain[] $domains
     *
     * @return Website
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
     * @return Website
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
     * @return Website
     */
    public function addDomain(Domain $domain): self
    {
        $domain->setWebsite($this);

        if (!$this->domains->contains($domain)) {
            $this->domains->add($domain);
        }

        return $this;
    }
}

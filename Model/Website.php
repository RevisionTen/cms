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
     * @var Alias[]
     * @ORM\OneToMany(targetEntity="Alias", mappedBy="website")
     */
    private $aliases;

    /**
     * @var UserRead[]
     * @ORM\ManyToMany(targetEntity="UserRead", mappedBy="websites")
     */
    private $users;

    /**
     * @var PageStreamRead[]
     *
     * @ORM\ManyToMany(targetEntity="PageStreamRead")
     * @ORM\JoinTable(name="website_errorpages",
     *      joinColumns={@ORM\JoinColumn(name="website_id", referencedColumnName="id")},
     *      inverseJoinColumns={@ORM\JoinColumn(name="page_id", referencedColumnName="id", unique=true)}
     * )
     */
    private $errorPages;

    /**
     * Website constructor.
     */
    public function __construct()
    {
        $this->title = '';
        $this->domains = new ArrayCollection();
        $this->aliases = new ArrayCollection();
        $this->users = new ArrayCollection();
        $this->errorPages = new ArrayCollection();
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
     * @return string|null
     */
    public function getSiteVerification(): ?string
    {
        return $this->siteVerification;
    }

    /**
     * @param string|null $siteVerification
     *
     * @return Website
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
     * @return Website
     */
    public function setDefaultLanguage(string $defaultLanguage): self
    {
        $this->defaultLanguage = $defaultLanguage;

        return $this;
    }

    /**
     * @return ArrayCollection|Domain[]
     */
    public function getDomains()
    {
        return $this->domains;
    }

    /**
     * @param ArrayCollection|Domain[] $domains
     *
     * @return Website
     */
    public function setDomains($domains): self
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

    /**
     * @return ArrayCollection|Alias[]
     */
    public function getAliases()
    {
        return $this->aliases;
    }

    /**
     * @return ArrayCollection|UserRead[]
     */
    public function getUsers()
    {
        return $this->users;
    }

    public function addAlias(Alias $alias): self
    {
        if (!$this->aliases->contains($alias)) {
            $this->aliases[] = $alias;
            $alias->setWebsite($this);
        }

        return $this;
    }

    public function removeAlias(Alias $alias): self
    {
        if ($this->aliases->contains($alias)) {
            $this->aliases->removeElement($alias);
            // set the owning side to null (unless already changed)
            if ($alias->getWebsite() === $this) {
                $alias->setWebsite(null);
            }
        }

        return $this;
    }

    public function addUser(UserRead $user): self
    {
        if (!$this->users->contains($user)) {
            $this->users[] = $user;
            $user->addWebsite($this);
        }

        return $this;
    }

    public function removeUser(UserRead $user): self
    {
        if ($this->users->contains($user)) {
            $this->users->removeElement($user);
            $user->removeWebsite($this);
        }

        return $this;
    }

    /**
     * @return Collection|PageStreamRead[]
     */
    public function getErrorPages(): Collection
    {
        return $this->errorPages;
    }

    public function addErrorPage(PageStreamRead $errorPage): self
    {
        if (!$this->errorPages->contains($errorPage)) {
            $this->errorPages[] = $errorPage;
        }

        return $this;
    }

    public function removeErrorPage(PageStreamRead $errorPage): self
    {
        if ($this->errorPages->contains($errorPage)) {
            $this->errorPages->removeElement($errorPage);
        }

        return $this;
    }
}

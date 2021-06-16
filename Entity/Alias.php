<?php

declare(strict_types=1);

namespace RevisionTen\CMS\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Class Alias.
 *
 * @ORM\Entity(repositoryClass="RevisionTen\CMS\Repository\AliasRepository")
 * @ORM\Table(name="alias")
 */
class Alias
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
     * @Assert\NotNull()
     * @ORM\Column(type="string")
     */
    private $path;

    /**
     * @var \RevisionTen\CMS\Entity\PageStreamRead
     * @ORM\ManyToOne(targetEntity="RevisionTen\CMS\Entity\PageStreamRead", inversedBy="aliases")
     */
    private $pageStreamRead;

    /**
     * @var string
     * @ORM\Column(type="string", nullable=true, options={"collation": "utf8_unicode_ci"})
     */
    private $controller;

    /**
     * @ORM\Column(type="string", nullable=true)
     */
    private ?string $redirect;

    /**
     * @ORM\Column(type="integer", options={"default":301})
     */
    private ?int $redirectCode = 301;

    /**
     * @ORM\Column(type="float", options={"default":0.5})
     */
    private ?float $priority = 0.5;

    /**
     * @var array
     * @ORM\Column(type="text", nullable=true)
     */
    private $meta;

    /**
     * @var string
     * @ORM\Column(type="string", nullable=true)
     */
    private $language;

    /**
     * @var Website
     * @ORM\ManyToOne(targetEntity="Website", inversedBy="aliases")
     * @ORM\JoinColumn(nullable=true)
     */
    private $website;

    /**
     * @var bool
     * @ORM\Column(type="boolean", options={"default":1})
     */
    private $enabled = true;

    /**
     * @return string
     */
    public function __toString(): string
    {
        return $this->path;
    }

    public function getHost(?string $port = ''): ?string
    {
        if (null !== $this->getWebsite() && 0 !== \count($this->getWebsite()->getDomains())) {
            // Append locale prefix if it differs from the websites default language.
            $locale = '';
            if ($this->getWebsite()->getDefaultLanguage() !== $this->getLanguage()) {
                $locale = '/'.$this->getLanguage();
            }
            /** @var Domain $domain */
            $domain = $this->getWebsite()->getDomains()->first();

            return $domain->getDomain().$port.$locale;
        }

        return null;
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
     * @return Alias
     */
    public function setId(int $id): self
    {
        $this->id = $id;

        return $this;
    }

    /**
     * @return string
     */
    public function getPath(): ?string
    {
        return $this->path;
    }

    /**
     * @param string $path
     *
     * @return Alias
     */
    public function setPath(string $path): self
    {
        if (0 !== strpos($path, '/')) {
            $path = '/'.$path;
        }

        $this->path = $path;

        return $this;
    }

    public function getRedirect(): ?string
    {
        return $this->redirect;
    }

    public function setRedirect(?string $redirect): Alias
    {
        $this->redirect = $redirect;

        return $this;
    }

    public function getRedirectCode(): ?int
    {
        return $this->redirectCode;
    }

    public function setRedirectCode(?int $redirectCode): Alias
    {
        $this->redirectCode = $redirectCode;

        return $this;
    }

    /**
     * @return PageStreamRead
     */
    public function getPageStreamRead(): ?PageStreamRead
    {
        return $this->pageStreamRead;
    }

    /**
     * @param PageStreamRead|null $pageStreamRead
     *
     * @return Alias
     */
    public function setPageStreamRead(PageStreamRead $pageStreamRead = null): self
    {
        $this->pageStreamRead = $pageStreamRead;

        return $this;
    }

    /**
     * @return string
     */
    public function getController(): ?string
    {
        return $this->controller;
    }

    /**
     * @param string|null $controller
     *
     * @return Alias
     */
    public function setController(string $controller = null): self
    {
        $this->controller = $controller;

        return $this;
    }

    /**
     * @return float
     */
    public function getPriority(): float
    {
        return $this->priority;
    }

    /**
     * @param float $priority
     *
     * @return Alias
     */
    public function setPriority(float $priority): self
    {
        $this->priority = $priority;

        return $this;
    }

    /**
     * @return array|null
     */
    public function getMeta(): ?array
    {
        return \is_string($this->meta) ? json_decode($this->meta, true) : $this->meta;
    }

    /**
     * @param array|null $meta
     *
     * @return Alias
     */
    public function setMeta(array $meta = null): self
    {
        $this->meta = json_encode($meta);

        return $this;
    }

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
     * @return Alias
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
     * @return Alias
     */
    public function setWebsite(Website $website = null): self
    {
        $this->website = $website;

        return $this;
    }

    /**
     * @return bool
     */
    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    /**
     * @param bool $enabled
     *
     * @return Alias
     */
    public function setEnabled(bool $enabled): self
    {
        $this->enabled = $enabled;

        return $this;
    }
}

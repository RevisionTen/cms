<?php

declare(strict_types=1);

namespace RevisionTen\CMS\Model;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Class Domain.
 *
 * @ORM\Entity
 * @ORM\Table(name="domain")
 */
class Domain
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
     * @Assert\NotBlank()
     * @ORM\Column(type="string")
     */
    private $domain;

    /**
     * @var Site
     * @ORM\ManyToOne(targetEntity="Site", inversedBy="domains")
     * @ORM\JoinColumn(onDelete="CASCADE")
     */
    private $website;

    /**
     * @return string
     */
    public function __toString(): string
    {
        return $this->domain;
    }

    /**
     * @return int
     */
    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     * @param int|null $id
     *
     * @return Domain
     */
    public function setId(int $id = null): self
    {
        $this->id = $id;

        return $this;
    }

    /**
     * @return string
     */
    public function getDomain(): ?string
    {
        return $this->domain;
    }

    /**
     * @param string $domain
     *
     * @return Domain
     */
    public function setDomain(string $domain = null): self
    {
        $this->domain = $domain;

        return $this;
    }

    /**
     * @return Site
     */
    public function getWebsite(): Site
    {
        return $this->website;
    }

    /**
     * @param Site $website
     *
     * @return Domain
     */
    public function setWebsite(Site $website = null): self
    {
        $this->website = $website;

        return $this;
    }
}

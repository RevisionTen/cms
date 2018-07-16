<?php

declare(strict_types=1);

namespace RevisionTen\CMS\Model;

use Doctrine\ORM\Mapping as ORM;

/**
 * Class Alias.
 *
 * @ORM\Entity
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
     * @ORM\Column(type="string")
     */
    private $path;

    /**
     * @var PageStreamRead
     * @ORM\ManyToOne(targetEntity="PageStreamRead")
     */
    private $pageStreamRead;

    /**
     * @var string
     * @ORM\Column(type="string", nullable=true)
     */
    private $redirect;

    /**
     * @var float
     * @ORM\Column(type="float", options={"default":0.5})
     */
    private $priority;

    /**
     * @return string
     */
    public function __toString(): string
    {
        return $this->path;
    }

    /**
     * Alias constructor.
     */
    public function __construct()
    {
        $this->priority = 0.5;
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
        $this->path = $path;

        return $this;
    }

    /**
     * @return string
     */
    public function getRedirect(): ?string
    {
        return $this->redirect;
    }

    /**
     * @param string|null $redirect
     *
     * @return Alias
     */
    public function setRedirect(string $redirect = null): self
    {
        $this->redirect = $redirect;

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
}

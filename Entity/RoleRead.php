<?php

declare(strict_types=1);

namespace RevisionTen\CMS\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping as ORM;
use RevisionTen\CMS\Traits\ReadModelTrait;

/**
 * Class RoleRead.
 *
 * @ORM\Entity
 * @ORM\Table(name="role_read", uniqueConstraints={
 *      @ORM\UniqueConstraint(name="unique_role",
 *          columns={"uuid"})
 * })
 */
class RoleRead
{
    use ReadModelTrait;

    /**
     * @var string
     * @ORM\Column(type="string")
     */
    private $title;

    /**
     * @var array
     * @ORM\Column(type="array", nullable=true)
     */
    private $permissions;

    /**
     * @var UserRead[]
     * @ORM\ManyToMany(targetEntity="UserRead", mappedBy="roles")
     */
    private $users;

    /**
     * @return string
     */
    public function getTitle(): string
    {
        return $this->title;
    }

    /**
     * @param string $title
     * @return RoleRead
     */
    public function setTitle(string $title): self
    {
        $this->title = $title;

        return $this;
    }

    /**
     * @return array|null
     */
    public function getPermissions(): ?array
    {
        return $this->permissions;
    }

    /**
     * @param array|null $permissions
     * @return RoleRead
     */
    public function setPermissions(?array $permissions): self
    {
        $this->permissions = $permissions;

        return $this;
    }

    /**
     * @return ArrayCollection|UserRead[]
     */
    public function getUsers()
    {
        return $this->users;
    }
}

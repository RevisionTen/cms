<?php

declare(strict_types=1);

namespace RevisionTen\CMS\Model;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping as ORM;

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
    private $uuid;

    /**
     * @var string
     * @ORM\Column(type="string")
     */
    private $title;

    /**
     * @var int
     * @ORM\Column(type="integer")
     */
    private $version;

    /**
     * @var array
     * @ORM\Column(type="json")
     */
    private $payload;

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
     * @return int
     */
    public function getId(): int
    {
        return $this->id;
    }

    /**
     * @return string
     */
    public function getUuid(): string
    {
        return $this->uuid;
    }

    /**
     * @param string $uuid
     *
     * @return RoleRead
     */
    public function setUuid($uuid): self
    {
        $this->uuid = $uuid;

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
     * @return RoleRead
     */
    public function setTitle(string $title): self
    {
        $this->title = $title;

        return $this;
    }

    /**
     * @return int
     */
    public function getVersion(): int
    {
        return $this->version;
    }

    /**
     * @param int $version
     *
     * @return RoleRead
     */
    public function setVersion($version): self
    {
        $this->version = $version;

        return $this;
    }

    /**
     * @return array
     */
    public function getPayload(): array
    {
        return $this->payload;
    }

    /**
     * @param array $payload
     *
     * @return RoleRead
     */
    public function setPayload($payload): self
    {
        $this->payload = $payload;

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

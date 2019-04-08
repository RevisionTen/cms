<?php

declare(strict_types=1);

namespace RevisionTen\CMS\Model;

use Doctrine\Common\Collections\ArrayCollection;
use Symfony\Component\Security\Core\User\UserInterface;
use Doctrine\ORM\Mapping as ORM;

/**
 * UserRead.
 *
 * @ORM\Entity
 * @ORM\Table("user")
 */
class UserRead implements UserInterface, \Serializable
{
    /**
     * @var int
     * @ORM\Column(type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="IDENTITY")
     */
    private $id;

    /**
     * @var string
     * @ORM\Column(type="string", options={"collation": "utf8_unicode_ci"})
     */
    private $uuid;

    /**
     * @var string
     * @ORM\Column(type="string", unique=true, options={"collation": "utf8_unicode_ci"})
     */
    private $username;

    /**
     * @var string
     * @ORM\Column(type="string", unique=true, options={"collation": "utf8_unicode_ci"})
     */
    private $email;

    /**
     * @var string
     * @ORM\Column(type="string", options={"collation": "utf8_unicode_ci"})
     */
    private $password;

    /**
     * @var string
     * @ORM\Column(type="string", options={"collation": "utf8_unicode_ci"})
     */
    private $secret;

    /**
     * @var string
     * @ORM\Column(type="string", nullable=true, options={"collation": "utf8_unicode_ci"})
     */
    private $resetToken;

    /**
     * @var string
     * @ORM\Column(type="string", nullable=true, options={"collation": "utf8_unicode_ci"})
     */
    private $color;

    /**
     * @var string
     * @ORM\Column(type="string", nullable=true, options={"collation": "utf8_unicode_ci"})
     */
    private $avatarUrl;

    /**
     * @var int
     * @ORM\Column(type="integer", options={"default" : 0})
     */
    private $version;

    /**
     * @var array
     * @ORM\Column(type="array", nullable=true, options={"collation": "utf8_unicode_ci"})
     */
    private $devices;

    /**
     * @var array
     * @ORM\Column(type="array", nullable=true, options={"collation": "utf8_unicode_ci"})
     */
    private $ips;

    /**
     * @var Website[]
     * @ORM\ManyToMany(targetEntity="Website", inversedBy="users")
     * @ORM\JoinTable(name="users_websites")
     */
    private $websites;

    /**
     * @var RoleRead[]
     * @ORM\ManyToMany(targetEntity="RoleRead", inversedBy="users")
     * @ORM\JoinTable(name="users_roles")
     */
    private $roles;

    /**
     * @var array|null
     * @ORM\Column(type="text", nullable=true, options={"collation": "utf8_unicode_ci"})
     */
    private $extra;

    /**
     * @var bool
     */
    private $imposter = false;

    public function __construct()
    {
        $this->websites = new ArrayCollection();
        $this->roles = new ArrayCollection();
    }

    public function __toString()
    {
        return (string) $this->getUsername();
    }

    public function serializeToSolrArray(): array
    {
        return [
            'username_s' => $this->getUsername(),
            'avatarurl_s' => $this->getAvatarUrl(),
            'color_s' => $this->getColor(),
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function getSalt()
    {
    }

    /**
     * {@inheritdoc}
     */
    public function eraseCredentials()
    {
    }

    /**
     * @see \Serializable::serialize()
     */
    public function serialize()
    {
        return serialize(array(
            $this->id,
            $this->username,
            $this->password,
        ));
    }

    /**
     * @see \Serializable::unserialize()
     *
     * @param string $serialized
     */
    public function unserialize($serialized)
    {
        list(
            $this->id,
            $this->username,
            $this->password) = unserialize($serialized, [
                'allowed_classes' => false,
        ]);
    }

    /**
     * @return int
     */
    public function getId(): ?int
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
     * @return UserRead
     */
    public function setUuid(string $uuid): UserRead
    {
        $this->uuid = $uuid;

        return $this;
    }

    /**
     * @return string
     */
    public function getUsername(): ?string
    {
        return $this->username;
    }

    /**
     * @param string $username
     *
     * @return UserRead
     */
    public function setUsername(string $username): self
    {
        $this->username = $username;

        return $this;
    }

    /**
     * @return string
     */
    public function getEmail(): ?string
    {
        return $this->email;
    }

    /**
     * @param string $email
     *
     * @return UserRead
     */
    public function setEmail(string $email): self
    {
        $this->email = $email;

        return $this;
    }

    /**
     * @return string
     */
    public function getPassword(): ?string
    {
        return $this->password;
    }

    /**
     * @param string $password
     *
     * @return UserRead
     */
    public function setPassword(string $password): self
    {
        $this->password = $password;

        return $this;
    }

    /**
     * @return string
     */
    public function getSecret(): ?string
    {
        return $this->secret;
    }

    /**
     * @param string $secret
     *
     * @return UserRead
     */
    public function setSecret(string $secret): self
    {
        $this->secret = $secret;

        return $this;
    }

    /**
     * @return string|null
     */
    public function getResetToken(): ?string
    {
        return $this->resetToken;
    }

    /**
     * @param string|null $resetToken
     *
     * @return UserRead
     */
    public function setResetToken(string $resetToken = null): self
    {
        $this->resetToken = $resetToken;

        return $this;
    }

    /**
     * @return string
     */
    public function getColor(): string
    {
        if (null === $this->color) {
            $this->color = $this->getColorFromUsername();
        }

        return $this->color;
    }

    /**
     * @param string|null $color
     *
     * @return UserRead
     */
    public function setColor(string $color = null): self
    {
        $this->color = $color;

        return $this;
    }

    /**
     * @return string|null
     */
    public function getAvatarUrl(): ?string
    {
        return $this->avatarUrl;
    }

    /**
     * @param string|null $avatarUrl
     *
     * @return UserRead
     */
    public function setAvatarUrl(string $avatarUrl = null): self
    {
        $this->color = $this->getColorFromUsername();
        $this->avatarUrl = $avatarUrl;

        return $this;
    }

    public function getColorFromUsername(): string
    {
        $hue = $this->hue($this->getUsername().$this->getEmail());

        return substr($this->hsl2rgb($hue / 0xFFFFFFFF, 1, 0.9), 0, 7);
    }

    private function hsl2rgb($H, float $strength, float $saturation): string
    {
        $H *= 6;
        $h = (int) $H;
        $H -= $h;
        $saturation *= 255;
        $m = $saturation * (1 - $strength);
        $x = $saturation * (1 - $strength * (1 - $H));
        $y = $saturation * (1 - $strength * $H);
        $a = [[$saturation, $x, $m], [$y, $saturation, $m],
            [$m, $saturation, $x], [$m, $y, $saturation],
            [$x, $m, $saturation], [$saturation, $m, $y], ][$h];

        return sprintf('#%02X%02X%02X', $a[0], $a[1], $a[2]);
    }

    private function hue(string $string)
    {
        return unpack('L', hash('adler32', $string, true))[1];
    }

    /**
     * @return bool
     */
    public function isImposter(): bool
    {
        return $this->imposter;
    }

    /**
     * @param bool $imposter
     *
     * @return UserRead
     */
    public function setImposter(bool $imposter): self
    {
        $this->imposter = $imposter;

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
     * @return UserRead
     */
    public function setVersion(int $version): self
    {
        $this->version = $version;

        return $this;
    }

    /**
     * @return array|null
     */
    public function getDevices(): ?array
    {
        return $this->devices;
    }

    /**
     * @param array|null $devices
     *
     * @return UserRead
     */
    public function setDevices(array $devices = null): self
    {
        $this->devices = $devices;

        return $this;
    }

    /**
     * @return array|null
     */
    public function getIps(): ?array
    {
        return $this->ips;
    }

    /**
     * @param array|null $ips
     *
     * @return UserRead
     */
    public function setIps(array $ips = null): self
    {
        $this->ips = $ips;

        return $this;
    }

    /**
     * @return ArrayCollection|Website[]
     */
    public function getWebsites()
    {
        return $this->websites;
    }

    /**
     * @param null|ArrayCollection|Website[] $websites
     *
     * @return UserRead
     */
    public function setWebsites($websites = null): self
    {
        $this->websites = $websites;

        return $this;
    }

    /**
     * @param Website $website
     * @return UserRead
     */
    public function addWebsite(Website $website): self
    {
        if (!$this->websites->contains($website)) {
            $this->websites[] = $website;
        }

        return $this;
    }

    /**
     * @param Website $website
     * @return UserRead
     */
    public function removeWebsite(Website $website): self
    {
        if ($this->websites->contains($website)) {
            $this->websites->removeElement($website);
        }

        return $this;
    }

    /**
     * @return array]
     */
    public function getRoles()
    {
        $roles = ['ROLE_USER'];

        foreach ($this->roles as $role) {
            $roles[] = 'ROLE_'.strtoupper($role->getTitle());
        }

        return $roles;
    }

    /**
     * @return ArrayCollection|RoleRead[]
     */
    public function getRoleEntities()
    {
        return $this->roles;
    }

    /**
     * Return an array of the users role titles.
     *
     * @return array
     */
    public function getRoleTitles(): array
    {
        $roles = [];
        foreach ($this->roles as $role) {
            $roles[] = $role->getTitle();
        }

        return $roles;
    }

    /**
     * @param null|ArrayCollection|RoleRead[] $roles
     *
     * @return UserRead
     */
    public function setRoles($roles = null): self
    {
        $this->roles = $roles;

        return $this;
    }

    /**
     * @param RoleRead $role
     * @return UserRead
     */
    public function addRoles(RoleRead $role): self
    {
        if (!$this->roles->contains($role)) {
            $this->roles[] = $role;
        }

        return $this;
    }

    /**
     * @param RoleRead $role
     * @return UserRead
     */
    public function removeRoles(RoleRead $role): self
    {
        if ($this->roles->contains($role)) {
            $this->roles->removeElement($role);
        }

        return $this;
    }

    /**
     * @return array|null
     */
    public function getExtra(): ?array
    {
        return \is_string($this->extra) ? json_decode($this->extra, true) : $this->extra;
    }

    /**
     * @param array|null $extra
     *
     * @return UserRead
     */
    public function setExtra(array $extra = null): self
    {
        $this->extra = is_array($extra) ? json_encode($extra) : null;

        return $this;
    }
}

\class_alias(UserRead::class, 'RevisionTen\CMS\Model\User');

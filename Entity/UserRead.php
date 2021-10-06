<?php

declare(strict_types=1);

namespace RevisionTen\CMS\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Serializable;
use Symfony\Component\Security\Core\User\UserInterface;
use Doctrine\ORM\Mapping as ORM;
use function hash;
use function is_array;
use function is_string;
use function json_decode;
use function json_encode;
use function serialize;
use function sprintf;
use function strtoupper;
use function substr;
use function unpack;
use function unserialize;

/**
 * @ORM\Entity
 * @ORM\Table("user")
 */
class UserRead implements UserInterface, Serializable
{
    /**
     * @ORM\Column(type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="IDENTITY")
     */
    private ?int $id = null;

    /**
     * @ORM\Column(type="string", options={"collation": "utf8_unicode_ci"})
     */
    private ?string $uuid = null;

    /**
     * @ORM\Column(type="string", unique=true, options={"collation": "utf8_unicode_ci"})
     */
    private ?string $username = null;

    /**
     * @ORM\Column(type="string", unique=true, options={"collation": "utf8_unicode_ci"})
     */
    private ?string $email = null;

    /**
     * @ORM\Column(type="string", options={"collation": "utf8_unicode_ci"})
     */
    private ?string $password = null;

    /**
     * @ORM\Column(type="string", options={"collation": "utf8_unicode_ci"})
     */
    private ?string $secret = null;

    /**
     * @ORM\Column(type="string", nullable=true, options={"collation": "utf8_unicode_ci"})
     */
    private ?string $resetToken = null;

    /**
     * @ORM\Column(type="string", nullable=true, options={"collation": "utf8_unicode_ci"})
     */
    private ?string $color = null;

    /**
     * @ORM\Column(type="string", nullable=true, options={"collation": "utf8_unicode_ci"})
     */
    private ?string $avatarUrl = null;

    /**
     * @ORM\Column(type="string", nullable=true, options={"collation": "utf8_unicode_ci"})
     */
    private ?string $theme = null;

    /**
     * @var int
     * @ORM\Column(type="integer", options={"default" : 0})
     */
    private int $version = 0;

    /**
     * @ORM\Column(type="array", nullable=true, options={"collation": "utf8_unicode_ci"})
     */
    private ?array $devices = null;

    /**
     * @ORM\Column(type="array", nullable=true, options={"collation": "utf8_unicode_ci"})
     */
    private ?array $ips = null;

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
     * @ORM\Column(type="text", nullable=true, options={"collation": "utf8_unicode_ci"})
     */
    private ?string $extra = null;

    private bool $imposter = false;

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

    public function getUserIdentifier(): string
    {
        return $this->getUsername();
    }

    public function getSalt()
    {
    }

    public function eraseCredentials(): void
    {
    }

    public function getRoles(): array
    {
        $roles = ['ROLE_USER'];

        foreach ($this->roles as $role) {
            $roles[] = 'ROLE_'. strtoupper($role->getTitle());
        }

        return $roles;
    }

    /**
     * @see Serializable::serialize()
     */
    public function serialize(): ?string
    {
        return serialize([
            $this->id,
            $this->username,
            $this->password,
        ]);
    }

    /**
     * @see Serializable::unserialize()
     *
     * @param string $data
     */
    public function unserialize($data): void
    {
        [
            $this->id,
            $this->username,
            $this->password] = unserialize($data, [
                'allowed_classes' => false,
        ]);
    }

    public function setId(?int $id): self
    {
        $this->id = $id;

        return $this;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUuid(): string
    {
        return $this->uuid;
    }

    public function setUuid(string $uuid): self
    {
        $this->uuid = $uuid;

        return $this;
    }

    public function getUsername(): ?string
    {
        return $this->username;
    }

    public function setUsername(string $username): self
    {
        $this->username = $username;

        return $this;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): self
    {
        $this->email = $email;

        return $this;
    }

    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function setPassword(string $password): self
    {
        $this->password = $password;

        return $this;
    }

    public function getSecret(): ?string
    {
        return $this->secret;
    }

    public function setSecret(string $secret): self
    {
        $this->secret = $secret;

        return $this;
    }

    public function getResetToken(): ?string
    {
        return $this->resetToken;
    }

    public function setResetToken(string $resetToken = null): self
    {
        $this->resetToken = $resetToken;

        return $this;
    }

    public function getColor(): string
    {
        if (null === $this->color) {
            $this->color = $this->getColorFromUsername();
        }

        return $this->color;
    }

    public function setColor(string $color = null): self
    {
        $this->color = $color;

        return $this;
    }

    public function getAvatarUrl(): ?string
    {
        return $this->avatarUrl;
    }

    public function setAvatarUrl(string $avatarUrl = null): self
    {
        $this->color = $this->getColorFromUsername();
        $this->avatarUrl = $avatarUrl;

        return $this;
    }

    public function getTheme(): ?string
    {
        return $this->theme;
    }

    public function setTheme(?string $theme): self
    {
        $this->theme = $theme;

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

    public function isImposter(): bool
    {
        return $this->imposter;
    }

    public function setImposter(bool $imposter): self
    {
        $this->imposter = $imposter;

        return $this;
    }

    public function getVersion(): int
    {
        return $this->version;
    }

    public function setVersion(int $version): self
    {
        $this->version = $version;

        return $this;
    }

    public function getDevices(): ?array
    {
        return $this->devices;
    }

    public function setDevices(array $devices = null): self
    {
        $this->devices = $devices;

        return $this;
    }

    public function getIps(): ?array
    {
        return $this->ips;
    }

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

    public function addWebsite(Website $website): self
    {
        if (!$this->websites->contains($website)) {
            $this->websites[] = $website;
        }

        return $this;
    }

    public function removeWebsite(Website $website): self
    {
        if ($this->websites->contains($website)) {
            $this->websites->removeElement($website);
        }

        return $this;
    }

    /**
     * @return ArrayCollection|RoleRead[]
     */
    public function getRoleEntities()
    {
        return $this->roles;
    }

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

    public function addRoles(RoleRead $role): self
    {
        if (!$this->roles->contains($role)) {
            $this->roles[] = $role;
        }

        return $this;
    }

    public function removeRoles(RoleRead $role): self
    {
        if ($this->roles->contains($role)) {
            $this->roles->removeElement($role);
        }

        return $this;
    }

    public function getExtra(): ?array
    {
        return is_string($this->extra) ? json_decode($this->extra, true) : $this->extra;
    }

    public function setExtra(array $extra = null): self
    {
        $this->extra = is_array($extra) ? json_encode($extra) : null;

        return $this;
    }
}

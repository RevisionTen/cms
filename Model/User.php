<?php

declare(strict_types=1);

namespace RevisionTen\CMS\Model;

use Symfony\Component\Security\Core\User\UserInterface;
use Doctrine\ORM\Mapping as ORM;

/**
 * User.
 *
 * @ORM\Entity
 */
class User implements UserInterface, \Serializable
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
     * @ORM\Column(type="string", unique=true)
     */
    private $username;

    /**
     * @var string
     * @ORM\Column(type="string", unique=true)
     */
    private $email;

    /**
     * @var string
     * @ORM\Column(type="string")
     */
    private $password;

    /**
     * @var string
     * @ORM\Column(type="string")
     */
    private $secret;

    /**
     * @var string
     * @ORM\Column(type="string", nullable=true)
     */
    private $color;

    /**
     * @var string
     * @ORM\Column(type="string", nullable=true)
     */
    private $avatarUrl;

    /**
     * {@inheritdoc}
     */
    public function getRoles()
    {
        return array('ROLE_USER');
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
     */
    public function unserialize($serialized)
    {
        list(
            $this->id,
            $this->username,
            $this->password) = unserialize($serialized);
    }

    /**
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @return string
     */
    public function getUsername()
    {
        return $this->username;
    }

    /**
     * @param string $username
     *
     * @return User
     */
    public function setUsername(string $username): self
    {
        $this->username = $username;

        return $this;
    }

    /**
     * @return string
     */
    public function getEmail()
    {
        return $this->email;
    }

    /**
     * @param string $email
     *
     * @return User
     */
    public function setEmail(string $email): self
    {
        $this->email = $email;

        return $this;
    }

    /**
     * @return string
     */
    public function getPassword()
    {
        return $this->password;
    }

    /**
     * @param string $password
     *
     * @return User
     */
    public function setPassword(string $password): self
    {
        $this->password = $password;

        return $this;
    }

    /**
     * @return string
     */
    public function getSecret()
    {
        return $this->secret;
    }

    /**
     * @param string $secret
     *
     * @return User
     */
    public function setSecret(string $secret): self
    {
        $this->secret = $secret;

        return $this;
    }

    /**
     * @return string
     */
    public function getColor(): string
    {
        if (null == $this->color) {
            $this->color = $this->getColorFromUsername();
        }

        return $this->color;
    }

    /**
     * @param string $color
     *
     * @return User
     */
    public function setColor(string $color): self
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
     * @return User
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
        $h = intval($H);
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
}

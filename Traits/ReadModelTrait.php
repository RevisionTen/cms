<?php

declare(strict_types=1);

namespace RevisionTen\CMS\Traits;

use Doctrine\ORM\Mapping as ORM;

trait ReadModelTrait
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
     * @ORM\Column(type="string", options={"collation": "utf8_unicode_ci"})
     */
    private $uuid;

    /**
     * @var int
     * @ORM\Column(type="integer")
     */
    private $version;

    /**
     * @var array
     * @ORM\Column(type="text")
     */
    private $payload;

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
     * @return self
     */
    public function setUuid($uuid): self
    {
        $this->uuid = $uuid;

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
     * @return self
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
        return \is_string($this->payload) ? json_decode($this->payload, true) : $this->payload;
    }

    /**
     * @param array $payload
     *
     * @return self
     */
    public function setPayload($payload): self
    {
        $this->payload = json_encode($payload);

        return $this;
    }
}

<?php

declare(strict_types=1);

namespace RevisionTen\CMS\Traits;

use Doctrine\ORM\Mapping as ORM;
use function is_string;
use function json_decode;
use function json_encode;

trait ReadModelTrait
{
    /**
     * @ORM\Column(type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue
     */
    private ?int $id = null;

    /**
     * @ORM\Column(type="string", options={"collation": "utf8_unicode_ci"})
     */
    private ?string $uuid = null;

    /**
     * @ORM\Column(type="integer")
     */
    private ?int $version = null;

    /**
     * Todo: Add PHP 8 union type.
     * @var array|string|null
     * @ORM\Column(type="text")
     */
    private $payload;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUuid(): ?string
    {
        return $this->uuid;
    }

    public function setUuid(?string $uuid = null): self
    {
        $this->uuid = $uuid;

        return $this;
    }

    public function getVersion(): ?int
    {
        return $this->version;
    }

    public function setVersion(?int $version = null): self
    {
        $this->version = $version;

        return $this;
    }

    public function getPayload(): ?array
    {
        return is_string($this->payload) ? json_decode($this->payload, true) : $this->payload;
    }

    public function setPayload(?array $payload = null): self
    {
        $this->payload = json_encode($payload);

        return $this;
    }
}

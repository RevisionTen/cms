<?php

declare(strict_types=1);

namespace RevisionTen\CMS\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Class Task.
 *
 * @ORM\Entity(repositoryClass="RevisionTen\CMS\Repository\TaskRepository")
 * @ORM\Table(name="tasks")
 */
class Task
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
     * @var string
     * @ORM\Column(type="string", options={"collation": "utf8_unicode_ci"})
     */
    private $aggregateUuid;

    /**
     * @var string
     * @ORM\Column(type="string", options={"collation": "utf8_unicode_ci"})
     */
    private $command;

    /**
     * @var \DateTime
     *
     * @ORM\Column(type="datetime")
     */
    private $due;

    /**
     * @var array
     *
     * @ORM\Column(type="text")
     */
    private $payload;

    /**
     * @var array|null
     *
     * @ORM\Column(type="text", nullable=true)
     */
    private $resultMessage;

    /**
     * @var bool
     *
     * @ORM\Column(type="boolean", options={"default": 0})
     */
    private $deleted = false;

    /**
     * @return int
     */
    public function getId(): int
    {
        return $this->id;
    }

    /**
     * @param int $id
     *
     * @return Task
     */
    public function setId(int $id): self
    {
        $this->id = $id;

        return $this;
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
     * @return Task
     */
    public function setUuid(string $uuid): self
    {
        $this->uuid = $uuid;

        return $this;
    }

    /**
     * @return string
     */
    public function getAggregateUuid(): string
    {
        return $this->aggregateUuid;
    }

    /**
     * @param string $aggregateUuid
     *
     * @return Task
     */
    public function setAggregateUuid(string $aggregateUuid): self
    {
        $this->aggregateUuid = $aggregateUuid;

        return $this;
    }

    /**
     * @return string
     */
    public function getCommand(): string
    {
        return $this->command;
    }

    /**
     * @param string $command
     *
     * @return Task
     */
    public function setCommand(string $command): self
    {
        $this->command = $command;

        return $this;
    }

    /**
     * @return \DateTime
     */
    public function getDue(): \DateTime
    {
        return $this->due;
    }

    /**
     * @param \DateTime $due
     *
     * @return Task
     */
    public function setDue(\DateTime $due): self
    {
        $this->due = $due;

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
     * @return Task
     */
    public function setPayload(array $payload): self
    {
        $this->payload = json_encode($payload);

        return $this;
    }

    /**
     * @return array
     */
    public function getResultMessage(): ?array
    {
        if (null !== $this->resultMessage) {
            return \is_string($this->resultMessage) ? json_decode($this->resultMessage, true) : $this->resultMessage;
        }

        return null;
    }

    /**
     * @param array $resultMessage
     *
     * @return Task
     */
    public function setResultMessage(?array $resultMessage): self
    {
        $this->resultMessage = null !== $resultMessage ? json_encode($resultMessage) : null;

        return $this;
    }

    /**
     * @return bool
     */
    public function getDeleted(): bool
    {
        return $this->deleted;
    }

    /**
     * @param bool $deleted
     *
     * @return Task
     */
    public function setDeleted(bool $deleted): self
    {
        $this->deleted = $deleted;

        return $this;
    }
}

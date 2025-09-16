<?php

namespace LimeRM\Controller\Model;

/**
 * Value object representing a managed site.
 */
class Site
{
    /** @var int|null */
    private $id;

    /** @var string */
    private $name;

    /** @var string */
    private $baseUrl;

    /** @var string */
    private $siteType;

    /** @var string */
    private $sharedSecret;

    /** @var string */
    private $status;

    /** @var string|null */
    private $lastSeen;

    /** @var array */
    private $info;

    /** @var string */
    private $createdAt;

    /** @var string */
    private $updatedAt;

    public function __construct(
        ?int $id,
        string $name,
        string $baseUrl,
        string $siteType,
        string $sharedSecret,
        string $status,
        ?string $lastSeen,
        array $info,
        string $createdAt,
        string $updatedAt
    ) {
        $this->id = $id;
        $this->name = $name;
        $this->baseUrl = $baseUrl;
        $this->siteType = $siteType;
        $this->sharedSecret = $sharedSecret;
        $this->status = $status;
        $this->lastSeen = $lastSeen;
        $this->info = $info;
        $this->createdAt = $createdAt;
        $this->updatedAt = $updatedAt;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getBaseUrl(): string
    {
        return $this->baseUrl;
    }

    public function getSiteType(): string
    {
        return $this->siteType;
    }

    public function getSharedSecret(): string
    {
        return $this->sharedSecret;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function getLastSeen(): ?string
    {
        return $this->lastSeen;
    }

    public function getInfo(): array
    {
        return $this->info;
    }

    public function getCreatedAt(): string
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): string
    {
        return $this->updatedAt;
    }
}

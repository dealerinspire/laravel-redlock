<?php
/**
 * Container for the details of a locked resource.
 */
declare(strict_types=1);

namespace DealerInspire\RedLock;

/**
 * Class Lock
 * @package DealerInspire\RedLock
 */
class Lock
{
    /** @var float */
    private $validityTime;

    /** @var string */
    private $resource;

    /** @var string */
    private $token;

    /** @var float */
    private $ttl;

    /** @var RedLock */
    private $redLock;

    /**
     * Lock constructor.
     * @param RedLock $redLock
     * @param float   $validityTime
     * @param string  $resource
     * @param string  $token
     * @param float   $ttl
     */
    public function __construct(RedLock $redLock, float $validityTime, string $resource, string $token, float $ttl)
    {
        $this->validityTime = $validityTime;
        $this->resource = $resource;
        $this->token = $token;
        $this->ttl = $ttl;
        $this->redLock = $redLock;
    }

    /**
     * @return float
     */
    public function getValidityTime(): float
    {
        return $this->validityTime;
    }

    /**
     * @return string
     */
    public function getResource(): string
    {
        return $this->resource;
    }

    /**
     * @return string
     */
    public function getToken(): string
    {
        return $this->token;
    }

    /**
     * @return float
     */
    public function getTtl(): float
    {
        return $this->ttl;
    }

    /**
     * Release this lock.
     */
    public function unlock(): void
    {
        $this->redLock->unlock($this);
    }
}

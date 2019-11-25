<?php

namespace DealerInspire\RedLock;

use Predis\Client as Redis;
use Illuminate\Support\Facades\App;

class RedLock
{
    private $retryDelay;
    private $retryCount;
    private $clockDriftFactor = 0.01;
    private $quorum;
    private $servers = array();
    private $instances = array();
    public const DEFAULT_LOCK_TIME = 300; //in seconds; 5 minutes default

    public function __construct(array $servers, $retryDelay = 200, $retryCount = 3)
    {
        $this->servers = $servers;
        $this->retryDelay = $retryDelay;
        $this->retryCount = $retryCount;
        $this->quorum = min(count($servers), (count($servers) / 2 + 1));
    }

    /**
     * @param $resource
     * @param $ttl
     * @return Lock|null
     */
    public function lock($resource, $ttl): ?Lock
    {
        $this->initInstances();
        $token = uniqid();
        $retry = $this->retryCount;
        do {
            $n = 0;
            $startTime = microtime(true) * 1000;
            foreach ($this->instances as $instance) {
                if ($this->lockInstance($instance, $resource, $token, $ttl)) {
                    $n++;
                }
            }
            # Add 2 milliseconds to the drift to account for Redis expires
            # precision, which is 1 millisecond, plus 1 millisecond min drift
            # for small TTLs.
            $drift = ($ttl * $this->clockDriftFactor) + 2;
            $validityTime = $ttl - (microtime(true) * 1000 - $startTime) - $drift;
            if ($n >= $this->quorum && $validityTime > 0) {
                return new Lock($this, $validityTime, $resource, $token, $ttl);
            } else {
                foreach ($this->instances as $instance) {
                    $this->unlockInstance($instance, $resource, $token);
                }
            }
            // Wait a random delay before to retry
            $delay = mt_rand(floor($this->retryDelay / 2), $this->retryDelay);
            usleep($delay * 1000);
            $retry--;
        } while ($retry > 0);
        return null;
    }

    /**
     * @param Lock $lock
     */
    public function unlock(Lock $lock): void
    {
        $this->initInstances();
        $resource = $lock->getResource();
        $token    = $lock->getToken();
        foreach ($this->instances as $instance) {
            $this->unlockInstance($instance, $resource, $token);
        }
    }

    private function initInstances()
    {
        $app = app();
        if (empty($this->instances)) {
            foreach ($this->servers as $server) {
                // support newer and older Laravel 5.*
                if (method_exists($app, 'makeWith')) {
                    $redis = $app->makeWith(Redis::class, ['parameters' => $server]);
                } else {
                    $redis = $app->make(Redis::class, [$server]);
                }
                $this->instances[] = $redis;
            }
        }
    }

    private function lockInstance($instance, $resource, $token, $ttl)
    {
        return $instance->set($resource, $token, "PX", $ttl, "NX");
        //return $instance->set($resource, $token, ['NX', 'PX' => $ttl]);
    }

    private function unlockInstance($instance, $resource, $token)
    {
        $script = '
            if redis.call("GET", KEYS[1]) == ARGV[1] then
                return redis.call("DEL", KEYS[1])
            else
                return 0
            end
        ';
        return $instance->eval($script, 1, $resource, $token);
        //return $instance->eval($script, [$resource, $token], 1);
    }

    /**
     * @param Lock $lock
     * @return Lock|null
     */
    public function refreshLock(Lock $lock)
    {
        $this->unlock($lock);
        return $this->lock($lock->getResource(), $lock->getTtl());
    }

    public function runLocked($resource, $ttl, $closure)
    {
        $lock = $this->lock($resource, $ttl);
        if (!$lock) {
            return false;
        }
        $refresh = function () use (&$lock) {
            $lock = $this->refreshLock($lock);
            if (!$lock) {
                throw new Exceptions\ClosureRefreshException();
            }
        };
        try {
            $result = $closure($refresh);
        } catch (Exceptions\ClosureRefreshException $e) {
            return false;
        } finally {
            if ($lock) {
                $this->unlock($lock);
            }
        }
        return $result;
    }
}

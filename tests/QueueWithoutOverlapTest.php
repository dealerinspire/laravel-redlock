<?php

namespace DealerInspire\RedLock\Traits;

use DealerInspire\RedLock\Facades\RedLock;
use DealerInspire\RedLock\Lock;
use Mockery;
use TestCase;

class QueueWithoutOverlapTest extends TestCase
{
    public function testInstanciate()
    {
        self::expectNotToPerformAssertions();
        new QueueWithoutOverlapJob();
    }

    public function testAllOfIt()
    {
        $job = new QueueWithoutOverlapJob();

        $queue = Mockery::mock();
        $queue->shouldReceive('push')->with($job)->once();

        $lock = new Lock(new \DealerInspire\RedLock\RedLock([]), 2, 'DealerInspire\RedLock\Traits\QueueWithoutOverlapJob::1000:', '1111', 2);

        RedLock::shouldReceive('lock')
            ->with("DealerInspire\RedLock\Traits\QueueWithoutOverlapJob::1000:", 1000000)
            ->twice()
            ->andReturn($lock);
        RedLock::shouldReceive('unlock')
            ->with($lock)
            ->twice()
            ->andReturn(true);

        $job->queue($queue, $job);

        $job->handle();

        $this->assertTrue($job->ran);
    }

    public function testFailToLock()
    {
        $job = new QueueWithoutOverlapJob();

        $queue = Mockery::mock();

        RedLock::shouldReceive('lock')
            ->with("DealerInspire\RedLock\Traits\QueueWithoutOverlapJob::1000:", 1000000)
            ->once()
            ->andReturn(null);

        $id = $job->queue($queue, $job);

        $this->assertFalse($id);
    }

    public function testFailToRefresh()
    {
        $job = new QueueWithoutOverlapJob();

        $queue = Mockery::mock();
        $queue->shouldReceive('push')->with($job)->once();

        $lock = new Lock(new \DealerInspire\RedLock\RedLock([]), 2, 'DealerInspire\RedLock\Traits\QueueWithoutOverlapJob::1000:', '1111', 2);

        RedLock::shouldReceive('lock')
            ->with("DealerInspire\RedLock\Traits\QueueWithoutOverlapJob::1000:", 1000000)
            ->twice()
            ->andReturn(
                $lock,
                null
            );
        RedLock::shouldReceive('unlock')
            ->with($lock)
            ->once()
            ->andReturn(true);

        $job->queue($queue, $job);

        $this->expectException('DealerInspire\RedLock\Exceptions\QueueWithoutOverlapRefreshException');

        $job->handle();
    }

    public function testAllOfItDefaultLockTime()
    {
        $job = new QueueWithoutOverlapJobDefaultLockTime();

        $queue = Mockery::mock();
        $queue->shouldReceive('push')->with($job)->once();

        $lock = new Lock(new \DealerInspire\RedLock\RedLock([]), 2, 'DealerInspire\RedLock\Traits\QueueWithoutOverlapJobDefaultLockTime::', '1111', 2);

        RedLock::shouldReceive('lock')
            ->with("DealerInspire\RedLock\Traits\QueueWithoutOverlapJobDefaultLockTime::", 300000)
            ->twice()
            ->andReturn($lock);
        RedLock::shouldReceive('unlock')
            ->with($lock)
            ->twice()
            ->andReturn(true);

        $job->queue($queue, $job);

        $job->handle();

        $this->assertTrue($job->ran);
    }
}

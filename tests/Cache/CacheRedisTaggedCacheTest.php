<?php

declare(strict_types=1);

namespace SwooleTW\Hyperf\Tests\Cache;

use Carbon\Carbon;
use Hyperf\Redis\RedisFactory;
use Hyperf\Redis\RedisProxy;
use Mockery as m;
use Mockery\MockInterface;
use SwooleTW\Hyperf\Cache\RedisStore;
use SwooleTW\Hyperf\Tests\TestCase;

/**
 * @internal
 * @coversNothing
 */
class CacheRedisTaggedCacheTest extends TestCase
{
    private RedisStore $redis;

    /** @var MockInterface|RedisProxy */
    private RedisProxy $redisProxy;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockRedis();
    }

    public function testTagEntriesCanBeStoredForever()
    {
        $key = sha1('tag:people:entries|tag:author:entries') . ':name';
        $this->redisProxy->shouldReceive('zadd')->once()->with('prefix:tag:people:entries', -1, $key)->andReturn('OK');
        $this->redisProxy->shouldReceive('zadd')->once()->with('prefix:tag:author:entries', -1, $key)->andReturn('OK');
        $this->redisProxy->shouldReceive('set')->once()->with("prefix:{$key}", serialize('Sally'))->andReturn('OK');

        $this->redis->tags(['people', 'author'])->forever('name', 'Sally');

        $key = sha1('tag:people:entries|tag:author:entries') . ':age';
        $this->redisProxy->shouldReceive('zadd')->once()->with('prefix:tag:people:entries', -1, $key)->andReturn('OK');
        $this->redisProxy->shouldReceive('zadd')->once()->with('prefix:tag:author:entries', -1, $key)->andReturn('OK');
        $this->redisProxy->shouldReceive('set')->once()->with("prefix:{$key}", 30)->andReturn('OK');

        $this->redis->tags(['people', 'author'])->forever('age', 30);

        $this->redisProxy
            ->shouldReceive('zScan')
            ->once()
            ->with('prefix:tag:people:entries', '0', ['match' => '*', 'count' => 1000])
            ->andReturn(['0', ['tag:people:entries:name' => 0, 'tag:people:entries:age' => 0]]);
        $this->redisProxy
            ->shouldReceive('zScan')
            ->once()
            ->with('prefix:tag:author:entries', '0', ['match' => '*', 'count' => 1000])
            ->andReturn(['0', ['tag:author:entries:name' => 0, 'tag:author:entries:age' => 0]]);
        $this->redisProxy->shouldReceive('del')->once()->with(
            'prefix:tag:people:entries:name',
            'prefix:tag:people:entries:age',
            'prefix:tag:author:entries:name',
            'prefix:tag:author:entries:age'
        )->andReturn('OK');
        $this->redisProxy->shouldReceive('del')->once()->with('prefix:tag:people:entries')->andReturn('OK');
        $this->redisProxy->shouldReceive('del')->once()->with('prefix:tag:author:entries')->andReturn('OK');

        $this->redis->tags(['people', 'author'])->flush();
    }

    public function testTagEntriesCanBeIncremented()
    {
        Carbon::setTestNow('2000-01-01 00:00:00');

        $key = sha1('tag:votes:entries') . ':person-1';
        $this->redisProxy->shouldReceive('zadd')->once()->with('prefix:tag:votes:entries', now()->timestamp + 5, $key)->andReturn('OK');
        $this->redisProxy->shouldReceive('setex')->once()->with("prefix:{$key}", 5, 0)->andReturn('OK');
        $this->redisProxy->shouldReceive('zadd')->times(4)->with('prefix:tag:votes:entries', 'NX', -1, $key)->andReturn('OK');
        $this->redisProxy->shouldReceive('incrby')->once()->with("prefix:{$key}", 1)->andReturn(1);
        $this->redisProxy->shouldReceive('incrby')->once()->with("prefix:{$key}", 1)->andReturn(2);
        $this->redisProxy->shouldReceive('decrby')->once()->with("prefix:{$key}", 1)->andReturn(1);
        $this->redisProxy->shouldReceive('decrby')->once()->with("prefix:{$key}", 1)->andReturn(0);

        $this->redis->tags(['votes'])->put('person-1', 0, 5);

        $this->assertSame(1, $this->redis->tags(['votes'])->increment('person-1'));
        $this->assertSame(2, $this->redis->tags(['votes'])->increment('person-1'));

        $this->assertSame(1, $this->redis->tags(['votes'])->decrement('person-1'));
        $this->assertSame(0, $this->redis->tags(['votes'])->decrement('person-1'));
    }

    public function testStaleEntriesCanBeFlushed()
    {
        Carbon::setTestNow('2000-01-01 00:00:00');

        $pipe = m::mock(RedisProxy::class);
        $pipe->shouldReceive('zremrangebyscore')->once()->with('prefix:tag:people:entries', 0, now()->timestamp)->andReturn('OK');
        $this->redisProxy->shouldReceive('pipeline')->once()->withArgs(function ($callback) use ($pipe) {
            $callback($pipe);

            return true;
        });

        $this->redis->tags(['people'])->flushStale();
    }

    private function mockRedis()
    {
        $this->redis = new RedisStore(m::mock(RedisFactory::class), 'prefix');
        $this->redisProxy = m::mock(RedisProxy::class);

        $this->redis->getRedis()->shouldReceive('get')->with('default')->andReturn($this->redisProxy);
    }
}

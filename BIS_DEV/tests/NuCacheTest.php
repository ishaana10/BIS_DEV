<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

class NuCacheTest extends TestCase
{
    protected function setUp(): void
    {
        NuCache::resetInstance();
    }

    protected function tearDown(): void
    {
        NuCache::resetInstance();
    }

    public function testDisabledConfigReturnsDefault(): void
    {
        $GLOBALS['nuConfigRedis'] = [
            'enabled' => false,
        ];

        $cache = NuCache::getInstance();
        $this->assertFalse($cache->isEnabled());
        $this->assertNull($cache->get('non_existent_key'));
        $this->assertEquals('default_val', $cache->get('non_existent_key', 'default_val'));
        $this->assertFalse($cache->set('some_key', 'some_val'));
        $this->assertFalse($cache->delete('some_key'));
        $this->assertEquals(0, $cache->deleteByPattern('test:*'));
        $this->assertFalse($cache->flush());
    }

    public function testRememberFallbackWhenDisabled(): void
    {
        $GLOBALS['nuConfigRedis'] = [
            'enabled' => false,
        ];

        $cache = NuCache::getInstance();
        $called = false;
        $result = $cache->remember('key', function () use (&$called) {
            $called = true;
            return 'computed_val';
        });

        $this->assertTrue($called);
        $this->assertEquals('computed_val', $result);
    }

    public function testRedisSessionHandlerInterfaceWithMock(): void
    {
        if (class_exists('Redis')) {
            $mockRedis = $this->createMock(\Redis::class);
            $mockRedis->expects($this->once())
                ->method('get')
                ->with('nuvis:sess:session_123')
                ->willReturn('serialized_data');

            $mockRedis->expects($this->once())
                ->method('setex')
                ->with('nuvis:sess:session_123', 1800, 'serialized_data')
                ->willReturn(true);

            $mockRedis->expects($this->once())
                ->method('del')
                ->with('nuvis:sess:session_123')
                ->willReturn(1);

            $handler = new NuRedisSessionHandler($mockRedis, 'nuvis:sess:', 1800);

            $this->assertTrue($handler->open('', ''));
            $this->assertEquals('serialized_data', $handler->read('session_123'));
            $this->assertTrue($handler->write('session_123', 'serialized_data'));
            $this->assertTrue($handler->destroy('session_123'));
            $this->assertTrue($handler->close());
            $this->assertEquals(0, $handler->gc(1800));
        } else {
            $this->assertTrue(true, 'Redis extension not loaded on environment');
        }
    }
}

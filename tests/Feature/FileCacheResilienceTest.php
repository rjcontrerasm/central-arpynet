<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class FileCacheResilienceTest extends TestCase
{
    private string $testCachePath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->testCachePath = storage_path(
            'framework/cache/central-resilience-test',
        );

        File::deleteDirectory($this->testCachePath);
        File::ensureDirectoryExists($this->testCachePath);

        config()->set(
            'cache.stores.file.path',
            $this->testCachePath,
        );

        config()->set(
            'cache.stores.file.lock_path',
            $this->testCachePath,
        );
    }

    protected function tearDown(): void
    {
        Cache::store('file')->forget(
            'central-resilience-probe',
        );

        File::deleteDirectory($this->testCachePath);

        parent::tearDown();
    }

    public function test_file_cache_reads_and_writes_without_database_store(): void
    {
        $store = Cache::store('file');

        $store->put(
            'central-resilience-probe',
            'ok',
            60,
        );

        $this->assertSame(
            'ok',
            $store->get('central-resilience-probe'),
        );
    }

    public function test_file_cache_supports_scheduler_style_locks(): void
    {
        $lock = Cache::store('file')->lock(
            'central-scheduler-lock-probe',
            30,
        );

        $this->assertTrue($lock->get());

        $lock->release();

        $this->assertTrue(
            Cache::store('file')
                ->lock(
                    'central-scheduler-lock-probe',
                    30,
                )
                ->get(),
        );
    }
}

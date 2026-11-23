<?php

declare(strict_types=1);

namespace TTDF\Tests\Unit;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use TTDF\Cache\Cache;
use TTDF\Cache\RedisClient;
use TTDF\Cache\RedisConfig;
use TTDF\Cache\RedisStore;
use TTDF\Cache\Repository;
use TTDF\Cache\Store;
use TTDF\Framework;
use TTDF\Theme\CacheSettings;
use TTDF\Theme\Schema;

final class RecordingStore implements Store
{
    /** @var array<string, string> */
    public array $items = [];

    /** @var array<string, int> */
    public array $ttls = [];

    /** @var list<string> */
    public array $clearedPrefixes = [];

    public function get(string $key): ?string
    {
        return $this->items[$key] ?? null;
    }

    public function put(string $key, string $value, int $ttl = 0): bool
    {
        $this->items[$key] = $value;
        $this->ttls[$key] = $ttl;

        return true;
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->items);
    }

    public function forget(string $key): bool
    {
        $existed = array_key_exists($key, $this->items);
        unset($this->items[$key], $this->ttls[$key]);

        return $existed;
    }

    public function increment(string $key, int $by = 1, int $ttl = 0): int|false
    {
        $value = (int) ($this->items[$key] ?? 0) + $by;
        $this->items[$key] = (string) $value;
        if (!array_key_exists($key, $this->ttls)) {
            $this->ttls[$key] = $ttl;
        }

        return $value;
    }

    public function clear(string $prefix = ''): int
    {
        $this->clearedPrefixes[] = $prefix;
        $deleted = 0;

        foreach (array_keys($this->items) as $key) {
            if ($prefix === '' || str_starts_with($key, $prefix)) {
                unset($this->items[$key], $this->ttls[$key]);
                $deleted++;
            }
        }

        return $deleted;
    }

    public function ping(): bool
    {
        return true;
    }
}

final class FakeRedisClient implements RedisClient
{
    /** @var array<string, string> */
    public array $items = [];

    /** @var array<string, int> */
    public array $ttls = [];

    /** @var array<string, int> */
    public array $expirations = [];

    /** @var list<string> */
    public array $scanPatterns = [];

    public function get(string $key): string|false
    {
        return $this->items[$key] ?? false;
    }

    public function set(string $key, string $value): bool
    {
        $this->items[$key] = $value;

        return true;
    }

    public function setEx(string $key, int $ttl, string $value): bool
    {
        $this->items[$key] = $value;
        $this->ttls[$key] = $ttl;

        return true;
    }

    public function del(string ...$keys): int
    {
        $deleted = 0;
        foreach ($keys as $key) {
            if (array_key_exists($key, $this->items)) {
                unset($this->items[$key], $this->ttls[$key]);
                $deleted++;
            }
        }

        return $deleted;
    }

    public function exists(string $key): bool
    {
        return array_key_exists($key, $this->items);
    }

    public function incrBy(string $key, int $by): int|false
    {
        $value = (int) ($this->items[$key] ?? 0) + $by;
        $this->items[$key] = (string) $value;

        return $value;
    }

    public function expire(string $key, int $ttl): bool
    {
        $this->expirations[$key] = $ttl;

        return true;
    }

    public function scan(?int &$iterator, string $pattern, int $count): array|false
    {
        $this->scanPatterns[] = $pattern;
        if ($iterator !== null) {
            return false;
        }

        $iterator = 0;
        $prefix = $pattern === '*' ? '' : substr($pattern, 0, -1);

        return array_values(array_filter(
            array_keys($this->items),
            static fn (string $key): bool => $pattern === '*' || str_starts_with($key, $prefix)
        ));
    }

    public function ping(): bool
    {
        return true;
    }

    public function close(): void
    {
    }

    public function lastError(): ?string
    {
        return null;
    }
}

final class CacheTest extends TestCase
{
    protected function tearDown(): void
    {
        Cache::reset();
    }

    public function testRepositoryRoundTripsScalarsArraysAndNull(): void
    {
        $store = new RecordingStore();
        $repository = new Repository($store, 'ttdf:test:', 120);

        self::assertNull($repository->get('missing'));
        self::assertSame('fallback', $repository->get('missing', 'fallback'));

        self::assertTrue($repository->put('string', 'hello'));
        self::assertSame('hello', $repository->get('string'));

        self::assertTrue($repository->put('array', ['enabled' => true, 'count' => 2]));
        self::assertSame(['enabled' => true, 'count' => 2], $repository->get('array'));

        self::assertTrue($repository->put('null', null));
        self::assertTrue($repository->has('null'));
        self::assertNull($repository->get('null', 'fallback'));
    }

    public function testRepositoryUsesDefaultAndExplicitTtl(): void
    {
        $store = new RecordingStore();
        $repository = new Repository($store, 'ttdf:test:', 120);

        $repository->put('default', 'value');
        $repository->put('explicit', 'value', 15);
        $repository->put('persistent', 'value', 0);

        self::assertSame(120, $store->ttls['ttdf:test:default']);
        self::assertSame(15, $store->ttls['ttdf:test:explicit']);
        self::assertSame(0, $store->ttls['ttdf:test:persistent']);
    }

    public function testRepositoryEvictsCorruptPayload(): void
    {
        $store = new RecordingStore();
        $repository = new Repository($store, 'ttdf:test:', 120);
        $store->items['ttdf:test:corrupt'] = '{not-json';

        self::assertSame('fallback', $repository->get('corrupt', 'fallback'));
        self::assertFalse($repository->has('corrupt'));
    }

    public function testRepositoryRememberRunsCallbackOnlyOnMiss(): void
    {
        $repository = new Repository(new RecordingStore(), 'ttdf:test:', 120);
        $calls = 0;

        $first = $repository->remember('remember', function () use (&$calls): array {
            $calls++;

            return ['value' => 'computed'];
        });
        $second = $repository->remember('remember', function () use (&$calls): array {
            $calls++;

            return ['value' => 'unexpected'];
        });

        self::assertSame(1, $calls);
        self::assertSame(['value' => 'computed'], $first);
        self::assertSame($first, $second);
    }

    public function testRepositoryForgetAndIncrement(): void
    {
        $repository = new Repository(new RecordingStore(), 'ttdf:test:', 120);
        $repository->put('key', 'value');

        self::assertTrue($repository->forget('key'));
        self::assertFalse($repository->has('key'));

        self::assertSame(1, $repository->increment('counter'));
        self::assertSame(3, $repository->increment('counter', 2));
    }

    public function testRepositoryRejectsInvalidKeys(): void
    {
        $repository = new Repository(new RecordingStore(), 'ttdf:test:', 120);

        $this->expectException(InvalidArgumentException::class);
        $repository->put('bad key', 'value');
    }

    public function testRepositoryRefusesToFlushWithoutNamespace(): void
    {
        $repository = new Repository(new RecordingStore());

        $this->expectException(InvalidArgumentException::class);
        $repository->flush();
    }

    public function testRedisStoreNamespacesAndExpiresFirstIncrement(): void
    {
        $client = new FakeRedisClient();
        $store = new RedisStore($client);

        self::assertTrue($store->put('ttdf:test:string', 'value', 30));
        self::assertSame('value', $store->get('ttdf:test:string'));
        self::assertSame(30, $client->ttls['ttdf:test:string']);

        self::assertSame(2, $store->increment('ttdf:test:counter', 2, 60));
        self::assertSame(60, $client->expirations['ttdf:test:counter']);

        self::assertSame(4, $store->increment('ttdf:test:counter', 2, 60));
        self::assertCount(1, $client->expirations);
    }

    public function testRedisStoreClearUsesScanAndStaysInNamespace(): void
    {
        $client = new FakeRedisClient();
        $store = new RedisStore($client);

        $client->items['ttdf:test:a'] = '1';
        $client->items['ttdf:test:b'] = '2';
        $client->items['other:test:c'] = '3';

        self::assertSame(2, $store->clear('ttdf:test:'));
        self::assertSame(['ttdf:test:*'], $client->scanPatterns);
        self::assertArrayNotHasKey('ttdf:test:a', $client->items);
        self::assertArrayNotHasKey('ttdf:test:b', $client->items);
        self::assertArrayHasKey('other:test:c', $client->items);
    }

    public function testRedisConfigClampsUnsafeValues(): void
    {
        $config = RedisConfig::fromArray([
            'enabled' => true,
            'host' => "redis\r\nSET injected",
            'port' => 70_000,
            'username' => " admin\n",
            'password' => ' secret ',
            'database' => 999,
            'prefix' => '..site prefix..',
            'timeout' => 0,
            'ttl' => -10,
        ], 'https://Example.test/', 'SunkenBoat');

        self::assertTrue($config->enabled());
        self::assertSame('127.0.0.1', $config->host());
        self::assertSame(65_535, $config->port());
        self::assertSame('admin', $config->username());
        self::assertSame(' secret ', $config->password());
        self::assertSame(255, $config->database());
        self::assertSame('..siteprefix..', $config->prefix());
        self::assertSame(1.0, $config->timeout());
        self::assertSame(0, $config->ttl());
    }

    public function testRedisConfigBuildsStableSiteAndThemeNamespace(): void
    {
        $config = RedisConfig::fromArray(
            ['enabled' => true, 'prefix' => 'blog'],
            'https://Example.test/',
            'SunkenBoat'
        );
        $hash = substr(hash('sha256', 'https://example.test'), 0, 10);

        self::assertSame('ttdf:' . $hash . ':SunkenBoat:blog:', $config->namespace());
        self::assertArrayNotHasKey('password', $config->toArray());
        self::assertArrayNotHasKey('username', $config->toArray());
    }

    public function testRedisConfigDefaultsAreDisabledAndSafe(): void
    {
        $config = RedisConfig::fromArray([]);

        self::assertFalse($config->enabled());
        self::assertSame('127.0.0.1', $config->host());
        self::assertSame(6379, $config->port());
        self::assertSame(0, $config->database());
        self::assertSame(1.0, $config->timeout());
        self::assertSame(300, $config->ttl());
        self::assertStringStartsWith('ttdf:', $config->namespace());
    }

    public function testRedisConfigAcceptsStoredFieldNames(): void
    {
        $config = RedisConfig::fromArray([
            'Cache_Redis_Enabled' => true,
            'Cache_Redis_Host' => 'redis.internal',
            'Cache_Redis_Port' => 6380,
            'Cache_Redis_Database' => 2,
        ]);

        self::assertTrue($config->enabled());
        self::assertSame('redis.internal', $config->host());
        self::assertSame(6380, $config->port());
        self::assertSame(2, $config->database());
    }

    public function testFacadeConfigureOverridesThemeDefaults(): void
    {
        Cache::configure(['enabled' => true, 'host' => 'redis.internal']);

        self::assertTrue(Cache::enabled());
        self::assertSame('redis.internal', Cache::config()?->host());
    }

    public function testFacadeFailsOpenWhenRedisIsDisabled(): void
    {
        Cache::reset();
        self::assertFalse(Cache::enabled());
        self::assertSame('fallback', Cache::get('key', 'fallback'));
        self::assertFalse(Cache::put('key', 'value'));
        self::assertFalse(Cache::forget('key'));
        self::assertFalse(Cache::increment('counter'));
        self::assertSame(0, Cache::flush());

        $calls = 0;
        $value = Cache::remember('computed', function () use (&$calls): string {
            $calls++;

            return 'result';
        });

        self::assertSame('result', $value);
        self::assertSame(1, $calls);

        $health = Cache::health();
        self::assertFalse($health['enabled']);
        self::assertArrayNotHasKey('password', $health);
    }

    public function testFacadeWithInjectedStoreNamespacesKeys(): void
    {
        $store = new RecordingStore();
        $config = RedisConfig::fromArray(
            ['enabled' => true, 'ttl' => 60, 'prefix' => 'tests'],
            'https://example.test',
            'Theme'
        );
        Cache::useStore($store, $config);

        self::assertTrue(Cache::enabled());
        self::assertTrue(Cache::put('alpha', ['value' => 1]));
        self::assertSame(['value' => 1], Cache::get('alpha'));
        self::assertSame(60, $store->ttls[$config->namespace() . 'alpha']);

        $calls = 0;
        self::assertSame('cached', Cache::remember('beta', function () use (&$calls): string {
            $calls++;

            return 'cached';
        }));
        Cache::remember('beta', function () use (&$calls): string {
            $calls++;

            return 'unexpected';
        });

        self::assertSame(1, $calls);
        self::assertSame(1, Cache::increment('counter'));
        self::assertSame(2, Cache::increment('counter'));
        self::assertTrue(Cache::forget('alpha'));
        self::assertSame(1, Cache::flush('beta'));
        self::assertTrue(Cache::health()['connected']);
    }

    public function testCacheSettingsSectionIsRegisteredInThemeSchema(): void
    {
        $fields = CacheSettings::fields();
        $names = array_column($fields, 'name');

        self::assertCount(9, $fields);
        self::assertContains(CacheSettings::ENABLED, $names);
        self::assertContains(CacheSettings::PASSWORD, $names);
        self::assertContains(CacheSettings::TTL, $names);

        $tabs = Schema::tabs(Framework::root());
        self::assertArrayHasKey('Runtime', $tabs);

        $sectionIds = array_column($tabs['Runtime']['sections'], 'id');
        self::assertContains('cache', $sectionIds);
    }
}

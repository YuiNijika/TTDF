<?php

declare(strict_types=1);

namespace TTDF\Theme;

/**
 * Reusable Redis settings section for TTDF themes.
 *
 * Add it to `app/config/theme.php` under any group:
 *
 *     'cache' => \TTDF\Theme\CacheSettings::section(),
 */
final class CacheSettings
{
    public const ENABLED = 'Cache_Redis_Enabled';

    public const HOST = 'Cache_Redis_Host';

    public const PORT = 'Cache_Redis_Port';

    public const USERNAME = 'Cache_Redis_Username';

    public const PASSWORD = 'Cache_Redis_Password';

    public const DATABASE = 'Cache_Redis_Database';

    public const PREFIX = 'Cache_Redis_Prefix';

    public const TIMEOUT = 'Cache_Redis_Timeout';

    public const TTL = 'Cache_Redis_TTL';

    /**
     * @return list<array<string, mixed>>
     */
    public static function fields(): array
    {
        return [
            [
                'type' => 'Switch',
                'name' => self::ENABLED,
                'value' => false,
                'label' => '启用 Redis 缓存',
                'description' => '开启后主题可通过统一的缓存接口读写 Redis；Redis 不可用时自动回退为不缓存，不影响页面访问。',
            ],
            [
                'type' => 'Text',
                'name' => self::HOST,
                'value' => '127.0.0.1',
                'label' => 'Redis 主机',
                'description' => '支持 IPv4、IPv6 或域名；远程服务请确认防火墙与访问白名单已放行。',
                'inline' => true,
                'inline_group' => 'cache-host',
                'show_when' => [self::ENABLED => true],
            ],
            [
                'type' => 'Number',
                'name' => self::PORT,
                'value' => 6379,
                'label' => '端口',
                'description' => 'Redis 默认端口为 6379。',
                'min' => 1,
                'max' => 65535,
                'step' => 1,
                'inline' => true,
                'inline_group' => 'cache-host',
                'show_when' => [self::ENABLED => true],
            ],
            [
                'type' => 'Text',
                'name' => self::USERNAME,
                'value' => '',
                'label' => 'ACL 用户名',
                'description' => 'Redis 6 及以上 ACL 用户名；使用仅密码认证时留空。',
                'inline' => true,
                'inline_group' => 'cache-auth',
                'show_when' => [self::ENABLED => true],
            ],
            [
                'type' => 'Password',
                'name' => self::PASSWORD,
                'value' => '',
                'label' => '密码',
                'description' => '仅用于服务端连接，不会出现在缓存诊断信息或日志中。',
                'inline' => true,
                'inline_group' => 'cache-auth',
                'show_when' => [self::ENABLED => true],
            ],
            [
                'type' => 'Number',
                'name' => self::DATABASE,
                'value' => 0,
                'label' => '数据库编号',
                'description' => '0 到 255；多站点共用实例时建议配合自定义前缀隔离。',
                'min' => 0,
                'max' => 255,
                'step' => 1,
                'inline' => true,
                'inline_group' => 'cache-space',
                'show_when' => [self::ENABLED => true],
            ],
            [
                'type' => 'Text',
                'name' => self::PREFIX,
                'value' => '',
                'label' => '自定义键前缀',
                'description' => '可留空。框架会自动加入站点与主题标识，这里只需填写业务前缀。',
                'inline' => true,
                'inline_group' => 'cache-space',
                'show_when' => [self::ENABLED => true],
            ],
            [
                'type' => 'Number',
                'name' => self::TIMEOUT,
                'value' => 1.0,
                'label' => '连接超时（秒）',
                'description' => '连接与读取超时，范围 0.1 到 10 秒；超时后本请求不再尝试 Redis。',
                'min' => 0.1,
                'max' => 10,
                'step' => 0.1,
                'inline' => true,
                'inline_group' => 'cache-window',
                'show_when' => [self::ENABLED => true],
            ],
            [
                'type' => 'Number',
                'name' => self::TTL,
                'value' => 300,
                'label' => '默认过期时间（秒）',
                'description' => '调用方未指定 TTL 时使用；填 0 表示不过期，最大一年。',
                'min' => 0,
                'max' => 31_536_000,
                'step' => 60,
                'inline' => true,
                'inline_group' => 'cache-window',
                'show_when' => [self::ENABLED => true],
            ],
        ];
    }

    /**
     * Drop-in section definition for `app/config/theme.php`.
     *
     * @return array<string, mixed>
     */
    public static function section(): array
    {
        return [
            'title' => 'Redis 缓存',
            'description' => '可选缓存层。配置只作用于当前主题，连接失败时自动降级为无缓存模式。',
            'fields' => self::fields(),
        ];
    }

    /**
     * Effective values with schema defaults. Returns short keys so the cache
     * config does not depend on admin field names.
     *
     * @return array<string, mixed>
     */
    public static function values(): array
    {
        $defaults = [
            'enabled' => false,
            'host' => '127.0.0.1',
            'port' => 6379,
            'username' => '',
            'password' => '',
            'database' => 0,
            'prefix' => '',
            'timeout' => 1.0,
            'ttl' => 300,
        ];

        try {
            $all = Settings::values();
        } catch (\Throwable) {
            $all = [];
        }

        $map = [
            'enabled' => self::ENABLED,
            'host' => self::HOST,
            'port' => self::PORT,
            'username' => self::USERNAME,
            'password' => self::PASSWORD,
            'database' => self::DATABASE,
            'prefix' => self::PREFIX,
            'timeout' => self::TIMEOUT,
            'ttl' => self::TTL,
        ];

        $values = [];
        foreach ($map as $key => $fieldName) {
            $values[$key] = array_key_exists($fieldName, $all)
                ? $all[$fieldName]
                : $defaults[$key];
        }

        return $values;
    }
}

<?php

declare(strict_types=1);

namespace Typecho\Db {
    final class Query
    {
        /** @var list<string> */
        public array $fields = [];

        public ?string $table = null;

        /** @var list<array{condition: string, values: list<mixed>}> */
        public array $wheres = [];

        /** @var list<array{field: string, order: string}> */
        public array $orders = [];

        /** @var list<array{table: string, condition: string, type: string}> */
        public array $joins = [];

        public ?int $limit = null;

        public ?int $offset = null;

        public ?string $mode = null;

        /** @var array<string, mixed> */
        public array $rows = [];

        public function from(string ...$tables): self
        {
            $this->table = $tables[0] ?? null;
            return $this;
        }

        public function where(string $condition, mixed ...$values): self
        {
            $this->wheres[] = ['condition' => $condition, 'values' => $values];
            return $this;
        }

        public function orWhere(string $condition, mixed ...$values): self
        {
            $this->wheres[] = ['condition' => $condition, 'values' => $values];
            return $this;
        }

        public function join(string $table, string $condition, string $type = 'LEFT'): self
        {
            $this->joins[] = ['table' => $table, 'condition' => $condition, 'type' => $type];
            return $this;
        }

        public function group(string $field): self
        {
            return $this;
        }

        public function order(string $field, string $order = 'DESC'): self
        {
            $this->orders[] = ['field' => $field, 'order' => $order];
            return $this;
        }

        public function limit(int $limit): self
        {
            $this->limit = $limit;
            return $this;
        }

        public function offset(int $offset): self
        {
            $this->offset = $offset;
            return $this;
        }

        public function rows(array $rows): self
        {
            $this->rows = $rows;
            return $this;
        }
    }
}

namespace Typecho {
    use Typecho\Db\Query;

    final class Config extends \stdClass
    {
        public function __construct(array $config = [])
        {
            foreach ($config as $name => $value) {
                $this->{$name} = $value;
            }
        }
    }

    final class Common
    {
        public static function stripTags(?string $html, ?string $allowableTags = null): string
        {
            return strip_tags((string) $html, $allowableTags ?? '');
        }

        public static function hash(?string $string, ?string $salt = null): string
        {
            if (!isset($string)) {
                return '';
            }

            $salt = $salt === null || $salt === '' ? 'testsalt1' : $salt;

            return '$T$' . $salt . md5($salt . $string);
        }

        public static function hashValidate(?string $from, ?string $to): bool
        {
            if ($from === null || $to === null) {
                return false;
            }

            if (substr($to, 0, 3) === '$T$') {
                return self::hash($from, substr($to, 3, 9)) === $to;
            }

            return md5($from) === $to;
        }

        public static function randString(int $length, bool $specialChars = false): string
        {
            return substr(str_repeat('0123456789abcdef', 4), 0, $length);
        }

        public static function slugName(?string $str, string $default = '', int $maxLength = 128): string
        {
            $slug = strtolower(trim((string) $str));
            $slug = preg_replace('/[^a-z0-9_-]+/', '-', $slug) ?? '';
            $slug = trim($slug, '-');

            return substr($slug !== '' ? $slug : $default, 0, $maxLength);
        }
    }

    final class Cookie
    {
        /** @var array<string, string> */
        public static array $values = [];

        public static function get(string $key, ?string $default = null): ?string
        {
            return self::$values[$key] ?? $default;
        }

        public static function set(string $key, mixed $value, int $expire = 0): void
        {
            self::$values[$key] = (string) $value;
        }

        public static function delete(string $key): void
        {
            unset(self::$values[$key]);
        }

        public static function reset(): void
        {
            self::$values = [];
        }
    }

    final class Db
    {
        public const SORT_ASC = 'ASC';
        public const SORT_DESC = 'DESC';

        /** @var (callable(Query): array<int, array<string, mixed>>)|null */
        public static $fetcher = null;

        /** @var (callable(Query): int)|null */
        public static $writer = null;

        /** @var list<Query> */
        public static array $executed = [];

        /** @var list<Query> */
        public static array $written = [];

        public static int $nextId = 100;

        public static function get(): self
        {
            return new self();
        }

        public function getPrefix(): string
        {
            return 'typecho_';
        }

        public function getAdapter(): object
        {
            return new class {
            };
        }

        public function getAdapterName(): string
        {
            return 'Mysql';
        }

        public function select(string ...$fields): Query
        {
            $query = new Query();
            $query->fields = $fields;
            return $query;
        }

        public function insert(string $table): Query
        {
            $query = new Query();
            $query->mode = 'insert';
            $query->table = $table;

            return $query;
        }

        public function update(string $table): Query
        {
            $query = new Query();
            $query->mode = 'update';
            $query->table = $table;

            return $query;
        }

        public function delete(string $table): Query
        {
            $query = new Query();
            $query->mode = 'delete';
            $query->table = $table;

            return $query;
        }

        public function query(string|Query $query): mixed
        {
            if ($query instanceof Query) {
                self::$written[] = $query;

                if (self::$writer !== null && in_array($query->mode, ['insert', 'update', 'delete'], true)) {
                    return (self::$writer)($query);
                }

                if ($query->mode === 'insert') {
                    return self::$nextId++;
                }

                if ($query->mode === 'update' || $query->mode === 'delete') {
                    return 1;
                }
            }

            return null;
        }

        public function fetchAll(Query $query): array
        {
            self::$executed[] = $query;
            return self::$fetcher !== null ? (self::$fetcher)($query) : [];
        }

        public function fetchObject(Query $query): ?object
        {
            $rows = $this->fetchAll($query);
            return $rows === [] ? null : (object) $rows[0];
        }

        public function fetchRow(Query $query): ?array
        {
            $rows = $this->fetchAll($query);
            return $rows[0] ?? null;
        }

        public static function reset(): void
        {
            self::$fetcher = null;
            self::$writer = null;
            self::$executed = [];
            self::$written = [];
            self::$nextId = 100;
        }
    }

    final class Response
    {
        public static function getInstance(): self
        {
            return new self();
        }

        public function setStatus(int $status): void
        {
        }
    }

    final class Plugin
    {
        /** @var array<string, array<string, mixed>> */
        public static array $hooks = [];

        public function __construct(private readonly string $component)
        {
        }

        public static function factory(string $component): self
        {
            return new self($component);
        }

        /**
         * @return array<string, mixed>
         */
        public static function parseInfo(string $pluginFile): array
        {
            return [];
        }

        public function __set(string $name, mixed $value): void
        {
            self::$hooks[$this->component][$name] = $value;
        }

        public static function reset(): void
        {
            self::$hooks = [];
        }
    }

    final class Router
    {
        /** @var array<string, array<string, mixed>> */
        private static array $routes = [];

        public static function setRoutes(array $routes): void
        {
            self::$routes = is_array($routes[0] ?? null) ? $routes[0] : $routes;
        }

        /**
         * @return array<string, array<string, mixed>>
         */
        public static function routes(): array
        {
            return self::$routes;
        }

        public static function reset(): void
        {
            self::$routes = [];
        }

        public static function url(string $name, ?array $value = null, ?string $prefix = null): string
        {
            if ($name === 'TTDF_AJAX') {
                return '#';
            }

            $base = rtrim((string) $prefix, '/');
            $slug = (string) ($value['slug'] ?? '');

            return $name === 'page'
                ? $base . '/' . $slug . '.html'
                : $base . '/archives/' . (int) ($value['cid'] ?? 0) . '/';
        }
    }

}

namespace Typecho\Router {
    final class Parser
    {
        public function __construct(private readonly array $routes)
        {
        }

        public function parse(): array
        {
            return $this->routes;
        }
    }
}

namespace Widget {
    final class Security
    {
        public static function alloc(): self
        {
            return new self();
        }

        public function getToken(?string $suffix): string
        {
            return hash('sha256', 'test-secret&' . (string) $suffix);
        }
    }

    final class User
    {
        public static bool $loggedIn = true;

        public static bool $administrator = true;

        public static bool $credentialsValid = true;

        public static int $loginUid = 1;

        public int $uid = 1;

        public static function alloc(): self
        {
            return new self();
        }

        public function hasLogin(): bool
        {
            return self::$loggedIn;
        }

        public function pass(string $permission, bool $throw = false): bool
        {
            return self::$administrator && $permission === 'administrator';
        }

        public function login(string $name, string $password, bool $temporarily = false, int $expire = 0): bool
        {
            if (!self::$credentialsValid || $name === '' || $password === '') {
                return false;
            }

            $this->uid = self::$loginUid;

            return true;
        }

        public static function reset(): void
        {
            self::$loggedIn = true;
            self::$administrator = true;
            self::$credentialsValid = true;
            self::$loginUid = 1;
        }
    }

    final class Upload
    {
        public const UPLOAD_DIR = '/usr/uploads';

        /**
         * @param array<string, mixed> $file
         * @return array<string, mixed>|false
         */
        public static function uploadHandle(array $file): array|false
        {
            $name = isset($file['name']) && is_string($file['name']) ? $file['name'] : '';
            if ($name === '') {
                return false;
            }

            return [
                'name' => $name,
                'path' => '/usr/uploads/2026/11/media.png',
                'size' => isset($file['size']) && is_numeric($file['size']) ? (int) $file['size'] : 0,
                'type' => 'png',
                'mime' => 'image/png',
            ];
        }

        public static function attachmentHandle(\Typecho\Config $attachment): string
        {
            return 'https://example.test' . (string) $attachment->path;
        }
    }
}

namespace Utils {
    final class Helper
    {
        public static function addRoute(string $name, string $pattern, string $widget, string $action): void
        {
        }

        public static function removeRoute(string $name): void
        {
        }
    }

    final class Markdown
    {
        public static function convert(string $text): string
        {
            return $text;
        }
    }
}

namespace {
    final class Helper
    {
        public static ?object $optionsOverride = null;

        public static function options(): object
        {
            if (self::$optionsOverride !== null) {
                return self::$optionsOverride;
            }

            $options = new \stdClass();
            $options->siteUrl = 'https://example.test/';
            $options->index = 'https://example.test/';
            $options->themeUrl = 'https://example.test/usr/themes/ttdf';
            $options->theme = 'Theme';
            $options->title = 'Example';
            $options->description = 'Example site';
            $options->version = '1.3.0';
            $options->routingTable = [0 => []];

            return $options;
        }
    }
}

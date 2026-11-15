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
    }

    final class Db
    {
        public const SORT_ASC = 'ASC';
        public const SORT_DESC = 'DESC';

        /** @var (callable(Query): array<int, array<string, mixed>>)|null */
        public static $fetcher = null;

        /** @var list<Query> */
        public static array $executed = [];

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
            return new Query();
        }

        public function update(string $table): Query
        {
            return new Query();
        }

        public function delete(string $table): Query
        {
            return new Query();
        }

        public function query(string|Query $query): mixed
        {
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
            self::$executed = [];
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

        public static function reset(): void
        {
            self::$loggedIn = true;
            self::$administrator = true;
        }
    }

    final class Upload
    {
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

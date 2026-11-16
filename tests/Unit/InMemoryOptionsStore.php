<?php

declare(strict_types=1);

namespace TTDF\Tests\Unit;

use TTDF\Theme\OptionsStore;

final class InMemoryOptionsStore implements OptionsStore
{
    /** @var array<string, string> */
    private array $data = [];

    public function __construct(array $data = [])
    {
        $this->data = $data;
    }

    public function get(string $name): ?string
    {
        return $this->data[$name] ?? null;
    }

    public function set(string $name, string $value): void
    {
        $this->data[$name] = $value;
    }

    public function delete(string $name): void
    {
        unset($this->data[$name]);
    }

    public function all(): array
    {
        return $this->data;
    }

    public function exists(string $name): bool
    {
        return array_key_exists($name, $this->data);
    }

    public function install(): void
    {
    }
}

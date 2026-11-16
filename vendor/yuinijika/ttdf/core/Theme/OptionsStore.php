<?php

declare(strict_types=1);

namespace TTDF\Theme;

interface OptionsStore
{
    public function get(string $name): ?string;

    public function set(string $name, string $value): void;

    public function delete(string $name): void;

    /**
     * @return array<string, string>
     */
    public function all(): array;

    public function exists(string $name): bool;

    public function install(): void;
}

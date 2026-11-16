<?php

declare(strict_types=1);

namespace TTDF\Theme;

interface OptionsStore
{
    /**
     * Physical row name for a logical document name.
     *
     * The dedicated TTDF table stores the name verbatim; the Typecho options
     * store prefixes it with `ttdf:` so it cannot collide with core options.
     */
    public function key(string $name): string;

    /**
     * Longest row name the backing column accepts.
     */
    public function maxNameLength(): int;

    /**
     * Logical storage table name, used by diagnostics.
     */
    public function table(): string;

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

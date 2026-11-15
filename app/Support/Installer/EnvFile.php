<?php

declare(strict_types=1);

namespace App\Support\Installer;

use RuntimeException;

/**
 * Reads and edits a .env file in place.
 *
 * Only the lines for the keys being set are touched: comments, blank lines
 * and the order of everything else survive, so the file stays the one the
 * developer recognises from .env.example.
 */
final readonly class EnvFile
{
    public function __construct(private string $path) {}

    public function path(): string
    {
        return $this->path;
    }

    public function exists(): bool
    {
        return is_file($this->path);
    }

    public function createFrom(string $template): void
    {
        if (! is_file($template)) {
            throw new RuntimeException("Cannot create {$this->path}: {$template} is missing.");
        }

        if (! copy($template, $this->path)) {
            throw new RuntimeException("Cannot write {$this->path}. Check the directory permissions.");
        }
    }

    public function get(string $key, ?string $default = null): ?string
    {
        if (! $this->exists()) {
            return $default;
        }

        if (preg_match($this->pattern($key), (string) file_get_contents($this->path), $match) !== 1) {
            return $default;
        }

        return $this->unquote($match[1]);
    }

    public function has(string $key): bool
    {
        return filled($this->get($key));
    }

    /**
     * @param  array<string, string|int|bool|null>  $values
     */
    public function set(array $values): void
    {
        $contents = $this->exists() ? (string) file_get_contents($this->path) : '';

        foreach ($values as $key => $value) {
            $line = $key.'='.$this->quote($value);

            if (preg_match($this->pattern($key), $contents) === 1) {
                // A callback, not a replacement string: a password may
                // contain "$1" or a backslash, which preg_replace would
                // interpret instead of writing.
                $contents = (string) preg_replace_callback($this->pattern($key), fn (): string => $line, $contents, 1);

                continue;
            }

            $contents = rtrim($contents, "\n")."\n".$line."\n";
        }

        if (file_put_contents($this->path, $contents, LOCK_EX) === false) {
            throw new RuntimeException("Cannot write {$this->path}. Check the file permissions.");
        }
    }

    private function pattern(string $key): string
    {
        return '/^'.preg_quote($key, '/').'=(.*)$/m';
    }

    private function quote(string|int|bool|null $value): string
    {
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        $value = (string) $value;

        // Quote anything a dotenv parser could read differently from how
        // it was typed: spaces, comment markers, quotes, variable
        // references. "=" stays bare: base64 keys end in it, and
        // `php artisan key:generate` only finds an unquoted APP_KEY.
        if ($value === '' || preg_match('/^[A-Za-z0-9_.:\/@+=-]+$/', $value) === 1) {
            return $value;
        }

        return '"'.addcslashes($value, '"\\$').'"';
    }

    private function unquote(string $value): string
    {
        $value = trim($value);

        if (strlen($value) >= 2 && $value[0] === '"' && str_ends_with($value, '"')) {
            return stripcslashes(substr($value, 1, -1));
        }

        if (strlen($value) >= 2 && $value[0] === "'" && str_ends_with($value, "'")) {
            return substr($value, 1, -1);
        }

        // An unquoted value ends where an inline comment starts.
        return trim((string) preg_replace('/\s+#.*$/', '', $value));
    }
}

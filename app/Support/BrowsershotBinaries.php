<?php

namespace App\Support;

class BrowsershotBinaries
{
    /**
     * @return list<string>
     */
    public static function chromeCandidates(): array
    {
        return array_values(array_unique(array_filter([
            self::configString('browsershot.chrome_path'),
            '/usr/bin/chromium',
            '/usr/bin/chromium-browser',
            '/usr/bin/google-chrome',
            '/usr/bin/google-chrome-stable',
            '/snap/bin/chromium',
        ], fn (?string $path) => is_string($path) && $path !== '')));
    }

    /**
     * @return list<string>
     */
    public static function nodeCandidates(): array
    {
        return array_values(array_unique(array_filter([
            self::configString('browsershot.node_binary'),
            '/usr/bin/node',
            '/usr/local/bin/node',
        ], fn (?string $path) => is_string($path) && $path !== '')));
    }

    /**
     * @return list<string>
     */
    public static function npmCandidates(): array
    {
        return array_values(array_unique(array_filter([
            self::configString('browsershot.npm_binary'),
            '/usr/bin/npm',
            '/usr/local/bin/npm',
        ], fn (?string $path) => is_string($path) && $path !== '')));
    }

    /**
     * @return list<string>
     */
    public static function nodeModuleCandidates(): array
    {
        return array_values(array_unique(array_filter([
            self::configString('browsershot.node_module_path'),
            base_path('node_modules'),
            '/usr/lib/node_modules',
            '/usr/local/lib/node_modules',
        ], fn (?string $path) => is_string($path) && $path !== '')));
    }

    public static function resolveChromePath(): ?string
    {
        return self::firstExistingExecutable(self::chromeCandidates());
    }

    public static function resolveNodeBinary(): ?string
    {
        return self::firstExistingExecutable(self::nodeCandidates());
    }

    public static function resolveNpmBinary(): ?string
    {
        return self::firstExistingExecutable(self::npmCandidates());
    }

    public static function resolveNodeModulePath(): ?string
    {
        foreach (self::nodeModuleCandidates() as $path) {
            if (is_dir($path)) {
                return $path;
            }
        }

        return null;
    }

    /**
     * @param  list<string>  $paths
     */
    public static function firstExistingExecutable(array $paths): ?string
    {
        foreach ($paths as $path) {
            if (is_file($path) && is_executable($path)) {
                return $path;
            }
        }

        return null;
    }

    private static function configString(string $key): ?string
    {
        $value = config($key);
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed !== '' ? $trimmed : null;
    }
}

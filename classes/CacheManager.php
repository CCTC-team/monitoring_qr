<?php

namespace CCTC\MonitoringQRModule\Classes;

// Provides in-memory caching for expensive operations within a single request
class CacheManager
{
    private static array $cache = [];
    private static array $timestamps = [];

    // Default TTL in seconds (5 minutes)
    private const DEFAULT_TTL = 300;

    // Generates a cache key from the given parameters
    public static function makeKey(string $prefix, ...$params): string
    {
        return $prefix . '_' . md5(serialize($params));
    }

    // Gets a value from cache, returns null if not found or expired
    public static function get(string $key)
    {
        if (!isset(self::$cache[$key])) {
            return null;
        }

        // Check if expired
        if (isset(self::$timestamps[$key]) &&
            (time() - self::$timestamps[$key]) > self::DEFAULT_TTL) {
            unset(self::$cache[$key], self::$timestamps[$key]);
            return null;
        }

        return self::$cache[$key];
    }

    // Sets a value in cache
    public static function set(string $key, $value): void
    {
        self::$cache[$key] = $value;
        self::$timestamps[$key] = time();
    }

    // Gets a value from cache, or computes and stores it using the callback if not found
    public static function remember(string $key, callable $callback)
    {
        $cached = self::get($key);
        if ($cached !== null) {
            return $cached;
        }

        $value = $callback();
        self::set($key, $value);
        return $value;
    }

    // Clears a specific key from cache
    public static function forget(string $key): void
    {
        unset(self::$cache[$key], self::$timestamps[$key]);
    }

    // Clears all keys with a given prefix
    public static function forgetPrefix(string $prefix): void
    {
        foreach (array_keys(self::$cache) as $key) {
            if (str_starts_with($key, $prefix)) {
                unset(self::$cache[$key], self::$timestamps[$key]);
            }
        }
    }

    // Clears all cached values
    public static function flush(): void
    {
        self::$cache = [];
        self::$timestamps = [];
    }

    // Returns cache statistics for debugging
    public static function stats(): array
    {
        return [
            'count' => count(self::$cache),
            'keys' => array_keys(self::$cache),
        ];
    }
}

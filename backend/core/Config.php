<?php
declare(strict_types=1);

final class Config
{
    private static array $values = [];

    public static function load(array $values): void
    {
        self::$values = $values;
    }

    /** Lit une valeur avec une clé du type "db.host". */
    public static function get(string $key, mixed $default = null): mixed
    {
        $current = self::$values;
        foreach (explode('.', $key) as $part) {
            if (!is_array($current) || !array_key_exists($part, $current)) {
                return $default;
            }
            $current = $current[$part];
        }
        return $current;
    }
}

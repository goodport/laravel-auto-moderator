<?php

namespace Goodport\AutoModerator\Support;

use Goodport\AutoModerator\Exceptions\InvalidConfigurationException;

/**
 * Shared validation helpers for built-in check settings.
 */
final class SettingsGuard
{
    /**
     * Reject unexpected setting keys.
     *
     * @param  array<string, mixed>  $settings
     * @param  array<int, string>  $allowed
     */
    public static function guardKeys(string $check, array $settings, array $allowed): void
    {
        $unknown = array_values(array_diff(array_keys($settings), $allowed));

        if ($unknown !== []) {
            throw InvalidConfigurationException::invalidSetting(
                $check,
                sprintf('unknown settings: %s.', implode(', ', $unknown)),
            );
        }
    }

    /**
     * @param  array<string, mixed>  $settings
     * @return array<int, string>
     */
    public static function stringList(string $check, array $settings, string $key, bool $nonEmpty): array
    {
        $value = $settings[$key] ?? null;

        if (! is_array($value) || ! array_is_list($value)) {
            throw InvalidConfigurationException::invalidSetting(
                $check.'.'.$key,
                'must be a list of non-empty strings.',
            );
        }

        $result = [];

        foreach ($value as $item) {
            if (! is_string($item) || trim($item) === '') {
                throw InvalidConfigurationException::invalidSetting(
                    $check.'.'.$key,
                    'must contain non-empty strings.',
                );
            }

            if (! mb_check_encoding($item, 'UTF-8')) {
                throw InvalidConfigurationException::invalidSetting(
                    $check.'.'.$key,
                    'must contain valid UTF-8 strings.',
                );
            }

            $result[] = $item;
        }

        if ($nonEmpty && $result === []) {
            throw InvalidConfigurationException::invalidSetting(
                $check.'.'.$key,
                'must not be empty.',
            );
        }

        if (count($result) !== count(array_unique($result))) {
            throw InvalidConfigurationException::invalidSetting(
                $check.'.'.$key,
                'must not contain duplicates.',
            );
        }

        return $result;
    }

    /**
     * @param  array<string, mixed>  $settings
     */
    public static function bool(string $check, array $settings, string $key, bool $default): bool
    {
        $value = $settings[$key] ?? $default;

        if (! is_bool($value)) {
            throw InvalidConfigurationException::invalidSetting($check.'.'.$key, 'must be a boolean.');
        }

        return $value;
    }

    /**
     * @param  array<string, mixed>  $settings
     */
    public static function integer(string $check, array $settings, string $key, int $default, int $min = PHP_INT_MIN): int
    {
        $value = $settings[$key] ?? $default;

        if (! is_int($value) || $value < $min) {
            throw InvalidConfigurationException::invalidSetting(
                $check.'.'.$key,
                sprintf('must be an integer greater than or equal to %d.', $min),
            );
        }

        return $value;
    }

    /**
     * @param  array<string, mixed>  $settings
     */
    public static function ratio(string $check, array $settings, string $key, float $default): float
    {
        $value = $settings[$key] ?? $default;

        if (! is_int($value) && ! is_float($value)) {
            throw InvalidConfigurationException::invalidSetting(
                $check.'.'.$key,
                'must be a number between 0 and 1.',
            );
        }

        $value = (float) $value;

        if ($value < 0.0 || $value > 1.0) {
            throw InvalidConfigurationException::invalidSetting(
                $check.'.'.$key,
                'must be between 0 and 1.',
            );
        }

        return $value;
    }

    /**
     * @param  array<string, mixed>  $settings
     */
    public static function optionalString(string $check, array $settings, string $key): ?string
    {
        $value = $settings[$key] ?? null;

        if ($value === null) {
            return null;
        }

        if (! is_string($value) || trim($value) === '') {
            throw InvalidConfigurationException::invalidSetting(
                $check.'.'.$key,
                'must be a non-empty string or null.',
            );
        }

        return $value;
    }

    /**
     * @param  array<string, mixed>  $settings
     * @return array<string, string>
     */
    public static function stringMap(string $check, array $settings, string $key, bool $nonEmpty): array
    {
        $value = $settings[$key] ?? null;

        if (! is_array($value) || ($nonEmpty && $value === [])) {
            throw InvalidConfigurationException::invalidSetting(
                $check.'.'.$key,
                'must be a non-empty map of strings.',
            );
        }

        $result = [];

        foreach ($value as $mapKey => $item) {
            if (! is_string($mapKey) || trim($mapKey) === '') {
                throw InvalidConfigurationException::invalidSetting(
                    $check.'.'.$key,
                    'keys must be non-empty strings.',
                );
            }

            if (! is_string($item) || trim($item) === '') {
                throw InvalidConfigurationException::invalidSetting(
                    $check.'.'.$key,
                    'values must be non-empty strings.',
                );
            }

            if (! mb_check_encoding($item, 'UTF-8')) {
                throw InvalidConfigurationException::invalidSetting(
                    $check.'.'.$key,
                    'values must be valid UTF-8.',
                );
            }

            $result[$mapKey] = $item;
        }

        return $result;
    }

    /**
     * @param  array<string, mixed>  $settings
     * @return array<string, mixed>
     */
    public static function arrayValue(string $check, array $settings, string $key): array
    {
        $value = $settings[$key] ?? null;

        if (! is_array($value)) {
            throw InvalidConfigurationException::invalidSetting(
                $check.'.'.$key,
                'must be an object.',
            );
        }

        return $value;
    }
}

<?php

namespace Goodport\AutoModerator\Support;

/**
 * Merges registry and profile settings.
 *
 * Associative maps are merged recursively so an application can add one
 * alphabet without repeating the whole map. Lists are replaced as a whole so
 * `fields`, `words`, and `languages` keep the profile value.
 */
final class ConfigMerger
{
    /**
     * @param  array<string, mixed>  $base
     * @param  array<string, mixed>  $override
     * @return array<string, mixed>
     */
    public static function merge(array $base, array $override): array
    {
        foreach ($override as $key => $value) {
            if (
                is_array($value)
                && isset($base[$key])
                && is_array($base[$key])
                && ! array_is_list($value)
                && ! array_is_list($base[$key])
            ) {
                $base[$key] = self::merge($base[$key], $value);

                continue;
            }

            $base[$key] = $value;
        }

        return $base;
    }
}

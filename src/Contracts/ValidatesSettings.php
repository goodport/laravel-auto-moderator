<?php

namespace Goodport\AutoModerator\Contracts;

/**
 * Optional contract for checks that validate their own settings.
 *
 * The profile resolver calls this method while the profile is being built, so
 * configuration mistakes fail before any check runs. Custom checks may implement
 * the contract to get the same early validation.
 */
interface ValidatesSettings
{
    /**
     * Validate the merged settings for the given check.
     *
     * @param  array<string, mixed>  $settings
     */
    public function validateSettings(string $check, array $settings): void;
}

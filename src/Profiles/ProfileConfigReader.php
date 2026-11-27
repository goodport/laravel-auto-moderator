<?php

namespace Goodport\AutoModerator\Profiles;

use Goodport\AutoModerator\Exceptions\InvalidConfigurationException;
use Illuminate\Contracts\Config\Repository;

/**
 * Reads and validates explicit and fallback profile configuration.
 *
 * Check registration, settings merging, and class resolution belong to the
 * check factory. This reader only guarantees that the profile itself is
 * well-formed.
 */
final class ProfileConfigReader
{
    public function __construct(private readonly Repository $config) {}

    public function read(string $profile): ProfileConfig
    {
        if (trim($profile) === '') {
            throw InvalidConfigurationException::unknownProfile($profile);
        }

        $globalEnabled = $this->readGlobalEnabled();
        $defaults = $this->readDefaults();

        if ($this->config->has("auto-moderator.profiles.{$profile}")) {
            return $this->readExplicitProfile($profile, $globalEnabled, $defaults);
        }

        return $this->readFallbackProfile($profile, $globalEnabled, $defaults);
    }

    private function readGlobalEnabled(): bool
    {
        $globalEnabled = $this->config->get('auto-moderator.enabled');

        if (! is_bool($globalEnabled)) {
            throw InvalidConfigurationException::invalidSetting('enabled', 'must be a boolean.');
        }

        return $globalEnabled;
    }

    /**
     * @return array{stop_on_reject: bool}
     */
    private function readDefaults(): array
    {
        $defaults = $this->config->get('auto-moderator.defaults', []);

        if (! is_array($defaults)) {
            throw InvalidConfigurationException::invalidSetting('defaults', 'must be an object.');
        }

        $unknown = array_diff(array_keys($defaults), ['stop_on_reject']);

        if ($unknown !== []) {
            throw InvalidConfigurationException::invalidSetting(
                'defaults',
                'contains unknown keys: '.implode(', ', $unknown).'.',
            );
        }

        $stopOnReject = $defaults['stop_on_reject'] ?? false;

        if (! is_bool($stopOnReject)) {
            throw InvalidConfigurationException::invalidSetting('defaults.stop_on_reject', 'must be a boolean.');
        }

        return ['stop_on_reject' => $stopOnReject];
    }

    /**
     * @param  array{stop_on_reject: bool}  $defaults
     */
    private function readExplicitProfile(string $profile, bool $globalEnabled, array $defaults): ProfileConfig
    {
        $profileConfig = $this->config->get("auto-moderator.profiles.{$profile}");

        if (! is_array($profileConfig)) {
            throw InvalidConfigurationException::invalidProfile($profile, 'must be an object.');
        }

        $unknown = array_diff(array_keys($profileConfig), ['enabled', 'stop_on_reject', 'checks']);

        if ($unknown !== []) {
            throw InvalidConfigurationException::invalidProfile(
                $profile,
                'contains unknown keys: '.implode(', ', $unknown).'.',
            );
        }

        $profileEnabled = $profileConfig['enabled'] ?? true;

        if (! is_bool($profileEnabled)) {
            throw InvalidConfigurationException::invalidProfile($profile, '`enabled` must be a boolean.');
        }

        $stopOnReject = $profileConfig['stop_on_reject'] ?? $defaults['stop_on_reject'];

        if (! is_bool($stopOnReject)) {
            throw InvalidConfigurationException::invalidProfile($profile, '`stop_on_reject` must be a boolean.');
        }

        $entries = $profileConfig['checks'] ?? null;

        if (! is_array($entries) || ! array_is_list($entries) || $entries === []) {
            throw InvalidConfigurationException::invalidProfile($profile, '`checks` must be a non-empty list.');
        }

        $checks = [];
        $seen = [];

        foreach ($entries as $index => $entry) {
            if (! is_array($entry)) {
                throw InvalidConfigurationException::invalidProfile(
                    $profile,
                    sprintf('check entry #%d must be an object.', $index),
                );
            }

            $unknown = array_diff(array_keys($entry), ['check', 'enabled', 'settings']);

            if ($unknown !== []) {
                throw InvalidConfigurationException::invalidProfile(
                    $profile,
                    sprintf('check entry #%d contains unknown keys: %s.', $index, implode(', ', $unknown)),
                );
            }

            $check = $entry['check'] ?? null;

            if (! is_string($check) || trim($check) === '') {
                throw InvalidConfigurationException::invalidProfile(
                    $profile,
                    sprintf('check entry #%d must contain a non-empty `check` key.', $index),
                );
            }

            $check = trim($check);

            if (in_array($check, $seen, true)) {
                throw InvalidConfigurationException::invalidProfile(
                    $profile,
                    sprintf('check "%s" is listed more than once.', $check),
                );
            }

            $seen[] = $check;

            $enabled = $entry['enabled'] ?? true;

            if (! is_bool($enabled)) {
                throw InvalidConfigurationException::invalidProfile(
                    $profile,
                    sprintf('`enabled` for check "%s" must be a boolean.', $check),
                );
            }

            $settings = $entry['settings'] ?? [];

            if (! is_array($settings)) {
                throw InvalidConfigurationException::invalidProfile(
                    $profile,
                    sprintf('`settings` for check "%s" must be an object.', $check),
                );
            }

            $checks[] = [
                'check' => $check,
                'enabled' => $enabled,
                'settings' => $settings,
            ];
        }

        return new ProfileConfig(
            key: $profile,
            enabled: $globalEnabled && $profileEnabled,
            stopOnReject: $stopOnReject,
            checks: $checks,
        );
    }

    /**
     * @param  array{stop_on_reject: bool}  $defaults
     */
    private function readFallbackProfile(string $profile, bool $globalEnabled, array $defaults): ProfileConfig
    {
        if (! $this->config->has("ai-moderator.checks.{$profile}")) {
            throw InvalidConfigurationException::unknownProfile($profile);
        }

        $fallbackCheck = $this->readFallbackCheck();

        if (! $this->config->has("auto-moderator.checks.{$fallbackCheck}")) {
            throw InvalidConfigurationException::fallbackCheckMissing($fallbackCheck);
        }

        return new ProfileConfig(
            key: $profile,
            enabled: $globalEnabled,
            stopOnReject: $defaults['stop_on_reject'],
            checks: [
                [
                    'check' => $fallbackCheck,
                    'enabled' => true,
                    'settings' => ['check' => $profile],
                ],
            ],
        );
    }

    private function readFallbackCheck(): string
    {
        $fallbackCheck = $this->config->get('auto-moderator.fallback.ai_check');

        if (! is_string($fallbackCheck) || trim($fallbackCheck) === '') {
            throw InvalidConfigurationException::invalidSetting(
                'fallback.ai_check',
                'must be a non-empty registered check key.',
            );
        }

        return trim($fallbackCheck);
    }
}

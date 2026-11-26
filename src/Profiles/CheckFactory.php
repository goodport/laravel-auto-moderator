<?php

namespace Goodport\AutoModerator\Profiles;

use Goodport\AutoModerator\Contracts\ModerationCheck;
use Goodport\AutoModerator\Contracts\ValidatesSettings;
use Goodport\AutoModerator\Exceptions\InvalidConfigurationException;
use Goodport\AutoModerator\Support\ConfigMerger;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Container\Container;
use Throwable;

/**
 * Resolves check registry entries into executable checks.
 */
final class CheckFactory
{
    public function __construct(
        private readonly Repository $config,
        private readonly Container $container,
    ) {}

    /**
     * Read and validate a registry entry.
     *
     * @return array{class: string, settings: array<string, mixed>}
     */
    public function definition(string $check): array
    {
        $entry = $this->config->get("auto-moderator.checks.{$check}");

        if (is_string($entry)) {
            $entry = ['class' => $entry];
        }

        if (! is_array($entry)) {
            throw InvalidConfigurationException::unknownCheck($check);
        }

        $unknown = array_diff(array_keys($entry), ['class', 'settings']);

        if ($unknown !== []) {
            throw InvalidConfigurationException::invalidCheck(
                $check,
                'registry entry contains unknown keys: '.implode(', ', $unknown).'.',
            );
        }

        $class = $entry['class'] ?? null;

        if (! is_string($class) || $class === '') {
            throw InvalidConfigurationException::invalidCheck(
                $check,
                'registry entry must contain a class name.',
            );
        }

        $settings = $entry['settings'] ?? [];

        if (! is_array($settings)) {
            throw InvalidConfigurationException::invalidCheck(
                $check,
                'registry `settings` must be an object.',
            );
        }

        return ['class' => $class, 'settings' => $settings];
    }

    /**
     * Build a check with merged settings.
     *
     * @param  array<string, mixed>  $registrySettings
     * @param  array<string, mixed>  $overrides
     * @return array{instance: ModerationCheck, settings: array<string, mixed>}
     */
    public function make(string $check, string $class, array $registrySettings, array $overrides): array
    {
        $settings = $this->resolveServiceReference(
            $check,
            ConfigMerger::merge($registrySettings, $overrides),
        );

        $instance = $this->resolveInstance($check, $class);

        if ($instance instanceof ValidatesSettings) {
            $instance->validateSettings($check, $settings);
        }

        return ['instance' => $instance, 'settings' => $settings];
    }

    /**
     * Replaces a `service` name with the matching service settings.
     *
     * @param  array<string, mixed>  $settings
     * @return array<string, mixed>
     */
    private function resolveServiceReference(string $check, array $settings): array
    {
        if (! array_key_exists('service', $settings)) {
            return $settings;
        }

        if (is_array($settings['service'])) {
            return $settings;
        }

        if (! is_string($settings['service']) || trim($settings['service']) === '') {
            throw InvalidConfigurationException::invalidCheck(
                $check,
                '`service` must be a service name or an object.',
            );
        }

        $serviceSettings = $this->config->get("auto-moderator.services.{$settings['service']}");

        if (! is_array($serviceSettings)) {
            throw InvalidConfigurationException::unknownService($settings['service']);
        }

        $settings['service'] = $serviceSettings;

        return $settings;
    }

    private function resolveInstance(string $check, string $class): ModerationCheck
    {
        try {
            $instance = $this->container->make($class);
        } catch (Throwable $exception) {
            throw InvalidConfigurationException::invalidCheck(
                $check,
                sprintf('unable to resolve class "%s": %s', $class, $exception->getMessage()),
                $exception,
            );
        }

        if (! $instance instanceof ModerationCheck) {
            throw InvalidConfigurationException::invalidCheck(
                $check,
                sprintf('class "%s" must implement %s.', $class, ModerationCheck::class),
            );
        }

        return $instance;
    }
}

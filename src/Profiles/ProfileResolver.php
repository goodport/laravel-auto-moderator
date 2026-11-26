<?php

namespace Goodport\AutoModerator\Profiles;

/**
 * Builds an executable profile from validated configuration.
 */
final class ProfileResolver
{
    public function __construct(
        private readonly ProfileConfigReader $reader,
        private readonly CheckFactory $factory,
    ) {}

    public function resolve(string $profile): ResolvedProfile
    {
        $config = $this->reader->read($profile);
        $checks = [];

        foreach ($config->checks as $entry) {
            $definition = $this->factory->definition($entry['check']);

            if (! $config->enabled || ! $entry['enabled']) {
                $checks[] = new ResolvedCheck($entry['check'], false, null, []);

                continue;
            }

            $resolved = $this->factory->make(
                check: $entry['check'],
                class: $definition['class'],
                registrySettings: $definition['settings'],
                overrides: $entry['settings'],
            );

            $checks[] = new ResolvedCheck(
                key: $entry['check'],
                enabled: true,
                instance: $resolved['instance'],
                settings: $resolved['settings'],
            );
        }

        return new ResolvedProfile(
            key: $config->key,
            enabled: $config->enabled,
            stopOnReject: $config->stopOnReject,
            checks: $checks,
        );
    }
}

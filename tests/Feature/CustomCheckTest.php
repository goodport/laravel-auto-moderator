<?php

namespace Goodport\AutoModerator\Tests\Feature;

use Goodport\AutoModerator\Exceptions\InvalidConfigurationException;
use Goodport\AutoModerator\Facades\AutoModerator;
use Goodport\AutoModerator\Moderation\AuditContext;
use Goodport\AutoModerator\Tests\Fixtures\DependentCheck;
use Goodport\AutoModerator\Tests\Fixtures\DependentService;
use Goodport\AutoModerator\Tests\Fixtures\RecordingCheck;
use Goodport\AutoModerator\Tests\Fixtures\ValidatingCheck;
use Goodport\AutoModerator\Tests\TestCase;

final class CustomCheckTest extends TestCase
{
    /**
     * A custom check receives fields, language, settings, and audit context.
     */
    public function test_custom_check_receives_context(): void
    {
        $this->setCheck('record', RecordingCheck::class);
        $this->setProfile('post', [
            'checks' => [
                [
                    'check' => 'record',
                    'settings' => ['custom' => 'value'],
                ],
            ],
        ]);
        $audit = new AuditContext(actorType: 'user', actorId: '1');

        AutoModerator::profile('post')
            ->input(['body' => 'Hello'])
            ->language('en')
            ->audit($audit)
            ->run();

        $context = RecordingCheck::$contexts[0];

        $this->assertSame('record', $context->check);
        $this->assertSame(['body' => 'Hello'], $context->fields);
        $this->assertSame('en', $context->language);
        $this->assertSame(['custom' => 'value'], $context->settings);
        $this->assertSame($audit, $context->audit);
    }

    /**
     * Registry defaults are merged with profile overrides.
     */
    public function test_settings_merge_registry_defaults_with_profile_overrides(): void
    {
        $this->setCheck('record', RecordingCheck::class, [
            'default' => 'value',
            'nested' => ['a' => 1, 'b' => 2],
        ]);
        $this->setProfile('post', [
            'checks' => [
                [
                    'check' => 'record',
                    'settings' => [
                        'nested' => ['b' => 3, 'c' => 4],
                    ],
                ],
            ],
        ]);

        AutoModerator::profile('post')->input([])->run();

        $this->assertSame(
            [
                'default' => 'value',
                'nested' => ['a' => 1, 'b' => 3, 'c' => 4],
            ],
            RecordingCheck::$contexts[0]->settings,
        );
    }

    /**
     * Custom settings are validated while the profile is being built.
     */
    public function test_custom_check_validates_settings_before_running(): void
    {
        $this->setCheck('validating', ValidatingCheck::class);
        $this->setProfile('post', [
            'checks' => [
                ['check' => 'validating'],
            ],
        ]);

        $this->expectException(InvalidConfigurationException::class);

        AutoModerator::profile('post')->input([])->run();
    }

    /**
     * Custom checks are resolved through the container.
     */
    public function test_custom_check_is_resolved_from_container(): void
    {
        $this->app->instance(DependentService::class, new DependentService('bound-service'));
        $this->setCheck('dependent', DependentCheck::class);
        $this->setProfile('post', [
            'checks' => [
                ['check' => 'dependent'],
            ],
        ]);

        $report = AutoModerator::profile('post')->input([])->run();

        $this->assertSame(
            'bound-service',
            $report->results()['dependent']->details['service'],
        );
    }
}

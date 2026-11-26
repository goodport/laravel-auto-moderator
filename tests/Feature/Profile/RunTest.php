<?php

namespace Goodport\AutoModerator\Tests\Feature\Profile;

use Goodport\AutoModerator\Facades\AutoModerator;
use Goodport\AutoModerator\Moderation\ModerationContext;
use Goodport\AutoModerator\Moderation\ModerationState;
use Goodport\AutoModerator\Tests\Fixtures\PassingCheck;
use Goodport\AutoModerator\Tests\Fixtures\RecordingCheck;
use Goodport\AutoModerator\Tests\Fixtures\RejectingCheck;
use Goodport\AutoModerator\Tests\Fixtures\ThrowingCheck;
use Goodport\AutoModerator\Tests\Fixtures\WarningCheck;
use Goodport\AutoModerator\Tests\TestCase;

final class RunTest extends TestCase
{
    /**
     * A profile passes when every enabled check passes.
     */
    public function test_passes_when_all_checks_pass(): void
    {
        $this->setCheck('pass', PassingCheck::class);
        $this->setProfile('post', [
            'checks' => [
                ['check' => 'pass'],
            ],
        ]);

        $report = AutoModerator::profile('post')->input(['body' => 'Hello'])->run();

        $this->assertTrue($report->passed());
        $this->assertSame(ModerationState::Passed, $report->state());
        $this->assertArrayHasKey('pass', $report->results());
    }

    /**
     * One rejecting check rejects the whole profile.
     */
    public function test_rejects_when_a_check_rejects(): void
    {
        $this->setCheck('reject', RejectingCheck::class);
        $this->setProfile('post', [
            'checks' => [
                ['check' => 'reject'],
            ],
        ]);

        $report = AutoModerator::profile('post')->input(['body' => 'Hello'])->run();

        $this->assertTrue($report->rejected());
        $this->assertFalse($report->passed());
        $this->assertSame(ModerationState::Rejected, $report->state());
    }

    /**
     * A check exception becomes an undecided result, not a skip.
     */
    public function test_undecided_when_a_check_throws(): void
    {
        $this->setCheck('throw', ThrowingCheck::class);
        $this->setProfile('post', [
            'checks' => [
                ['check' => 'throw'],
            ],
        ]);

        $report = AutoModerator::profile('post')->input(['body' => 'Hello'])->run();

        $this->assertNull($report->passed());
        $this->assertTrue($report->undecided());
        $this->assertSame(ModerationState::Undecided, $report->state());
        $this->assertSame('Fixture failure.', $report->results()['throw']->error);
    }

    /**
     * A profile where every check is disabled reports skipped.
     */
    public function test_all_checks_skipped(): void
    {
        $this->setCheck('pass', PassingCheck::class);
        $this->setProfile('post', [
            'checks' => [
                ['check' => 'pass', 'enabled' => false],
            ],
        ]);

        $report = AutoModerator::profile('post')->input([])->run();

        $this->assertTrue($report->allSkipped());
        $this->assertNull($report->passed());
        $this->assertSame(ModerationState::Skipped, $report->state());
        $this->assertTrue($report->results()['pass']->skipped());
    }

    /**
     * Checks run in the exact order declared in the profile list.
     */
    public function test_runs_checks_in_profile_order(): void
    {
        $this->setCheck('first', RecordingCheck::class);
        $this->setCheck('second', RecordingCheck::class);
        $this->setProfile('post', [
            'checks' => [
                ['check' => 'second'],
                ['check' => 'first'],
            ],
        ]);

        $report = AutoModerator::profile('post')->input([])->run();

        $this->assertSame(
            ['second', 'first'],
            array_map(fn (ModerationContext $context): string => $context->check, RecordingCheck::$contexts),
        );
        $this->assertSame(['second', 'first'], array_keys($report->results()));
    }

    /**
     * stop_on_reject stops the pipeline after the first rejection.
     */
    public function test_stops_after_first_rejection(): void
    {
        $this->setCheck('reject', RejectingCheck::class);
        $this->setCheck('after', RecordingCheck::class);
        $this->setProfile('post', [
            'stop_on_reject' => true,
            'checks' => [
                ['check' => 'reject'],
                ['check' => 'after'],
            ],
        ]);

        $report = AutoModerator::profile('post')->input([])->run();

        $this->assertTrue($report->rejected());
        $this->assertCount(1, $report->results());
        $this->assertSame([], RecordingCheck::$contexts);
    }

    /**
     * A non-blocking finding is a warning and does not reject the report.
     */
    public function test_warning_does_not_reject(): void
    {
        $this->setCheck('warn', WarningCheck::class);
        $this->setProfile('post', [
            'checks' => [
                ['check' => 'warn'],
            ],
        ]);

        $report = AutoModerator::profile('post')->input([])->run();

        $this->assertTrue($report->passed());
        $this->assertSame([], $report->violations());
        $this->assertArrayHasKey('warn', $report->warnings());
    }

    /**
     * A disabled check is visible in the report as skipped.
     */
    public function test_disabled_check_is_reported_as_skipped(): void
    {
        $this->setCheck('pass', PassingCheck::class);
        $this->setCheck('skip', PassingCheck::class);
        $this->setProfile('post', [
            'checks' => [
                ['check' => 'pass'],
                ['check' => 'skip', 'enabled' => false],
            ],
        ]);

        $report = AutoModerator::profile('post')->input([])->run();

        $this->assertTrue($report->passed());
        $this->assertTrue($report->results()['pass']->passed());
        $this->assertTrue($report->results()['skip']->skipped());
    }

    /**
     * The global switch skips every check without running it.
     */
    public function test_global_switch_returns_all_skipped(): void
    {
        $this->app['config']->set('auto-moderator.enabled', false);
        $this->setCheck('pass', PassingCheck::class);
        $this->setProfile('post', [
            'checks' => [
                ['check' => 'pass'],
            ],
        ]);

        $report = AutoModerator::profile('post')->input([])->run();

        $this->assertTrue($report->allSkipped());
        $this->assertTrue($report->results()['pass']->skipped());
    }

    /**
     * A disabled profile skips every check.
     */
    public function test_profile_disable_returns_all_skipped(): void
    {
        $this->setCheck('pass', PassingCheck::class);
        $this->setProfile('post', [
            'enabled' => false,
            'checks' => [
                ['check' => 'pass'],
            ],
        ]);

        $report = AutoModerator::profile('post')->input([])->run();

        $this->assertTrue($report->allSkipped());
        $this->assertTrue($report->results()['pass']->skipped());
    }
}

<?php

namespace Goodport\AutoModerator\Tests\Feature\Profile;

use Goodport\AiModerator\Agents\ModerationAgent;
use Goodport\AutoModerator\Checks\Ai\AiModerationCheck;
use Goodport\AutoModerator\Exceptions\InvalidConfigurationException;
use Goodport\AutoModerator\Facades\AutoModerator;
use Goodport\AutoModerator\Tests\Fixtures\PassingCheck;
use Goodport\AutoModerator\Tests\Fixtures\RecordingCheck;
use Goodport\AutoModerator\Tests\Fixtures\RejectingCheck;
use Goodport\AutoModerator\Tests\TestCase;

final class FallbackTest extends TestCase
{
    /**
     * A missing profile falls back to a one-check AI profile.
     */
    public function test_fallback_profile_from_ai_check(): void
    {
        $this->setAiCheck('text_name', $this->aiCheckConfig());
        $this->approve();

        $report = AutoModerator::profile('text_name')
            ->input(['text' => 'Hello'])
            ->language('en')
            ->run();

        $result = $report->results()['ai'];

        $this->assertTrue($report->passed());
        $this->assertSame('text_name', $result->details['check']);
        $this->assertSame('passed', $result->details['result']['state']);
    }

    /**
     * The configured fallback check key is used.
     */
    public function test_fallback_profile_uses_configured_fallback_check(): void
    {
        $this->app['config']->set('auto-moderator.fallback.ai_check', 'ai_custom');
        $this->setCheck('ai_custom', AiModerationCheck::class);
        $this->setAiCheck('text_name', $this->aiCheckConfig());
        $this->approve();

        $report = AutoModerator::profile('text_name')
            ->input(['text' => 'Hello'])
            ->language('en')
            ->run();

        $this->assertTrue($report->passed());
        $this->assertSame('text_name', $report->results()['ai_custom']->details['check']);
    }

    /**
     * An explicit profile always wins over the fallback.
     */
    public function test_explicit_profile_overrides_fallback(): void
    {
        $this->setAiCheck('text_name', $this->aiCheckConfig());
        $this->setCheck('pass', PassingCheck::class);
        $this->setProfile('text_name', [
            'checks' => [
                ['check' => 'pass'],
            ],
        ]);

        $report = AutoModerator::profile('text_name')
            ->input(['text' => 'Hello'])
            ->language('en')
            ->run();

        $this->assertTrue($report->passed());
        $this->assertArrayHasKey('pass', $report->results());
        $this->assertArrayNotHasKey('ai', $report->results());
    }

    /**
     * An invalid explicit profile is an error, not a fallback.
     */
    public function test_explicit_invalid_profile_does_not_fall_back(): void
    {
        $this->setAiCheck('text_name', $this->aiCheckConfig());
        $this->setProfile('text_name', ['checks' => []]);

        $this->expectException(InvalidConfigurationException::class);

        AutoModerator::profile('text_name')
            ->input(['text' => 'Hello'])
            ->language('en')
            ->run();
    }

    /**
     * A missing profile without an AI check is unknown.
     */
    public function test_unknown_profile_without_ai_check_throws(): void
    {
        $this->expectException(InvalidConfigurationException::class);

        AutoModerator::profile('missing')->input([])->run();
    }

    /**
     * A missing fallback check is a configuration error.
     */
    public function test_fallback_check_missing_throws(): void
    {
        $this->app['config']->set('auto-moderator.fallback.ai_check', 'missing_ai');
        $this->setAiCheck('text_name', $this->aiCheckConfig());

        $this->expectException(InvalidConfigurationException::class);

        AutoModerator::profile('text_name')
            ->input(['text' => 'Hello'])
            ->language('en')
            ->run();
    }

    /**
     * A disabled AI check creates the fallback profile and returns skipped.
     */
    public function test_disabled_ai_check_falls_back_to_skipped(): void
    {
        $this->setAiCheck(
            'text_name',
            array_merge($this->aiCheckConfig(), ['enabled' => false]),
        );

        $report = AutoModerator::profile('text_name')
            ->input(['text' => 'Hello'])
            ->language('en')
            ->run();

        $result = $report->results()['ai'];

        $this->assertTrue($report->allSkipped());
        $this->assertTrue($result->skipped());
        $this->assertSame('text_name', $result->details['check']);
    }

    /**
     * Composite profiles keep their order and stop_on_reject behavior.
     */
    public function test_composite_profile_keeps_order_and_stop_on_reject(): void
    {
        $this->setAiCheck('text_name', $this->aiCheckConfig());
        $this->setCheck('reject', RejectingCheck::class);
        $this->setCheck('record', RecordingCheck::class);
        $this->setProfile('post', [
            'stop_on_reject' => true,
            'checks' => [
                ['check' => 'reject'],
                ['check' => 'record'],
            ],
        ]);

        $report = AutoModerator::profile('post')->input([])->run();

        $this->assertTrue($report->rejected());
        $this->assertCount(1, $report->results());
        $this->assertSame([], RecordingCheck::$contexts);
    }

    /**
     * @return array<string, mixed>
     */
    private function aiCheckConfig(): array
    {
        return [
            'kind' => 'verdict',
            'input' => [
                'fields' => [
                    'text' => ['type' => 'text', 'label' => 'Text', 'max_chars' => 4000],
                ],
            ],
            'instructions' => 'Reject bad names.',
            'violations' => [
                'bad' => ['severity' => 'high', 'blocking' => true, 'description' => 'Bad name'],
            ],
        ];
    }

    private function approve(): void
    {
        ModerationAgent::fake([
            ['verdict' => 'approve', 'reason' => 'Fine.', 'violations' => []],
        ]);
    }
}

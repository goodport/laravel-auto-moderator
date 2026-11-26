<?php

namespace Goodport\AutoModerator\Tests\Feature\Checks;

use Goodport\AiModerator\Agents\ModerationAgent;
use Goodport\AutoModerator\Exceptions\InvalidConfigurationException;
use Goodport\AutoModerator\Exceptions\InvalidInputException;
use Goodport\AutoModerator\Facades\AutoModerator;
use Goodport\AutoModerator\Moderation\AuditContext;
use Goodport\AutoModerator\Tests\TestCase;
use Illuminate\Support\Facades\DB;
use Laravel\Ai\Prompts\AgentPrompt;

final class AiTest extends TestCase
{
    /**
     * A passing AI policy passes the profile.
     */
    public function test_passes_when_policy_passes(): void
    {
        $this->configureAi();
        ModerationAgent::fake([
            ['verdict' => 'approve', 'reason' => 'Fine.', 'violations' => []],
        ]);

        $report = AutoModerator::profile('post')
            ->input(['body' => 'Hello'])
            ->language('en')
            ->run();

        $result = $report->results()['ai'];

        $this->assertTrue($report->passed());
        $this->assertSame('post', $result->details['policy']);
        $this->assertSame('passed', $result->details['checks']['post_body']['state']);
    }

    /**
     * AI reasons and violations are preserved in the package result.
     */
    public function test_rejects_with_violations(): void
    {
        $this->configureAi();
        ModerationAgent::fake([
            [
                'verdict' => 'reject',
                'reason' => 'Bad body.',
                'violations' => [['code' => 'bad', 'message' => 'Bad body.']],
            ],
        ]);

        $report = AutoModerator::profile('post')
            ->input(['body' => 'Hello'])
            ->language('en')
            ->run();

        $result = $report->results()['ai'];

        $this->assertTrue($report->rejected());
        $this->assertSame('bad', $result->findings[0]->code);
        $this->assertTrue($result->findings[0]->blocking);
        $this->assertSame('Bad body.', $result->reason);
        $this->assertSame('Bad body.', $result->details['checks']['post_body']['reason']);
    }

    /**
     * An inconsistent AI response becomes undecided.
     */
    public function test_undecided_when_policy_cannot_decide(): void
    {
        $this->configureAi();
        ModerationAgent::fake([
            ['verdict' => 'reject', 'reason' => 'Broken.', 'violations' => []],
        ]);

        $report = AutoModerator::profile('post')
            ->input(['body' => 'Hello'])
            ->language('en')
            ->run();

        $this->assertNull($report->passed());
        $this->assertTrue($report->undecided());
        $this->assertNotNull($report->results()['ai']->error);
    }

    /**
     * The AI package global switch produces skipped, not passed.
     */
    public function test_skipped_when_ai_package_switch_is_disabled(): void
    {
        $this->configureAi();
        $this->app['config']->set('ai-moderator.enabled', false);

        $report = AutoModerator::profile('post')
            ->input(['body' => 'Hello'])
            ->language('en')
            ->run();

        $this->assertTrue($report->allSkipped());
        $this->assertNull($report->passed());
        $this->assertTrue($report->results()['ai']->skipped());
    }

    /**
     * A policy where every AI check is disabled produces skipped.
     */
    public function test_skipped_when_all_ai_checks_are_disabled(): void
    {
        $this->configureAi();
        $this->app['config']->set('ai-moderator.checks.post_body.enabled', false);

        $report = AutoModerator::profile('post')
            ->input(['body' => 'Hello'])
            ->language('en')
            ->run();

        $this->assertTrue($report->allSkipped());
        $this->assertTrue($report->results()['ai']->skipped());
    }

    /**
     * Only declared fields and the run language reach the AI policy.
     */
    public function test_passes_language_and_selected_fields(): void
    {
        $this->configureAi();
        ModerationAgent::fake([
            ['verdict' => 'approve', 'reason' => 'Fine.', 'violations' => []],
        ]);

        AutoModerator::profile('post')
            ->input(['body' => 'Hello', 'title' => 'Ignored'])
            ->language('en')
            ->run();

        ModerationAgent::assertPrompted(function (AgentPrompt $prompt): bool {
            return str_contains($prompt->prompt, 'Hello')
                && ! str_contains($prompt->prompt, 'Ignored')
                && str_contains((string) $prompt->agent->instructions(), 'English');
        });
    }

    /**
     * Audit context is forwarded to the AI policy.
     */
    public function test_maps_audit_context(): void
    {
        $this->configureAi();
        ModerationAgent::fake([
            ['verdict' => 'approve', 'reason' => 'Fine.', 'violations' => []],
        ]);

        AutoModerator::profile('post')
            ->input(['body' => 'Hello'])
            ->language('en')
            ->audit(new AuditContext(
                actorType: 'user',
                actorId: '1',
                subjectType: 'post',
                subjectId: '2',
                requestUrl: 'https://example.com/posts/2',
            ))
            ->run();

        $audit = DB::table('ai_moderation_audits')->first();

        $this->assertNotNull($audit);
        $this->assertSame('post', $audit->policy);
        $this->assertSame('user', $audit->actor_type);
        $this->assertSame('1', $audit->actor_id);
        $this->assertSame('post', $audit->subject_type);
        $this->assertSame('2', $audit->subject_id);
        $this->assertSame('https://example.com/posts/2', $audit->request_url);
    }

    /**
     * A declared AI input field must exist.
     */
    public function test_missing_field_throws(): void
    {
        $this->configureAi();

        $this->expectException(InvalidInputException::class);

        AutoModerator::profile('post')
            ->input([])
            ->language('en')
            ->run();
    }

    /**
     * The AI check requires a language.
     */
    public function test_missing_language_throws(): void
    {
        $this->configureAi();

        $this->expectException(InvalidConfigurationException::class);

        AutoModerator::profile('post')
            ->input(['body' => 'Hello'])
            ->run();
    }

    /**
     * An unknown AI policy is a configuration error.
     */
    public function test_invalid_policy_throws(): void
    {
        $this->setProfile('post', [
            'checks' => [
                [
                    'check' => 'ai',
                    'settings' => [
                        'policy' => 'missing',
                        'fields' => ['body'],
                    ],
                ],
            ],
        ]);

        $this->expectException(InvalidConfigurationException::class);

        AutoModerator::profile('post')
            ->input(['body' => 'Hello'])
            ->language('en')
            ->run();
    }

    /**
     * The AI check does not accept a blocking override.
     */
    public function test_rejects_blocking_setting(): void
    {
        $this->configureAi();
        $this->app['config']->set('auto-moderator.profiles.post.checks.0.settings.blocking', true);

        $this->expectException(InvalidConfigurationException::class);

        AutoModerator::profile('post')
            ->input(['body' => 'Hello'])
            ->language('en')
            ->run();
    }

    private function configureAi(): void
    {
        $this->setAiCheck('post_body', [
            'kind' => 'verdict',
            'input' => [
                'fields' => [
                    'body' => ['type' => 'text', 'label' => 'Body', 'max_chars' => 4000],
                ],
            ],
            'instructions' => 'Reject bad content.',
            'violations' => [
                'bad' => ['severity' => 'high', 'blocking' => true, 'description' => 'Bad content'],
            ],
        ]);
        $this->setAiPolicy('post', ['checks' => ['post_body']]);
        $this->setProfile('post', [
            'checks' => [
                [
                    'check' => 'ai',
                    'settings' => [
                        'policy' => 'post',
                        'fields' => ['body'],
                    ],
                ],
            ],
        ]);
    }
}

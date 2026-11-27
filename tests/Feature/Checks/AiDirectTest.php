<?php

namespace Goodport\AutoModerator\Tests\Feature\Checks;

use Goodport\AiModerator\Agents\ModerationAgent;
use Goodport\AutoModerator\Checks\Ai\AiModerationCheck;
use Goodport\AutoModerator\Exceptions\InvalidConfigurationException;
use Goodport\AutoModerator\Facades\AutoModerator;
use Goodport\AutoModerator\Tests\Fixtures\TestActor;
use Goodport\AutoModerator\Tests\Fixtures\TestSubject;
use Goodport\AutoModerator\Tests\TestCase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Laravel\Ai\Prompts\AgentPrompt;

final class AiDirectTest extends TestCase
{
    /**
     * A direct AI check passes the profile.
     */
    public function test_direct_check_passes(): void
    {
        $this->configureDirect();
        ModerationAgent::fake([
            ['verdict' => 'approve', 'reason' => 'Fine.', 'violations' => []],
        ]);

        $report = AutoModerator::profile('post')
            ->input(['text' => 'Hello'])
            ->language('en')
            ->run();

        $result = $report->results()['ai_text_name'];

        $this->assertTrue($report->passed());
        $this->assertSame('text_name', $result->details['check']);
        $this->assertSame('passed', $result->details['result']['state']);
    }

    /**
     * A direct AI check rejection keeps findings.
     */
    public function test_direct_check_rejects(): void
    {
        $this->configureDirect();
        ModerationAgent::fake([
            [
                'verdict' => 'reject',
                'reason' => 'Bad name.',
                'violations' => [['code' => 'bad', 'message' => 'Bad name.']],
            ],
        ]);

        $report = AutoModerator::profile('post')
            ->input(['text' => 'Bad'])
            ->language('en')
            ->run();

        $result = $report->results()['ai_text_name'];

        $this->assertTrue($report->rejected());
        $this->assertSame('bad', $result->findings[0]->code);
        $this->assertSame('Bad name.', $result->reason);
    }

    /**
     * The AI global switch produces skipped, not passed.
     */
    public function test_direct_check_all_skipped(): void
    {
        $this->configureDirect();
        $this->app['config']->set('ai-moderator.enabled', false);

        $report = AutoModerator::profile('post')
            ->input(['text' => 'Hello'])
            ->language('en')
            ->run();

        $this->assertTrue($report->allSkipped());
        $this->assertTrue($report->results()['ai_text_name']->skipped());
    }

    /**
     * Several direct AI checks can run in one profile.
     */
    public function test_several_direct_checks_in_one_profile(): void
    {
        $this->setAiCheck('text_name', $this->aiCheckConfig());
        $this->setAiCheck('text_body', $this->aiCheckConfig());
        $this->setCheck('ai_text_name', AiModerationCheck::class, ['check' => 'text_name']);
        $this->setCheck('ai_text_body', AiModerationCheck::class, ['check' => 'text_body']);
        $this->setProfile('post', [
            'checks' => [
                ['check' => 'ai_text_name'],
                ['check' => 'ai_text_body'],
            ],
        ]);
        ModerationAgent::fake([
            ['verdict' => 'approve', 'reason' => 'Fine.', 'violations' => []],
            ['verdict' => 'approve', 'reason' => 'Fine.', 'violations' => []],
        ]);

        $report = AutoModerator::profile('post')
            ->input(['text' => 'Hello'])
            ->language('en')
            ->run();

        $this->assertTrue($report->passed());
        $this->assertSame(['ai_text_name', 'ai_text_body'], array_keys($report->results()));
        $this->assertSame('text_name', $report->results()['ai_text_name']->details['check']);
        $this->assertSame('text_body', $report->results()['ai_text_body']->details['check']);
        ModerationAgent::assertPromptedTimes(2);
    }

    /**
     * The direct mode passes the whole input; extra fields are ignored by AI.
     */
    public function test_direct_check_passes_whole_input(): void
    {
        $this->configureDirect();
        ModerationAgent::fake([
            ['verdict' => 'approve', 'reason' => 'Fine.', 'violations' => []],
        ]);

        AutoModerator::profile('post')
            ->input(['text' => 'Hello', 'title' => 'Ignored'])
            ->language('en')
            ->run();

        ModerationAgent::assertPrompted(function (AgentPrompt $prompt): bool {
            return str_contains($prompt->prompt, 'Hello')
                && ! str_contains($prompt->prompt, 'Ignored');
        });
    }

    /**
     * The direct audit row keeps the AI check key, actor, subject, and URL.
     */
    public function test_direct_audit_keeps_key_actor_subject_and_url(): void
    {
        $this->configureDirect();
        ModerationAgent::fake([
            ['verdict' => 'approve', 'reason' => 'Fine.', 'violations' => []],
        ]);

        $actor = new TestActor;
        $actor->id = 10;
        Auth::login($actor);

        $subject = new TestSubject;
        $subject->id = 20;

        $this->app->instance('request', Request::create('https://example.com/posts/1'));

        AutoModerator::profile('post')
            ->input(['text' => 'Hello'])
            ->language('en')
            ->subject($subject)
            ->run();

        $audit = DB::table('ai_moderation_audits')->first();

        $this->assertNotNull($audit);
        $this->assertSame('text_name', $audit->policy);
        $this->assertSame('text_name', $audit->check);
        $this->assertSame(TestActor::class, $audit->actor_type);
        $this->assertSame('10', $audit->actor_id);
        $this->assertSame(TestSubject::class, $audit->subject_type);
        $this->assertSame('20', $audit->subject_id);
        $this->assertSame('https://example.com/posts/1', $audit->request_url);
    }

    /**
     * The application locale is the fallback language.
     */
    public function test_direct_check_uses_app_locale(): void
    {
        $this->app->setLocale('ru');
        $this->configureDirect();
        ModerationAgent::fake([
            ['verdict' => 'approve', 'reason' => 'Fine.', 'violations' => []],
        ]);

        AutoModerator::profile('post')
            ->input(['text' => 'Hello'])
            ->run();

        ModerationAgent::assertPrompted(function (AgentPrompt $prompt): bool {
            return str_contains((string) $prompt->agent->instructions(), 'Russian');
        });
    }

    /**
     * The check language setting wins over the application locale.
     */
    public function test_direct_check_uses_check_language_before_app_locale(): void
    {
        $this->app->setLocale('en');
        $this->configureDirect(language: 'ru');
        ModerationAgent::fake([
            ['verdict' => 'approve', 'reason' => 'Fine.', 'violations' => []],
        ]);

        AutoModerator::profile('post')
            ->input(['text' => 'Hello'])
            ->run();

        ModerationAgent::assertPrompted(function (AgentPrompt $prompt): bool {
            return str_contains((string) $prompt->agent->instructions(), 'Russian');
        });
    }

    /**
     * The explicit language overrides the check setting.
     */
    public function test_direct_check_run_language_overrides_check_language(): void
    {
        $this->app->setLocale('en');
        $this->configureDirect(language: 'ru');
        ModerationAgent::fake([
            ['verdict' => 'approve', 'reason' => 'Fine.', 'violations' => []],
        ]);

        AutoModerator::profile('post')
            ->input(['text' => 'Hello'])
            ->language('en')
            ->run();

        ModerationAgent::assertPrompted(function (AgentPrompt $prompt): bool {
            return str_contains((string) $prompt->agent->instructions(), 'English');
        });
    }

    /**
     * fields is forbidden in direct mode.
     */
    public function test_direct_check_forbids_fields(): void
    {
        $this->setAiCheck('text_name', $this->aiCheckConfig());
        $this->setCheck('ai_text_name', AiModerationCheck::class, [
            'check' => 'text_name',
            'fields' => ['text'],
        ]);
        $this->setProfile('post', ['checks' => [['check' => 'ai_text_name']]]);

        $this->expectException(InvalidConfigurationException::class);

        AutoModerator::profile('post')->input(['text' => 'Hello'])->language('en')->run();
    }

    /**
     * policy and check are mutually exclusive.
     */
    public function test_direct_check_conflicts_with_policy(): void
    {
        $this->setAiCheck('text_name', $this->aiCheckConfig());
        $this->setCheck('ai_text_name', AiModerationCheck::class, [
            'check' => 'text_name',
            'policy' => 'text_name',
        ]);
        $this->setProfile('post', ['checks' => [['check' => 'ai_text_name']]]);

        $this->expectException(InvalidConfigurationException::class);

        AutoModerator::profile('post')->input(['text' => 'Hello'])->language('en')->run();
    }

    /**
     * An empty direct check key is a configuration error.
     */
    public function test_direct_check_requires_non_empty_key(): void
    {
        $this->setCheck('ai_text_name', AiModerationCheck::class, ['check' => '']);
        $this->setProfile('post', ['checks' => [['check' => 'ai_text_name']]]);

        $this->expectException(InvalidConfigurationException::class);

        AutoModerator::profile('post')->input(['text' => 'Hello'])->language('en')->run();
    }

    /**
     * Exactly one of check or policy is required.
     */
    public function test_direct_check_requires_check_or_policy(): void
    {
        $this->setCheck('ai_text_name', AiModerationCheck::class, ['language' => 'en']);
        $this->setProfile('post', ['checks' => [['check' => 'ai_text_name']]]);

        $this->expectException(InvalidConfigurationException::class);

        AutoModerator::profile('post')->input(['text' => 'Hello'])->language('en')->run();
    }

    /**
     * An unknown direct check key is a configuration error.
     */
    public function test_direct_check_unknown_key_throws(): void
    {
        $this->setCheck('ai_text_name', AiModerationCheck::class, ['check' => 'missing']);
        $this->setProfile('post', ['checks' => [['check' => 'ai_text_name']]]);

        $this->expectException(InvalidConfigurationException::class);

        AutoModerator::profile('post')->input(['text' => 'Hello'])->language('en')->run();
    }

    /**
     * Policy mode still requires fields.
     */
    public function test_policy_mode_requires_fields(): void
    {
        $this->setCheck('ai_text_name', AiModerationCheck::class, ['policy' => 'text_name']);
        $this->setProfile('post', ['checks' => [['check' => 'ai_text_name']]]);

        $this->expectException(InvalidConfigurationException::class);

        AutoModerator::profile('post')->input(['text' => 'Hello'])->language('en')->run();
    }

    private function configureDirect(?string $language = null): void
    {
        $settings = ['check' => 'text_name'];

        if ($language !== null) {
            $settings['language'] = $language;
        }

        $this->setAiCheck('text_name', $this->aiCheckConfig());
        $this->setCheck('ai_text_name', AiModerationCheck::class, $settings);
        $this->setProfile('post', [
            'checks' => [
                ['check' => 'ai_text_name'],
            ],
        ]);
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
}

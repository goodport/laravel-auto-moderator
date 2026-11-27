<?php

namespace Goodport\AutoModerator\Tests\Feature\Profile;

use Goodport\AutoModerator\Exceptions\InvalidConfigurationException;
use Goodport\AutoModerator\Facades\AutoModerator;
use Goodport\AutoModerator\Moderation\AuditContext;
use Goodport\AutoModerator\Tests\Fixtures\PlainAuthenticatable;
use Goodport\AutoModerator\Tests\Fixtures\RecordingCheck;
use Goodport\AutoModerator\Tests\Fixtures\TestActor;
use Goodport\AutoModerator\Tests\Fixtures\TestSubject;
use Goodport\AutoModerator\Tests\TestCase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

final class ContextTest extends TestCase
{
    /**
     * The authenticated user and request URL are used by default.
     */
    public function test_actor_defaults_to_authenticated_user_and_captures_request_url(): void
    {
        $actor = $this->actor(10);
        Auth::login($actor);
        $this->useRequest('https://example.com/posts/1');

        $this->recordProfile();

        $audit = RecordingCheck::$contexts[0]->audit;

        $this->assertNotNull($audit);
        $this->assertSame(TestActor::class, $audit->actorType);
        $this->assertSame('10', $audit->actorId);
        $this->assertSame('https://example.com/posts/1', $audit->requestUrl);
        $this->assertNull($audit->subjectType);
    }

    /**
     * A non-Eloquent authenticatable actor uses its class and auth identifier.
     */
    public function test_plain_authenticatable_actor_uses_class_and_auth_identifier(): void
    {
        Auth::login(new PlainAuthenticatable('42'));
        $this->useRequest('https://example.com/posts/1');

        $this->recordProfile();

        $audit = RecordingCheck::$contexts[0]->audit;

        $this->assertNotNull($audit);
        $this->assertSame(PlainAuthenticatable::class, $audit->actorType);
        $this->assertSame('42', $audit->actorId);
    }

    /**
     * Explicit actor and subject override the authenticated user.
     */
    public function test_explicit_actor_and_subject_are_used(): void
    {
        Auth::login($this->actor(1));
        $this->useRequest('https://example.com/posts/1');

        $actor = $this->actor(2);
        $subject = new TestSubject;
        $subject->id = 3;

        $this->setCheck('record', RecordingCheck::class);
        $this->setProfile('post', ['checks' => [['check' => 'record']]]);

        AutoModerator::profile('post')
            ->input([])
            ->actor($actor)
            ->subject($subject)
            ->run();

        $audit = RecordingCheck::$contexts[0]->audit;

        $this->assertNotNull($audit);
        $this->assertSame('2', $audit->actorId);
        $this->assertSame(TestSubject::class, $audit->subjectType);
        $this->assertSame('3', $audit->subjectId);
    }

    /**
     * The application locale is the default language.
     */
    public function test_language_defaults_to_app_locale(): void
    {
        $this->app->setLocale('ru');
        $this->useRequest('https://example.com/posts/1');

        $this->recordProfile();

        $this->assertSame('ru', RecordingCheck::$contexts[0]->language);
    }

    /**
     * Explicit language and audit override the defaults.
     */
    public function test_explicit_language_and_audit_override_defaults(): void
    {
        $this->app->setLocale('ru');
        $this->useRequest('https://example.com/posts/1');
        $audit = new AuditContext(
            actorType: 'explicit',
            actorId: '9',
            requestUrl: 'https://explicit.test',
        );

        $this->setCheck('record', RecordingCheck::class);
        $this->setProfile('post', ['checks' => [['check' => 'record']]]);

        AutoModerator::profile('post')
            ->input([])
            ->language('en')
            ->audit($audit)
            ->run();

        $context = RecordingCheck::$contexts[0];

        $this->assertSame('en', $context->language);
        $this->assertSame($audit, $context->audit);
    }

    /**
     * Automatic audit can be disabled.
     */
    public function test_auto_audit_can_be_disabled(): void
    {
        $this->app['config']->set('auto-moderator.audit.enabled', false);

        $this->recordProfile();

        $this->assertNull(RecordingCheck::$contexts[0]->audit);
    }

    /**
     * An explicit audit still works when automatic audit is disabled.
     */
    public function test_explicit_audit_works_when_auto_audit_is_disabled(): void
    {
        $this->app['config']->set('auto-moderator.audit.enabled', false);
        $audit = new AuditContext(actorType: 'explicit', actorId: '9');

        $this->setCheck('record', RecordingCheck::class);
        $this->setProfile('post', ['checks' => [['check' => 'record']]]);

        AutoModerator::profile('post')
            ->input([])
            ->audit($audit)
            ->run();

        $this->assertSame($audit, RecordingCheck::$contexts[0]->audit);
    }

    /**
     * The automatic audit switch must be a boolean.
     */
    public function test_auto_audit_config_must_be_boolean(): void
    {
        $this->app['config']->set('auto-moderator.audit.enabled', 'yes');

        $this->expectException(InvalidConfigurationException::class);

        $this->recordProfile();
    }

    private function actor(int $id): TestActor
    {
        $actor = new TestActor;
        $actor->id = $id;

        return $actor;
    }

    private function useRequest(string $url): void
    {
        $this->app->instance('request', Request::create($url));
    }

    private function recordProfile(): void
    {
        $this->setCheck('record', RecordingCheck::class);
        $this->setProfile('post', ['checks' => [['check' => 'record']]]);

        AutoModerator::profile('post')->input([])->run();
    }
}

<?php

namespace Goodport\AutoModerator\Tests\Feature\Background;

use Goodport\AutoModerator\Facades\AutoModerator;
use Goodport\AutoModerator\Models\ModerationResult;
use Goodport\AutoModerator\Models\ModerationRun;
use Goodport\AutoModerator\Tests\Fixtures\ModeratableSubject;
use Goodport\AutoModerator\Tests\Fixtures\PassingCheck;
use Goodport\AutoModerator\Tests\Fixtures\TestSubject;

final class ForgetTest extends BackgroundTestCase
{
    /**
     * forget deletes every scenario of one model and keeps other models intact.
     */
    public function test_forget_deletes_runs_and_results_for_model(): void
    {
        $this->registerProfile('pass', PassingCheck::class);
        $subject = $this->createSubject(fields: [
            $this->moderationField('body', 'Body', 'pass', 'Body'),
        ]);
        $other = $this->createSubject(fields: [
            $this->moderationField('body', 'Body', 'pass', 'Body'),
        ]);
        $this->handle($this->moderationJob($subject));

        $subject->scenario = 'other_scenario';
        $subject->save();
        $this->handle($this->moderationJob($subject));
        $this->handle($this->moderationJob($other));

        AutoModerator::forget($subject);

        $this->assertSame(1, ModerationRun::query()->count());
        $this->assertSame(1, ModerationResult::query()->count());
        $this->assertNotNull($subject->fresh());
        $this->assertSame(
            1,
            ModerationRun::query()->where('moderatable_id', (string) $other->id)->count(),
        );
    }

    /**
     * forget is a no-op for a model without runs.
     */
    public function test_forget_is_noop_without_runs(): void
    {
        $subject = $this->createSubject(fields: []);

        AutoModerator::forget($subject);

        $this->assertSame(0, ModerationRun::query()->count());
    }

    /**
     * forget rejects unpersisted and non-moderatable models.
     */
    public function test_forget_rejects_invalid_models(): void
    {
        $this->assertInvalidArgument(fn () => AutoModerator::forget(new ModeratableSubject));

        $plain = new TestSubject;
        $plain->save();
        $this->assertInvalidArgument(fn () => AutoModerator::forget($plain));
    }
}

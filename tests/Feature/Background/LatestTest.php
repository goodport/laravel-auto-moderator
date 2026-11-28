<?php

namespace Goodport\AutoModerator\Tests\Feature\Background;

use Goodport\AutoModerator\Facades\AutoModerator;
use Goodport\AutoModerator\Tests\Fixtures\ModeratableSubject;
use Goodport\AutoModerator\Tests\Fixtures\OtherModeratableSubject;
use Goodport\AutoModerator\Tests\Fixtures\PassingCheck;
use Goodport\AutoModerator\Tests\Fixtures\RejectingCheck;
use Goodport\AutoModerator\Tests\Fixtures\TestSubject;
use Illuminate\Support\Facades\DB;

final class LatestTest extends BackgroundTestCase
{
    /**
     * A model without runs has no outcome.
     */
    public function test_latest_returns_null_without_runs(): void
    {
        $subject = $this->createSubject(fields: []);

        $this->assertNull(AutoModerator::latest($subject));
    }

    /**
     * The latest run wins and the scenario filter narrows the search.
     */
    public function test_latest_returns_latest_run_and_respects_scenario(): void
    {
        $this->registerProfile('pass', PassingCheck::class);
        $this->registerProfile('reject', RejectingCheck::class);
        $subject = $this->createSubject(
            fields: [$this->moderationField('body', 'Body', 'pass', 'Body')],
            scenario: 'one',
        );
        $this->handle($this->moderationJob($subject));

        $subject->scenario = 'two';
        $subject->fields = [$this->moderationField('body', 'Body', 'reject', 'Body')];
        $subject->save();
        $this->handle($this->moderationJob($subject));

        $this->assertTrue(AutoModerator::latest($subject)->isRejected());
        $this->assertTrue(AutoModerator::latest($subject, 'one')->isApproved());
        $this->assertTrue(AutoModerator::latest($subject, 'two')->isRejected());
        $this->assertNull(AutoModerator::latest($subject, 'missing'));
    }

    /**
     * Public model queries reject unpersisted, non-moderatable, and empty scenario values.
     */
    public function test_latest_rejects_invalid_model_and_scenario(): void
    {
        $this->assertInvalidArgument(fn () => AutoModerator::latest(new ModeratableSubject));

        $plain = new TestSubject;
        $plain->save();
        $this->assertInvalidArgument(fn () => AutoModerator::latest($plain));

        $subject = $this->createSubject(fields: []);
        $this->assertInvalidArgument(fn () => AutoModerator::latest($subject, ''));
    }

    /**
     * latestFor applies the scenario filter to every model.
     */
    public function test_latest_for_respects_scenario(): void
    {
        $this->registerProfile('pass', PassingCheck::class);
        $this->registerProfile('reject', RejectingCheck::class);
        $subject = $this->createSubject(
            fields: [$this->moderationField('body', 'Body', 'pass', 'Body')],
            scenario: 'one',
        );
        $this->handle($this->moderationJob($subject));

        $subject->scenario = 'two';
        $subject->fields = [$this->moderationField('body', 'Body', 'reject', 'Body')];
        $subject->save();
        $this->handle($this->moderationJob($subject));

        $other = $this->createSubject(
            fields: [$this->moderationField('body', 'Body', 'pass', 'Body')],
            scenario: 'two',
        );
        $this->handle($this->moderationJob($other));

        $one = AutoModerator::latestFor([$subject, $other], 'one');

        $this->assertSame([$subject->id], array_keys($one));
        $this->assertTrue($one[$subject->id]->isApproved());

        $two = AutoModerator::latestFor([$subject, $other], 'two');

        $this->assertSame([$subject->id, $other->id], array_keys($two));
        $this->assertTrue($two[$subject->id]->isRejected());
        $this->assertTrue($two[$other->id]->isApproved());

        $this->assertSame([], AutoModerator::latestFor([$subject, $other], 'missing'));
    }

    /**
     * latestFor keeps first-seen order and omits models without runs.
     */
    public function test_latest_for_returns_outcomes_in_first_seen_order(): void
    {
        $this->registerProfile('pass', PassingCheck::class);
        $first = $this->createSubject(fields: [$this->moderationField('body', 'Body', 'pass', 'Body')]);
        $second = $this->createSubject(fields: [$this->moderationField('body', 'Body', 'pass', 'Body')]);
        $third = $this->createSubject(fields: []);
        $this->handle($this->moderationJob($first));
        $this->handle($this->moderationJob($second));

        $outcomes = AutoModerator::latestFor([$first, $third, $second, $first]);

        $this->assertSame([$first->id, $second->id], array_keys($outcomes));
        $this->assertTrue($outcomes[$first->id]->isApproved());
        $this->assertTrue($outcomes[$second->id]->isApproved());
    }

    /**
     * latestFor rejects mixed model classes.
     */
    public function test_latest_for_rejects_mixed_classes(): void
    {
        $first = $this->createSubject(fields: []);
        $other = new OtherModeratableSubject;
        $other->scenario = 'subject_scenario';
        $other->fields = [];
        $other->save();

        $this->assertInvalidArgument(fn () => AutoModerator::latestFor([$first, $other]));
    }

    /**
     * latestFor loads all outcomes without an N+1 query.
     */
    public function test_latest_for_does_not_create_n_plus_one(): void
    {
        $this->registerProfile('pass', PassingCheck::class);
        $subjects = [];

        for ($index = 1; $index <= 4; $index++) {
            $subject = $this->createSubject(fields: [
                $this->moderationField('body', 'Body', 'pass', 'Body'),
            ]);
            $this->handle($this->moderationJob($subject));
            $subjects[] = $subject;
        }

        DB::enableQueryLog();
        DB::flushQueryLog();

        $outcomes = AutoModerator::latestFor($subjects);
        $queries = DB::getQueryLog();

        DB::disableQueryLog();

        $this->assertCount(4, $outcomes);
        $this->assertLessThanOrEqual(2, count($queries));
    }
}

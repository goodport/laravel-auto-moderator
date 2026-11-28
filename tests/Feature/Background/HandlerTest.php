<?php

namespace Goodport\AutoModerator\Tests\Feature\Background;

use Goodport\AutoModerator\Events\ModerationCompleted;
use Goodport\AutoModerator\Facades\AutoModerator;
use Goodport\AutoModerator\Tests\Fixtures\ModeratableSubject;
use Goodport\AutoModerator\Tests\Fixtures\PassingCheck;
use Goodport\AutoModerator\Tests\Fixtures\RecordingResultHandler;
use Goodport\AutoModerator\Tests\Fixtures\TestSubject;
use Goodport\AutoModerator\Tests\Fixtures\ThrowingCheck;
use Goodport\AutoModerator\Tests\Fixtures\ThrowingResultHandler;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Event;
use InvalidArgumentException;
use RuntimeException;
use stdClass;

final class HandlerTest extends BackgroundTestCase
{
    /**
     * A registered handler receives the model and the public outcome.
     */
    public function test_handler_is_called_for_completed_run(): void
    {
        $this->registerProfile('pass', PassingCheck::class);
        $subject = $this->createSubject(fields: [
            $this->moderationField('body', 'Body', 'pass', 'Body'),
        ]);
        AutoModerator::registerHandler(ModeratableSubject::class, RecordingResultHandler::class);

        $this->handle($this->moderationJob($subject));

        $this->assertCount(1, RecordingResultHandler::$calls);
        $call = RecordingResultHandler::$calls[0];

        $this->assertInstanceOf(ModeratableSubject::class, $call['model']);
        $this->assertTrue($call['outcome']->isApproved());
        $this->assertSame('Body', $call['outcome']->field('body')->label);
    }

    /**
     * A failed run never calls the handler.
     */
    public function test_handler_is_not_called_for_failed_run(): void
    {
        $this->registerProfile('pass', PassingCheck::class);
        $subject = $this->createSubject(
            fields: [$this->moderationField('body', 'Body', 'pass', 'Body')],
            scenario: 'actual_scenario',
        );
        AutoModerator::registerHandler(ModeratableSubject::class, RecordingResultHandler::class);

        $this->assertJobFails($this->moderationJob($subject, 'expected_scenario'));

        $this->assertSame([], RecordingResultHandler::$calls);
    }

    /**
     * A skipped run still calls the handler.
     */
    public function test_handler_is_called_for_skipped_run(): void
    {
        $subject = $this->createSubject(fields: [
            $this->moderationField('body', 'Body', 'missing', '   '),
        ]);
        AutoModerator::registerHandler(ModeratableSubject::class, RecordingResultHandler::class);

        $this->handle($this->moderationJob($subject));

        $this->assertCount(1, RecordingResultHandler::$calls);
        $this->assertTrue(RecordingResultHandler::$calls[0]['outcome']->isSkipped());
    }

    /**
     * A handler is called for a needs_review run.
     */
    public function test_handler_is_called_for_needs_review_run(): void
    {
        $this->registerProfile('throw', ThrowingCheck::class);
        $subject = $this->createSubject(fields: [
            $this->moderationField('body', 'Body', 'throw', 'Body'),
        ]);
        AutoModerator::registerHandler(ModeratableSubject::class, RecordingResultHandler::class);

        $this->handle($this->moderationJob($subject));

        $this->assertCount(1, RecordingResultHandler::$calls);
        $this->assertTrue(RecordingResultHandler::$calls[0]['outcome']->needsReview());
        $this->assertTrue(RecordingResultHandler::$calls[0]['outcome']->field('body')->isUndecided());
    }

    /**
     * A handler can be registered by morph alias.
     */
    public function test_handler_can_be_registered_by_morph_alias(): void
    {
        Relation::morphMap(['moderatable_subject' => ModeratableSubject::class]);
        $this->registerProfile('pass', PassingCheck::class);
        $subject = $this->createSubject(fields: [
            $this->moderationField('body', 'Body', 'pass', 'Body'),
        ]);
        AutoModerator::registerHandler('moderatable_subject', RecordingResultHandler::class);

        $this->handle($this->moderationJob($subject));

        $this->assertCount(1, RecordingResultHandler::$calls);
        $this->assertInstanceOf(ModeratableSubject::class, RecordingResultHandler::$calls[0]['model']);
        $this->assertTrue(RecordingResultHandler::$calls[0]['outcome']->isApproved());
    }

    /**
     * A handler exception does not change the completed run status.
     */
    public function test_handler_exception_does_not_fail_run(): void
    {
        $this->registerProfile('pass', PassingCheck::class);
        $subject = $this->createSubject(fields: [
            $this->moderationField('body', 'Body', 'pass', 'Body'),
        ]);
        AutoModerator::registerHandler(ModeratableSubject::class, ThrowingResultHandler::class);
        Event::fake([ModerationCompleted::class]);

        try {
            $this->handle($this->moderationJob($subject));
        } catch (RuntimeException) {
            // The handler error is expected and must not change the run.
        }

        $this->assertTrue(AutoModerator::latest($subject)->isApproved());
        Event::assertNotDispatched(ModerationCompleted::class);
    }

    /**
     * The last registered handler wins.
     */
    public function test_last_registration_wins(): void
    {
        $this->registerProfile('pass', PassingCheck::class);
        $subject = $this->createSubject(fields: [
            $this->moderationField('body', 'Body', 'pass', 'Body'),
        ]);
        AutoModerator::registerHandler(ModeratableSubject::class, ThrowingResultHandler::class);
        AutoModerator::registerHandler(ModeratableSubject::class, RecordingResultHandler::class);

        $this->handle($this->moderationJob($subject));

        $this->assertCount(1, RecordingResultHandler::$calls);
    }

    /**
     * A missing handler is a no-op.
     */
    public function test_unregistered_handler_is_noop(): void
    {
        $this->registerProfile('pass', PassingCheck::class);
        $subject = $this->createSubject(fields: [
            $this->moderationField('body', 'Body', 'pass', 'Body'),
        ]);

        $this->handle($this->moderationJob($subject));

        $this->assertTrue(AutoModerator::latest($subject)->isApproved());
    }

    /**
     * The handler model must be a moderatable model.
     */
    public function test_register_handler_validates_model_class(): void
    {
        $this->expectException(InvalidArgumentException::class);

        AutoModerator::registerHandler(TestSubject::class, RecordingResultHandler::class);
    }

    /**
     * The handler class must implement the handler contract.
     */
    public function test_register_handler_validates_handler_class(): void
    {
        $this->expectException(InvalidArgumentException::class);

        AutoModerator::registerHandler(ModeratableSubject::class, stdClass::class);
    }
}

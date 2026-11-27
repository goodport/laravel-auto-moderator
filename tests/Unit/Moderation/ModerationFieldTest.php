<?php

namespace Goodport\AutoModerator\Tests\Unit\Moderation;

use Goodport\AutoModerator\Moderation\ModerationField;
use PHPUnit\Framework\TestCase;

final class ModerationFieldTest extends TestCase
{
    /**
     * A field is empty when every input value is empty.
     */
    public function test_field_is_empty_when_all_values_are_empty(): void
    {
        $field = new ModerationField('body', 'Body', 'text', [
            'text' => null,
            'title' => '',
            'summary' => '   ',
            'tags' => [],
        ]);

        $this->assertTrue($field->isEmpty());
    }

    /**
     * Zero, false, and non-empty arrays are present values.
     */
    public function test_field_is_not_empty_when_any_value_is_present(): void
    {
        $empty = ['text' => null, 'title' => '   '];

        $this->assertFalse((new ModerationField('body', 'Body', 'text', [
            'text' => 0,
        ]))->isEmpty());
        $this->assertFalse((new ModerationField('body', 'Body', 'text', [
            'text' => false,
        ]))->isEmpty());
        $this->assertFalse((new ModerationField('body', 'Body', 'text', [
            'tags' => ['example'],
        ]))->isEmpty());
        $this->assertFalse((new ModerationField('body', 'Body', 'text', [
            ...$empty,
            'text' => 'Hello',
        ]))->isEmpty());
    }
}

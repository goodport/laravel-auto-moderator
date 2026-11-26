<?php

namespace Goodport\AutoModerator\Tests\Unit;

use Goodport\AutoModerator\Support\ConfigMerger;
use PHPUnit\Framework\TestCase;

final class ConfigMergerTest extends TestCase
{
    /**
     * Associative maps are merged recursively and lists are replaced.
     */
    public function test_merges_maps_and_replaces_lists(): void
    {
        $base = [
            'fields' => ['body'],
            'alphabets' => ['en' => 'abc', 'ru' => 'абв'],
            'nested' => ['a' => 1, 'b' => 2],
        ];
        $override = [
            'fields' => ['title'],
            'alphabets' => ['ru' => 'где'],
            'nested' => ['b' => 3, 'c' => 4],
        ];

        $this->assertSame(
            [
                'fields' => ['title'],
                'alphabets' => ['en' => 'abc', 'ru' => 'где'],
                'nested' => ['a' => 1, 'b' => 3, 'c' => 4],
            ],
            ConfigMerger::merge($base, $override),
        );
    }

    /**
     * A scalar override replaces the base value.
     */
    public function test_scalar_override(): void
    {
        $this->assertSame(
            ['blocking' => false],
            ConfigMerger::merge(['blocking' => true], ['blocking' => false]),
        );
    }
}

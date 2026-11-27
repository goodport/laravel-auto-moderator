<?php

namespace Goodport\AutoModerator\Tests\Unit\Profiles;

use Goodport\AutoModerator\Checks\Ai\AiModerationCheck;
use Goodport\AutoModerator\Exceptions\InvalidConfigurationException;
use Goodport\AutoModerator\Profiles\ProfileConfigReader;
use Illuminate\Config\Repository;
use PHPUnit\Framework\TestCase;

final class ProfileConfigReaderTest extends TestCase
{
    /**
     * A missing profile falls back to a one-check AI profile.
     */
    public function test_builds_fallback_profile_from_ai_check(): void
    {
        $profile = $this->reader(aiChecks: ['text_name' => []])->read('text_name');

        $this->assertSame('text_name', $profile->key);
        $this->assertTrue($profile->enabled);
        $this->assertFalse($profile->stopOnReject);
        $this->assertSame([
            [
                'check' => 'ai',
                'enabled' => true,
                'settings' => ['check' => 'text_name'],
            ],
        ], $profile->checks);
    }

    /**
     * An explicit profile always wins over the fallback.
     */
    public function test_explicit_profile_overrides_fallback(): void
    {
        $profile = $this->reader(
            auto: [
                'profiles' => [
                    'text_name' => ['checks' => [['check' => 'pass']]],
                ],
            ],
            aiChecks: ['text_name' => []],
        )->read('text_name');

        $this->assertSame('pass', $profile->checks[0]['check']);
    }

    /**
     * An invalid explicit profile is an error, not a fallback.
     */
    public function test_explicit_invalid_profile_does_not_fall_back(): void
    {
        $this->expectException(InvalidConfigurationException::class);

        $this->reader(
            auto: [
                'profiles' => [
                    'text_name' => ['checks' => []],
                ],
            ],
            aiChecks: ['text_name' => []],
        )->read('text_name');
    }

    /**
     * A non-array explicit profile is an error, not a fallback.
     */
    public function test_explicit_non_array_profile_does_not_fall_back(): void
    {
        $this->expectException(InvalidConfigurationException::class);

        $this->reader(
            auto: [
                'profiles' => [
                    'text_name' => null,
                ],
            ],
            aiChecks: ['text_name' => []],
        )->read('text_name');
    }

    /**
     * A missing profile without an AI check is unknown.
     */
    public function test_unknown_profile_without_ai_check_throws(): void
    {
        $this->expectException(InvalidConfigurationException::class);

        $this->reader()->read('missing');
    }

    /**
     * A missing fallback check is a configuration error.
     */
    public function test_missing_fallback_check_throws(): void
    {
        $this->expectException(InvalidConfigurationException::class);

        $this->reader(
            auto: ['fallback' => ['ai_check' => 'missing_ai']],
            aiChecks: ['text_name' => []],
        )->read('text_name');
    }

    /**
     * A custom fallback check key is used.
     */
    public function test_configured_fallback_check_is_used(): void
    {
        $profile = $this->reader(
            auto: [
                'fallback' => ['ai_check' => 'ai_custom'],
                'checks' => [
                    'ai_custom' => ['class' => AiModerationCheck::class, 'settings' => []],
                ],
            ],
            aiChecks: ['text_name' => []],
        )->read('text_name');

        $this->assertSame('ai_custom', $profile->checks[0]['check']);
        $this->assertSame(['check' => 'text_name'], $profile->checks[0]['settings']);
    }

    /**
     * The fallback profile uses the default stop_on_reject value.
     */
    public function test_fallback_uses_default_stop_on_reject(): void
    {
        $profile = $this->reader(
            auto: ['defaults' => ['stop_on_reject' => true]],
            aiChecks: ['text_name' => []],
        )->read('text_name');

        $this->assertTrue($profile->stopOnReject);
    }

    /**
     * The fallback profile respects the global switch.
     */
    public function test_fallback_respects_global_switch(): void
    {
        $profile = $this->reader(
            auto: ['enabled' => false],
            aiChecks: ['text_name' => []],
        )->read('text_name');

        $this->assertFalse($profile->enabled);
    }

    /**
     * @param  array<string, mixed>  $auto
     * @param  array<string, mixed>  $aiChecks
     */
    private function reader(array $auto = [], array $aiChecks = []): ProfileConfigReader
    {
        $auto = array_replace_recursive([
            'enabled' => true,
            'defaults' => ['stop_on_reject' => false],
            'fallback' => ['ai_check' => 'ai'],
            'checks' => [
                'ai' => ['class' => AiModerationCheck::class, 'settings' => []],
            ],
            'profiles' => [],
        ], $auto);

        return new ProfileConfigReader(new Repository([
            'auto-moderator' => $auto,
            'ai-moderator' => ['checks' => $aiChecks],
        ]));
    }
}

<?php

namespace Goodport\AutoModerator\Tests\Feature\Checks;

use Goodport\AutoModerator\Facades\AutoModerator;
use Goodport\AutoModerator\Tests\TestCase;

final class LanguageTest extends TestCase
{
    /**
     * Text in the expected alphabet passes and reports the actual ratio.
     */
    public function test_passes_text_in_expected_alphabet(): void
    {
        $this->configure('ru');

        $report = AutoModerator::profile('post')
            ->input(['body' => 'Привет мир'])
            ->run();

        $result = $report->results()['language'];

        $this->assertTrue($report->passed());
        $this->assertSame(1.0, $result->details['fields']['body']['ratio']);
    }

    /**
     * Text in another alphabet is rejected.
     */
    public function test_rejects_text_in_another_alphabet(): void
    {
        $this->configure('ru');

        $report = AutoModerator::profile('post')
            ->input(['body' => 'Hello world'])
            ->run();

        $result = $report->results()['language'];

        $this->assertTrue($report->rejected());
        $this->assertSame('language_ratio', $result->findings[0]->code);
        $this->assertSame(0.0, $result->details['fields']['body']['ratio']);
    }

    /**
     * Text shorter than the minimum length is skipped.
     */
    public function test_skips_short_text(): void
    {
        $this->configure('ru', minLength: 100);

        $report = AutoModerator::profile('post')
            ->input(['body' => 'Привет'])
            ->run();

        $result = $report->results()['language'];

        $this->assertTrue($result->skipped());
        $this->assertSame('shorter_than_min_length', $result->details['fields']['body']['reason']);
    }

    /**
     * Text without letters cannot be analyzed.
     */
    public function test_skips_text_without_letters(): void
    {
        $this->configure('ru', minLength: 1);

        $report = AutoModerator::profile('post')
            ->input(['body' => '12345'])
            ->run();

        $result = $report->results()['language'];

        $this->assertTrue($result->skipped());
        $this->assertSame('no_letters', $result->details['fields']['body']['reason']);
    }

    /**
     * The run language overrides the check setting.
     */
    public function test_run_language_overrides_setting(): void
    {
        $this->configure('en');

        $report = AutoModerator::profile('post')
            ->input(['body' => 'Привет мир'])
            ->language('ru')
            ->run();

        $this->assertTrue($report->passed());
        $this->assertSame('ru', $report->results()['language']->details['language']);
    }

    /**
     * The explicit language overrides a different check setting.
     */
    public function test_run_language_overrides_check_language(): void
    {
        $this->app->setLocale('en');
        $this->configure('ru');

        $report = AutoModerator::profile('post')
            ->input(['body' => 'Hello world'])
            ->language('en')
            ->run();

        $this->assertTrue($report->passed());
        $this->assertSame('en', $report->results()['language']->details['language']);
    }

    /**
     * The check language setting wins over the application locale.
     */
    public function test_uses_check_language_before_app_locale(): void
    {
        $this->app->setLocale('en');
        $this->configure('ru');

        $report = AutoModerator::profile('post')
            ->input(['body' => 'Привет мир'])
            ->run();

        $this->assertTrue($report->passed());
        $this->assertSame('ru', $report->results()['language']->details['language']);
    }

    /**
     * The application locale is the fallback when no language is configured.
     */
    public function test_uses_app_locale_without_check_language_or_override(): void
    {
        $this->app->setLocale('ru');
        $this->configure(null);

        $report = AutoModerator::profile('post')
            ->input(['body' => 'Привет мир'])
            ->run();

        $this->assertTrue($report->passed());
        $this->assertSame('ru', $report->results()['language']->details['language']);
    }

    /**
     * A non-blocking ratio violation is a warning.
     */
    public function test_non_blocking_violation_is_a_warning(): void
    {
        $this->configure('ru', blocking: false);

        $report = AutoModerator::profile('post')
            ->input(['body' => 'Hello world'])
            ->run();

        $this->assertTrue($report->passed());
        $this->assertArrayHasKey('language', $report->warnings());
    }

    private function configure(
        ?string $language = 'ru',
        int $minLength = 5,
        float $threshold = 0.6,
        bool $blocking = true,
    ): void {
        $settings = [
            'fields' => ['body'],
            'min_length' => $minLength,
            'threshold' => $threshold,
            'blocking' => $blocking,
        ];

        if ($language !== null) {
            $settings['language'] = $language;
        }

        $this->setProfile('post', [
            'checks' => [
                [
                    'check' => 'language',
                    'settings' => $settings,
                ],
            ],
        ]);
    }
}

<?php

namespace Goodport\AutoModerator\Tests\Feature\Checks;

use Goodport\AutoModerator\Exceptions\InvalidConfigurationException;
use Goodport\AutoModerator\Facades\AutoModerator;
use Goodport\AutoModerator\Tests\TestCase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

final class ToxicityTest extends TestCase
{
    /**
     * A score at or above the threshold rejects the content.
     */
    public function test_rejects_score_at_or_above_threshold(): void
    {
        $this->configure();
        $this->fakeScore(0.7);

        $report = AutoModerator::profile('post')
            ->input(['body' => 'Hello'])
            ->language('en')
            ->run();

        $result = $report->results()['toxicity'];

        $this->assertTrue($report->rejected());
        $this->assertSame('toxicity', $result->findings[0]->code);
        $this->assertSame(0.7, $result->details['scores']['body']);
    }

    /**
     * A score below the threshold passes.
     */
    public function test_passes_score_below_threshold(): void
    {
        $this->configure();
        $this->fakeScore(0.2);

        $report = AutoModerator::profile('post')
            ->input(['body' => 'Hello'])
            ->language('en')
            ->run();

        $this->assertTrue($report->passed());
        $this->assertSame(0.2, $report->results()['toxicity']->details['scores']['body']);
    }

    /**
     * An HTTP error is undecided, never a pass.
     */
    public function test_http_error_is_undecided(): void
    {
        $this->configure();
        Http::fake([
            'https://example.com/*' => Http::response('', 500),
        ]);

        $report = AutoModerator::profile('post')
            ->input(['body' => 'Hello'])
            ->language('en')
            ->run();

        $result = $report->results()['toxicity'];

        $this->assertNull($report->passed());
        $this->assertTrue($report->undecided());
        $this->assertStringContainsString('HTTP 500', $result->error);
    }

    /**
     * A malformed response is undecided.
     */
    public function test_invalid_response_is_undecided(): void
    {
        $this->configure();
        Http::fake([
            'https://example.com/*' => Http::response([]),
        ]);

        $report = AutoModerator::profile('post')
            ->input(['body' => 'Hello'])
            ->language('en')
            ->run();

        $this->assertTrue($report->undecided());
        $this->assertNotNull($report->results()['toxicity']->error);
    }

    /**
     * The request carries the text, language, and API key.
     */
    public function test_request_carries_text_language_and_key(): void
    {
        $this->configure();
        $this->fakeScore(0.1);

        AutoModerator::profile('post')
            ->input(['body' => 'Hello'])
            ->language('en')
            ->run();

        Http::assertSent(function (Request $request): bool {
            return $request->url() === 'https://example.com/v1alpha1/comments:analyze?key=test-key'
                && $request['comment']['text'] === 'Hello'
                && $request['languages'] === ['en']
                && array_key_exists('TOXICITY', $request['requestedAttributes']);
        });
    }

    /**
     * Text longer than the configured limit is truncated before the request.
     */
    public function test_truncates_text_to_configured_limit(): void
    {
        $this->configure(maxChars: 5);
        $this->fakeScore(0.1);

        $report = AutoModerator::profile('post')
            ->input(['body' => 'Hello world'])
            ->language('en')
            ->run();

        Http::assertSent(function (Request $request): bool {
            return $request['comment']['text'] === 'Hello';
        });

        $this->assertSame(['body'], $report->results()['toxicity']->details['truncated']);
    }

    /**
     * The default limit truncates a long text to ten thousand characters.
     */
    public function test_truncates_text_at_default_limit(): void
    {
        $this->configure();
        $this->fakeScore(0.1);

        $report = AutoModerator::profile('post')
            ->input(['body' => str_repeat('a', 10001)])
            ->language('en')
            ->run();

        Http::assertSent(function (Request $request): bool {
            return mb_strlen($request['comment']['text'], 'UTF-8') === 10000;
        });

        $this->assertSame(['body'], $report->results()['toxicity']->details['truncated']);
    }

    /**
     * Warnings survive when another field fails.
     */
    public function test_keeps_warnings_when_another_field_fails(): void
    {
        $this->setPerspectiveService();
        $this->setProfile('post', [
            'checks' => [
                [
                    'check' => 'toxicity',
                    'settings' => [
                        'fields' => ['first', 'second'],
                        'threshold' => 0.7,
                        'blocking' => false,
                    ],
                ],
            ],
        ]);
        Http::fakeSequence()
            ->push([
                'attributeScores' => [
                    'TOXICITY' => ['summaryScore' => ['value' => 0.9]],
                ],
            ], 200)
            ->push('', 500);

        $report = AutoModerator::profile('post')
            ->input(['first' => 'Toxic', 'second' => 'Error'])
            ->language('en')
            ->run();

        $result = $report->results()['toxicity'];

        $this->assertTrue($report->undecided());
        $this->assertCount(1, $result->findings);
        $this->assertFalse($result->findings[0]->blocking);
        $this->assertArrayHasKey('toxicity', $report->warnings());
        $this->assertNotNull($result->error);
    }

    /**
     * The Perspective key is required.
     */
    public function test_missing_api_key_throws(): void
    {
        $this->configure();
        $this->setPerspectiveService(key: '');

        $this->expectException(InvalidConfigurationException::class);

        AutoModerator::profile('post')
            ->input(['body' => 'Hello'])
            ->language('en')
            ->run();
    }

    /**
     * The application locale is the fallback language.
     */
    public function test_uses_app_locale_by_default(): void
    {
        $this->app->setLocale('ru');
        $this->configure();
        $this->fakeScore(0.1);

        AutoModerator::profile('post')
            ->input(['body' => 'Hello'])
            ->run();

        Http::assertSent(function (Request $request): bool {
            return $request['languages'] === ['ru'];
        });
    }

    /**
     * The check language setting wins over the application locale.
     */
    public function test_uses_check_language_before_app_locale(): void
    {
        $this->app->setLocale('en');
        $this->configure(language: 'ru');
        $this->fakeScore(0.1);

        AutoModerator::profile('post')
            ->input(['body' => 'Hello'])
            ->run();

        Http::assertSent(function (Request $request): bool {
            return $request['languages'] === ['ru'];
        });
    }

    /**
     * The explicit language overrides the check setting.
     */
    public function test_run_language_overrides_check_language(): void
    {
        $this->app->setLocale('en');
        $this->configure(language: 'ru');
        $this->fakeScore(0.1);

        AutoModerator::profile('post')
            ->input(['body' => 'Hello'])
            ->language('en')
            ->run();

        Http::assertSent(function (Request $request): bool {
            return $request['languages'] === ['en'];
        });
    }

    private function configure(int $maxChars = 10000, ?string $language = null): void
    {
        $settings = [
            'fields' => ['body'],
            'threshold' => 0.7,
            'max_chars' => $maxChars,
        ];

        if ($language !== null) {
            $settings['language'] = $language;
        }

        $this->setPerspectiveService();
        $this->setProfile('post', [
            'checks' => [
                [
                    'check' => 'toxicity',
                    'settings' => $settings,
                ],
            ],
        ]);
    }

    private function fakeScore(float $score): void
    {
        Http::fake([
            'https://example.com/*' => Http::response([
                'attributeScores' => [
                    'TOXICITY' => [
                        'summaryScore' => ['value' => $score],
                    ],
                ],
            ]),
        ]);
    }
}

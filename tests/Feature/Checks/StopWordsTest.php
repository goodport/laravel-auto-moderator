<?php

namespace Goodport\AutoModerator\Tests\Feature\Checks;

use Goodport\AutoModerator\Exceptions\InvalidConfigurationException;
use Goodport\AutoModerator\Exceptions\InvalidInputException;
use Goodport\AutoModerator\Facades\AutoModerator;
use Goodport\AutoModerator\Tests\TestCase;

final class StopWordsTest extends TestCase
{
    /**
     * Configured words are matched case-insensitively.
     */
    public function test_detects_configured_words_case_insensitively(): void
    {
        $this->configure(['badword']);

        $report = AutoModerator::profile('post')
            ->input(['body' => 'A BadWord here.'])
            ->run();

        $result = $report->results()['stop_words'];

        $this->assertTrue($report->rejected());
        $this->assertSame('stop_word', $result->findings[0]->code);
        $this->assertSame(
            [['field' => 'body', 'word' => 'badword']],
            $result->details['matched_words'],
        );
    }

    /**
     * Phrases are matched as a whole, with normalized whitespace.
     */
    public function test_detects_phrases(): void
    {
        $this->configure(['bad phrase']);

        $report = AutoModerator::profile('post')
            ->input(['body' => 'A BAD   PHRASE here.'])
            ->run();

        $this->assertTrue($report->rejected());
        $this->assertSame(
            [['field' => 'body', 'word' => 'bad phrase']],
            $report->results()['stop_words']->details['matched_words'],
        );
    }

    /**
     * A stop word inside a longer word is not a match.
     */
    public function test_does_not_match_inside_a_word(): void
    {
        $this->configure(['bad']);

        $report = AutoModerator::profile('post')
            ->input(['body' => 'A badge here.'])
            ->run();

        $this->assertTrue($report->passed());
    }

    /**
     * A non-blocking stop word is returned as a warning.
     */
    public function test_non_blocking_word_is_a_warning(): void
    {
        $this->configure(['badword'], blocking: false);

        $report = AutoModerator::profile('post')
            ->input(['body' => 'A badword here.'])
            ->run();

        $this->assertTrue($report->passed());
        $this->assertSame([], $report->violations());
        $this->assertArrayHasKey('stop_words', $report->warnings());
    }

    /**
     * The application must provide the word list.
     */
    public function test_requires_configured_words(): void
    {
        $this->setProfile('post', [
            'checks' => [
                [
                    'check' => 'stop_words',
                    'settings' => [
                        'fields' => ['body'],
                    ],
                ],
            ],
        ]);

        $this->expectException(InvalidConfigurationException::class);

        AutoModerator::profile('post')->input(['body' => 'Text'])->run();
    }

    /**
     * A text field must be a string.
     */
    public function test_text_field_must_be_a_string(): void
    {
        $this->configure(['badword']);

        $this->expectException(InvalidInputException::class);

        AutoModerator::profile('post')
            ->input(['body' => ['not', 'a', 'string']])
            ->run();
    }

    /**
     * Invalid UTF-8 is an input error, not a silent pass.
     */
    public function test_invalid_utf8_throws(): void
    {
        $this->configure(['badword']);

        $this->expectException(InvalidInputException::class);

        AutoModerator::profile('post')
            ->input(['body' => "\xFF"])
            ->run();
    }

    /**
     * @param  array<int, string>  $words
     */
    private function configure(array $words, bool $blocking = true): void
    {
        $this->setProfile('post', [
            'checks' => [
                [
                    'check' => 'stop_words',
                    'settings' => [
                        'fields' => ['body'],
                        'words' => $words,
                        'blocking' => $blocking,
                    ],
                ],
            ],
        ]);
    }
}

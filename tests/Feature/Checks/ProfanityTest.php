<?php

namespace Goodport\AutoModerator\Tests\Feature\Checks;

use Goodport\AutoModerator\Exceptions\InvalidConfigurationException;
use Goodport\AutoModerator\Facades\AutoModerator;
use Goodport\AutoModerator\Tests\TestCase;

final class ProfanityTest extends TestCase
{
    /**
     * The Russian dictionary catches Russian profanity.
     */
    public function test_detects_russian_profanity(): void
    {
        $this->configure(['ru']);

        $report = AutoModerator::profile('post')
            ->input(['body' => 'Ну блядь, опять.'])
            ->run();

        $result = $report->results()['profanity'];

        $this->assertTrue($report->rejected());
        $this->assertSame('profanity', $result->findings[0]->code);
        $this->assertSame('ru', $result->details['matched_words'][0]['language']);
    }

    /**
     * The English dictionary catches English profanity.
     */
    public function test_detects_english_profanity(): void
    {
        $this->configure(['en']);

        $report = AutoModerator::profile('post')
            ->input(['body' => 'You bitch.'])
            ->run();

        $this->assertTrue($report->rejected());
        $this->assertSame('en', $report->results()['profanity']->details['matched_words'][0]['language']);
    }

    /**
     * Several dictionaries are checked in one call.
     */
    public function test_detects_both_languages(): void
    {
        $this->configure(['ru', 'en']);

        $report = AutoModerator::profile('post')
            ->input(['body' => 'bitch блядь'])
            ->run();

        $languages = array_column(
            $report->results()['profanity']->details['matched_words'],
            'language',
        );

        $this->assertTrue($report->rejected());
        $this->assertContains('en', $languages);
        $this->assertContains('ru', $languages);
    }

    /**
     * The run language must not disable a configured dictionary.
     */
    public function test_run_language_does_not_restrict_dictionaries(): void
    {
        $this->configure(['ru']);

        $report = AutoModerator::profile('post')
            ->input(['body' => 'Ну блядь, опять.'])
            ->language('en')
            ->run();

        $this->assertTrue($report->rejected());
    }

    /**
     * A word inside a longer word is not a profanity match.
     */
    public function test_does_not_match_inside_a_word(): void
    {
        $this->configure(['en']);

        $report = AutoModerator::profile('post')
            ->input(['body' => 'A classroom.'])
            ->run();

        $this->assertTrue($report->passed());
    }

    /**
     * A language without a dictionary is a configuration error.
     */
    public function test_unknown_language_throws(): void
    {
        $this->configure(['xx']);

        $this->expectException(InvalidConfigurationException::class);

        AutoModerator::profile('post')->input(['body' => 'Text'])->run();
    }

    /**
     * The profanity check does not accept the interface language setting.
     */
    public function test_rejects_language_setting(): void
    {
        $this->setProfile('post', [
            'checks' => [
                [
                    'check' => 'profanity',
                    'settings' => [
                        'fields' => ['body'],
                        'languages' => ['ru'],
                        'language' => 'ru',
                    ],
                ],
            ],
        ]);

        $this->expectException(InvalidConfigurationException::class);

        AutoModerator::profile('post')->input(['body' => 'Text'])->run();
    }

    /**
     * @param  array<int, string>  $languages
     */
    private function configure(array $languages): void
    {
        $this->setProfile('post', [
            'checks' => [
                [
                    'check' => 'profanity',
                    'settings' => [
                        'fields' => ['body'],
                        'languages' => $languages,
                    ],
                ],
            ],
        ]);
    }
}

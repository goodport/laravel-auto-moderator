<?php

namespace Goodport\AutoModerator\Tests\Feature\Profile;

use Goodport\AutoModerator\Exceptions\InvalidCheckResultException;
use Goodport\AutoModerator\Exceptions\InvalidConfigurationException;
use Goodport\AutoModerator\Exceptions\InvalidInputException;
use Goodport\AutoModerator\Facades\AutoModerator;
use Goodport\AutoModerator\Tests\Fixtures\InconsistentRejectedCheck;
use Goodport\AutoModerator\Tests\Fixtures\MismatchedKeyCheck;
use Goodport\AutoModerator\Tests\Fixtures\PassingCheck;
use Goodport\AutoModerator\Tests\TestCase;
use stdClass;

final class ConfigurationTest extends TestCase
{
    /**
     * An unknown profile is a configuration error.
     */
    public function test_unknown_profile_throws(): void
    {
        $this->expectException(InvalidConfigurationException::class);

        AutoModerator::profile('missing')->input([])->run();
    }

    /**
     * A profile without checks is a configuration error.
     */
    public function test_empty_checks_throws(): void
    {
        $this->setProfile('post', ['checks' => []]);

        $this->expectException(InvalidConfigurationException::class);

        AutoModerator::profile('post')->input([])->run();
    }

    /**
     * A check may appear only once in a profile.
     */
    public function test_duplicate_check_throws(): void
    {
        $this->setCheck('pass', PassingCheck::class);
        $this->setProfile('post', [
            'checks' => [
                ['check' => 'pass'],
                ['check' => 'pass'],
            ],
        ]);

        $this->expectException(InvalidConfigurationException::class);

        AutoModerator::profile('post')->input([])->run();
    }

    /**
     * A profile cannot reference an unregistered check.
     */
    public function test_unknown_check_throws(): void
    {
        $this->setProfile('post', [
            'checks' => [
                ['check' => 'missing'],
            ],
        ]);

        $this->expectException(InvalidConfigurationException::class);

        AutoModerator::profile('post')->input([])->run();
    }

    /**
     * Unknown profile keys are rejected.
     */
    public function test_unknown_profile_key_throws(): void
    {
        $this->setCheck('pass', PassingCheck::class);
        $this->setProfile('post', [
            'unknown' => true,
            'checks' => [
                ['check' => 'pass'],
            ],
        ]);

        $this->expectException(InvalidConfigurationException::class);

        AutoModerator::profile('post')->input([])->run();
    }

    /**
     * Unknown registry entry keys are rejected.
     */
    public function test_unknown_registry_key_throws(): void
    {
        $this->app['config']->set('auto-moderator.checks.pass', [
            'class' => PassingCheck::class,
            'unknown' => true,
        ]);
        $this->setProfile('post', [
            'checks' => [
                ['check' => 'pass'],
            ],
        ]);

        $this->expectException(InvalidConfigurationException::class);

        AutoModerator::profile('post')->input([])->run();
    }

    /**
     * A registered class must implement the check contract.
     */
    public function test_class_must_implement_contract(): void
    {
        $this->setCheck('invalid', stdClass::class);
        $this->setProfile('post', [
            'checks' => [
                ['check' => 'invalid'],
            ],
        ]);

        $this->expectException(InvalidConfigurationException::class);

        AutoModerator::profile('post')->input([])->run();
    }

    /**
     * A check result key must match the profile check key.
     */
    public function test_result_key_must_match(): void
    {
        $this->setCheck('mismatch', MismatchedKeyCheck::class);
        $this->setProfile('post', [
            'checks' => [
                ['check' => 'mismatch'],
            ],
        ]);

        $this->expectException(InvalidCheckResultException::class);

        AutoModerator::profile('post')->input([])->run();
    }

    /**
     * A rejected result without a blocking finding is a programming error.
     */
    public function test_rejected_result_requires_blocking_finding(): void
    {
        $this->setCheck('inconsistent', InconsistentRejectedCheck::class);
        $this->setProfile('post', [
            'checks' => [
                ['check' => 'inconsistent'],
            ],
        ]);

        $this->expectException(InvalidCheckResultException::class);

        AutoModerator::profile('post')->input([])->run();
    }

    /**
     * A declared input field must exist.
     */
    public function test_missing_input_field_throws(): void
    {
        $this->setProfile('post', [
            'checks' => [
                [
                    'check' => 'stop_words',
                    'settings' => [
                        'fields' => ['body'],
                        'words' => ['example'],
                    ],
                ],
            ],
        ]);

        $this->expectException(InvalidInputException::class);

        AutoModerator::profile('post')->input([])->run();
    }

    /**
     * An empty run language is rejected by the public API.
     */
    public function test_empty_run_language_throws(): void
    {
        $this->expectException(InvalidInputException::class);

        AutoModerator::profile('post')->language('')->run();
    }

    /**
     * An unsupported language without a configured alphabet is an error.
     */
    public function test_unknown_language_alphabet_throws(): void
    {
        $this->setProfile('post', [
            'checks' => [
                [
                    'check' => 'language',
                    'settings' => [
                        'fields' => ['body'],
                        'language' => 'xx',
                        'min_length' => 1,
                    ],
                ],
            ],
        ]);

        $this->expectException(InvalidConfigurationException::class);

        AutoModerator::profile('post')
            ->input(['body' => 'Hello'])
            ->language('xx')
            ->run();
    }
}

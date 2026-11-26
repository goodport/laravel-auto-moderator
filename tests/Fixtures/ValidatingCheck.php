<?php

namespace Goodport\AutoModerator\Tests\Fixtures;

use Goodport\AutoModerator\Contracts\ModerationCheck;
use Goodport\AutoModerator\Contracts\ValidatesSettings;
use Goodport\AutoModerator\Exceptions\InvalidConfigurationException;
use Goodport\AutoModerator\Moderation\CheckResult;
use Goodport\AutoModerator\Moderation\ModerationContext;

final class ValidatingCheck implements ModerationCheck, ValidatesSettings
{
    /**
     * @param  array<string, mixed>  $settings
     */
    public function validateSettings(string $check, array $settings): void
    {
        if (! isset($settings['required']) || ! is_string($settings['required'])) {
            throw InvalidConfigurationException::invalidSetting(
                $check.'.required',
                'must be configured.',
            );
        }
    }

    public function check(ModerationContext $context): CheckResult
    {
        return CheckResult::passedResult($context->check);
    }
}

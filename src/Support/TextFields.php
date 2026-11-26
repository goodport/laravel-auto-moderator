<?php

namespace Goodport\AutoModerator\Support;

use Goodport\AutoModerator\Exceptions\InvalidInputException;
use Goodport\AutoModerator\Moderation\ModerationContext;
use Stringable;

/**
 * Selects and validates text input fields.
 */
final class TextFields
{
    /**
     * @param  array<int, string>  $fields
     * @return array<string, string>
     */
    public static function values(ModerationContext $context, array $fields): array
    {
        $values = InputFields::select($context, $fields);
        $texts = [];

        foreach ($values as $field => $value) {
            if (! is_string($value) && ! $value instanceof Stringable) {
                throw InvalidInputException::invalidText($context->check, $field);
            }

            $text = (string) $value;

            if (! mb_check_encoding($text, 'UTF-8')) {
                throw InvalidInputException::invalidEncoding($context->check, $field);
            }

            $texts[$field] = $text;
        }

        return $texts;
    }
}

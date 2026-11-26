<?php

namespace Goodport\AutoModerator\Support;

use Goodport\AutoModerator\Exceptions\InvalidInputException;
use Goodport\AutoModerator\Moderation\ModerationContext;

/**
 * Selects declared input fields from the context.
 */
final class InputFields
{
    /**
     * @param  array<int, string>  $fields
     * @return array<string, mixed>
     */
    public static function select(ModerationContext $context, array $fields): array
    {
        $values = [];

        foreach ($fields as $field) {
            if (! array_key_exists($field, $context->fields)) {
                throw InvalidInputException::missingField($context->check, $field);
            }

            $values[$field] = $context->fields[$field];
        }

        return $values;
    }
}

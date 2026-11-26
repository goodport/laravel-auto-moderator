<?php

namespace Goodport\AutoModerator\Moderation;

/**
 * Input passed to a single moderation check.
 */
final readonly class ModerationContext
{
    /**
     * @param  array<string, mixed>  $fields  Named input fields.
     * @param  array<string, mixed>  $settings  Registry defaults merged with profile overrides.
     */
    public function __construct(
        public string $check,
        public array $fields,
        public ?string $language,
        public array $settings,
        public ?AuditContext $audit = null,
    ) {}
}

<?php

namespace Goodport\AutoModerator;

use Goodport\AutoModerator\Exceptions\InvalidInputException;
use Goodport\AutoModerator\Moderation\AuditContext;
use Goodport\AutoModerator\Moderation\Report;
use Goodport\AutoModerator\Orchestration\ModerationRunner;
use Goodport\AutoModerator\Profiles\ProfileResolver;

/**
 * Fluent moderation profile run.
 */
final class PendingProfile
{
    /**
     * @var array<string, mixed>
     */
    private array $fields = [];

    private ?string $language = null;

    private ?AuditContext $audit = null;

    public function __construct(
        private readonly string $profile,
        private readonly ProfileResolver $resolver,
        private readonly ModerationRunner $runner,
    ) {}

    /**
     * Set the named input fields for the run.
     *
     * @param  array<string, mixed>  $fields
     */
    public function input(array $fields): self
    {
        $this->fields = $fields;

        return $this;
    }

    /**
     * Override the language configured on checks.
     */
    public function language(string $language): self
    {
        if (trim($language) === '') {
            throw InvalidInputException::emptyLanguage();
        }

        $this->language = $language;

        return $this;
    }

    /**
     * Attach audit context to the run.
     */
    public function audit(AuditContext $audit): self
    {
        $this->audit = $audit;

        return $this;
    }

    public function run(): Report
    {
        return $this->runner->run(
            profile: $this->resolver->resolve($this->profile),
            fields: $this->fields,
            language: $this->language,
            audit: $this->audit,
        );
    }
}

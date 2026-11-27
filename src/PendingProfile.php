<?php

namespace Goodport\AutoModerator;

use Goodport\AutoModerator\Exceptions\InvalidInputException;
use Goodport\AutoModerator\Moderation\AuditContext;
use Goodport\AutoModerator\Moderation\Report;
use Goodport\AutoModerator\Orchestration\ModerationRunner;
use Goodport\AutoModerator\Orchestration\RunContextFactory;
use Goodport\AutoModerator\Profiles\ProfileResolver;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;

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

    private Model|Authenticatable|null $actor = null;

    private ?Model $subject = null;

    public function __construct(
        private readonly string $profile,
        private readonly ProfileResolver $resolver,
        private readonly ModerationRunner $runner,
        private readonly RunContextFactory $contextFactory,
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
     * Override the default application language.
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
     * Set the actor used by the automatic audit context.
     */
    public function actor(Model|Authenticatable $actor): self
    {
        $this->actor = $actor;

        return $this;
    }

    /**
     * Set the subject used by the automatic audit context.
     */
    public function subject(Model $subject): self
    {
        $this->subject = $subject;

        return $this;
    }

    /**
     * Override the automatic audit context.
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
            defaultLanguage: $this->contextFactory->defaultLanguage(),
            audit: $this->contextFactory->audit($this->audit, $this->actor, $this->subject),
        );
    }
}

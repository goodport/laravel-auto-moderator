<?php

namespace Goodport\AutoModerator;

use Goodport\AutoModerator\Exceptions\InvalidInputException;
use Goodport\AutoModerator\Moderation\AuditContext;
use Goodport\AutoModerator\Moderation\ModerationCaseResult;
use Goodport\AutoModerator\Moderation\ModerationField;
use Goodport\AutoModerator\Orchestration\ModerationCaseRunner;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;

/**
 * Fluent moderation case run across several fields.
 */
final class PendingModerationCase
{
    /**
     * @var array<string, ModerationField>
     */
    private array $fields = [];

    private ?string $language = null;

    private ?AuditContext $audit = null;

    private Model|Authenticatable|null $actor = null;

    private ?Model $subject = null;

    public function __construct(private readonly ModerationCaseRunner $runner) {}

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
     * Override the automatic audit context.
     */
    public function audit(AuditContext $audit): self
    {
        $this->audit = $audit;

        return $this;
    }

    /**
     * Add one field to the case.
     *
     * @param  array<string, mixed>  $input
     */
    public function field(string $key, string $label, string $profile, array $input): self
    {
        $this->guardField($key, $label, $profile);

        $this->fields[$key] = new ModerationField($key, $label, $profile, $input);

        return $this;
    }

    public function run(): ModerationCaseResult
    {
        return $this->runner->run(
            fields: array_values($this->fields),
            language: $this->language,
            actor: $this->actor,
            subject: $this->subject,
            audit: $this->audit,
        );
    }

    private function guardField(string $key, string $label, string $profile): void
    {
        if (trim($key) === '') {
            throw InvalidInputException::emptyModerationFieldKey();
        }

        if (trim($label) === '') {
            throw InvalidInputException::emptyModerationFieldLabel();
        }

        if (trim($profile) === '') {
            throw InvalidInputException::emptyModerationFieldProfile();
        }

        if (array_key_exists($key, $this->fields)) {
            throw InvalidInputException::duplicateModerationFieldKey($key);
        }
    }
}

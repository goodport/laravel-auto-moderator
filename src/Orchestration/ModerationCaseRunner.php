<?php

namespace Goodport\AutoModerator\Orchestration;

use Goodport\AutoModerator\Moderation\AuditContext;
use Goodport\AutoModerator\Moderation\ModerationCaseResult;
use Goodport\AutoModerator\Moderation\ModerationField;
use Goodport\AutoModerator\Moderation\ModerationFieldResult;
use Goodport\AutoModerator\Profiles\ProfileResolver;
use Goodport\AutoModerator\Profiles\ResolvedProfile;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;

/**
 * Runs moderation case fields in order and aggregates their results.
 */
final class ModerationCaseRunner
{
    public function __construct(
        private readonly ProfileResolver $resolver,
        private readonly ModerationRunner $runner,
        private readonly RunContextFactory $contextFactory,
    ) {}

    /**
     * @param  array<int, ModerationField>  $fields
     */
    public function run(
        array $fields,
        ?string $language,
        Model|Authenticatable|null $actor,
        ?Model $subject,
        ?AuditContext $audit,
    ): ModerationCaseResult {
        $resolvedAudit = $this->contextFactory->audit($audit, $actor, $subject);
        $defaultLanguage = $this->contextFactory->defaultLanguage();
        $profiles = $this->resolveProfiles($fields);

        $results = [];

        foreach ($fields as $field) {
            $resolvedProfile = $profiles[$field->key] ?? null;

            if ($resolvedProfile === null) {
                $results[$field->key] = ModerationFieldResult::skipped($field);

                continue;
            }

            $report = $this->runner->run(
                profile: $resolvedProfile,
                fields: $field->input,
                language: $language,
                defaultLanguage: $defaultLanguage,
                audit: $resolvedAudit,
            );

            $results[$field->key] = ModerationFieldResult::checked($field, $report);
        }

        return new ModerationCaseResult($results);
    }

    /**
     * Resolve every non-empty field profile before any check runs.
     *
     * @param  array<int, ModerationField>  $fields
     * @return array<string, ResolvedProfile>
     */
    private function resolveProfiles(array $fields): array
    {
        $profiles = [];

        foreach ($fields as $field) {
            if ($field->isEmpty()) {
                continue;
            }

            $profiles[$field->key] = $this->resolver->resolve($field->profile);
        }

        return $profiles;
    }
}

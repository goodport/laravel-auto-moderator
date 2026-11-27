<?php

namespace Goodport\AutoModerator\Contracts;

use Goodport\AutoModerator\Moderation\ModerationField;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;

/**
 * Contract for a model that can be moderated in the background.
 */
interface Moderatable
{
    /**
     * The scenario key stored on the moderation run.
     */
    public function moderationScenario(): string;

    /**
     * Fields to moderate. Each field carries its own profile.
     *
     * @return array<int, ModerationField>
     */
    public function moderationFields(): array;

    /**
     * The actor used by the audit context.
     */
    public function moderationActor(): Model|Authenticatable|null;

    /**
     * The run language passed to every field, or null to use check defaults.
     */
    public function moderationLanguage(): ?string;
}

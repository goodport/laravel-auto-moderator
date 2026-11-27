<?php

namespace Goodport\AutoModerator\Tests\Fixtures;

use Goodport\AutoModerator\Contracts\Moderatable;
use Goodport\AutoModerator\Moderation\ModerationField;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;

final class ModeratableSubject extends Model implements Moderatable
{
    protected $table = 'moderatable_subjects';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'fields' => 'array',
        ];
    }

    public function moderationScenario(): string
    {
        return $this->scenario;
    }

    public function moderationFields(): array
    {
        /** @var array<int, array{key: string, label: string, profile: string, input: array<string, mixed>}> $fields */
        $fields = $this->fields ?? [];

        return array_map(
            fn (array $field): ModerationField => new ModerationField(
                key: $field['key'],
                label: $field['label'],
                profile: $field['profile'],
                input: $field['input'],
            ),
            $fields,
        );
    }

    public function moderationActor(): Model|Authenticatable|null
    {
        if ($this->actor_id === null) {
            return null;
        }

        $actor = new TestActor;
        $actor->id = $this->actor_id;

        return $actor;
    }

    public function moderationLanguage(): ?string
    {
        return $this->language;
    }
}

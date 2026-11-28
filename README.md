# Laravel Auto Moderator

Synchronous and background content moderation for Laravel applications.

The package runs an ordered list of checks over named input fields, collects one
report, and returns a final state: `passed`, `rejected`, `undecided`, or
`skipped`. The optional background layer queues moderation, stores automatic
results, and calls the registered result handler. The package does not know
application models, statuses, notifications, or domain effects. The run context
uses the authenticated user and the current request URL by default, and falls
back to the application locale for the language; each value can be overridden
per run.

## Requirements

- PHP 8.3 or newer;
- Laravel 12.62+ or 13.x;
- a configured AI provider in `config/ai.php` when the AI check is used;
- a database connection for the AI moderator audit table when the AI check is
  used (see below);
- a queue worker when background moderation is used.

The package depends on `goodport/laravel-ai-moderator` for AI checks and
policies and on `consoletvs/profanity` for profanity dictionaries. It has no
application models, statuses, translations, or admin screens. The background
layer owns two fixed tables and models.

## Installation

```bash
composer require goodport/laravel-auto-moderator
```

Publish the configuration:

```bash
php artisan vendor:publish --tag=auto-moderator-config
```

The AI dependency writes its own audit and journal tables. The package loads its
own migrations automatically, including the background moderation tables. Run
them once:

```bash
php artisan migrate
```

Publish the AI moderator configuration when you use the AI check:

```bash
php artisan vendor:publish --tag=ai-moderator-config
```

Set the Perspective key when the toxicity check is enabled:

```dotenv
PERSPECTIVE_API_KEY=your-key
```

## Quick start

Define the AI check instance and a profile in `config/auto-moderator.php`.
The examples assume `AiModerationCheck` is imported with
`use Goodport\AutoModerator\Checks\Ai\AiModerationCheck;`.

```php
'checks' => [
    'ai_text_name' => [
        'class' => AiModerationCheck::class,
        'settings' => ['check' => 'text_name'],
    ],
],

'profiles' => [
    'post' => [
        'enabled' => true,
        'stop_on_reject' => false,
        'checks' => [
            [
                'check' => 'stop_words',
                'settings' => [
                    'fields' => ['body'],
                    'words' => ['example-word', 'example phrase'],
                ],
            ],
            [
                'check' => 'profanity',
                'settings' => [
                    'fields' => ['body'],
                    'languages' => ['en', 'ru'],
                ],
            ],
            [
                'check' => 'language',
                'settings' => [
                    'fields' => ['body'],
                    'min_length' => 20,
                    'threshold' => 0.6,
                ],
            ],
            [
                'check' => 'toxicity',
                'settings' => [
                    'fields' => ['body'],
                    'threshold' => 0.7,
                ],
            ],
            [
                'check' => 'ai_text_name',
            ],
        ],
    ],
],
```

Run the profile:

```php
use Goodport\AutoModerator\Facades\AutoModerator;

$report = AutoModerator::profile('post')
    ->input([
        'title' => $post->title,
        'body' => $post->body,
    ])
    ->subject($post)
    ->language('en')
    ->run();

if ($report->rejected()) {
    // The application decides what to do with the content.
}

if ($report->undecided()) {
    // The result is not an approval. Retry, queue, or ask for a manual review.
}
```

The actor defaults to `Auth::user()`. The run language is resolved from
`->language()`, the check `language` setting, then `app()->getLocale()`. The
audit URL comes from the current request. Use explicit calls to override the
defaults:

```php
use Goodport\AutoModerator\Moderation\AuditContext;

$report = AutoModerator::profile('post')
    ->input(['body' => $post->body])
    ->subject($post)
    ->language('en')
    ->audit(new AuditContext(
        actorType: 'user',
        actorId: (string) $user->id,
        subjectType: 'post',
        subjectId: (string) $post->id,
        requestUrl: request()->fullUrl(),
    ))
    ->run();
```

## Run context

By default, each run uses:

- the actor from `Auth::user()`;
- the request URL from `request()->fullUrl()`;
- the language fallback from `app()->getLocale()`;
- no subject.

The run language is resolved in this order: `->language()`, the check
`language` setting, then `app()->getLocale()`.

`->actor($model)`, `->subject($model)`, `->language($locale)`, and
`->audit($context)` override these values. The actor accepts an Eloquent model
or any `Authenticatable`; the subject accepts an Eloquent model.

`auto-moderator.audit.enabled` (default `true`) controls the automatic audit
context. When it is disabled, no automatic context is built, but an explicit
`->audit()` still works. An explicit `->audit()` replaces the automatic context
entirely.

Synchronous runs do not persist the context. Background runs build an explicit
audit context from the actor and the moderated model. The AI check also forwards
audit data to the AI moderator audit table.

## Result semantics

| State | Meaning |
| --- | --- |
| `passed` | At least one check passed, none rejected, and none is undecided. |
| `rejected` | At least one check produced a blocking finding. |
| `undecided` | No rejection, but at least one check could not decide. Never an approval. |
| `skipped` | Every check was skipped or disabled. |

Aggregation order is `rejected` → `undecided` → `passed` → `skipped`.
A warning is a non-blocking finding and does not reject the report.

`Report` exposes:

```php
$report->state();        // ModerationState
$report->passed();       // true | false | null
$report->rejected();     // bool
$report->undecided();    // bool
$report->allSkipped();   // bool
$report->results();      // array<string, CheckResult>
$report->result('ai');   // ?CheckResult
$report->firstReason();  // ?string: first rejected/undecided reason or error
$report->messages();     // reasons and errors for checks that did not pass
$report->violations();  // blocking findings grouped by check
$report->warnings();    // non-blocking findings grouped by check
$report->findings();    // all findings grouped by check
$report->toArray();     // full array representation
```

`CheckResult` exposes `check`, `state`, `reason`, `findings`, `details`, and
`error`. A `Finding` exposes `code`, `message`, `severity`, `blocking`, and
`details`.

`firstReason()` returns the first reason or error from a rejected or undecided
check. Passed and skipped checks are ignored. It returns `null` when no check is
rejected or undecided.

## Moderation case

A moderation case runs several fields with one shared run context. The package
does not know application models, statuses, notifications, or translations; the
application keeps its own decision and persistence logic.

```php
use Goodport\AutoModerator\Facades\AutoModerator;

$result = AutoModerator::moderationCase()
    ->subject($post)
    ->actor($user)
    ->language('en')
    ->field(
        key: 'body',
        label: 'Post body',
        profile: 'post',
        input: ['body' => $post->body],
    )
    ->field(
        key: 'comment',
        label: 'Comment',
        profile: 'comment',
        input: ['body' => $comment->body],
    )
    ->run();

if ($result->rejected()) {
    // The application reads $result->firstReason() and changes its own state.
}
```

Rules:

- fields run in the order they are added;
- a field is skipped without resolving its profile when every input value is
  `null`, an empty string, a whitespace-only string, or an empty array; `0`,
  `false`, and non-empty arrays are present values;
- `actor`, `subject`, `language`, and `audit` apply to every field; the audit
  context is built once per case;
- `stop_on_reject` still stops a single field profile, not the case;
- every non-empty field profile is resolved before the first check runs, so an
  invalid later profile fails before earlier fields execute;
- aggregation is rejected → undecided → passed → skipped; an empty case and a
  case where every field is skipped are `skipped`, never approved;
- configuration, input, and check-contract errors throw; an unexpected check
  exception becomes `undecided` for that field and other fields still run;
- the synchronous case does not change application state, send notifications,
  log, sleep, or persist anything.

`ModerationCaseResult` exposes `state()`, `passed()`, `rejected()`,
`undecided()`, `allSkipped()`, `results()`, `result($key)`, `firstReason()`,
and `toArray()`. `firstReason()` returns `Report::firstReason()` of the first
rejected field in field order; if no field is rejected, it returns
`Report::firstReason()` of the first undecided field. Because
`Report::firstReason()` also covers undecided checks, a rejected field may
return an earlier undecided reason from the same profile.

`ModerationFieldResult` exposes `key`, `label`, `skipped`, `state()`, `report`,
and `toArray()`. `report` is `null` for a field that did not run. The
`toArray()` shape is `state`, `passed`, `rejected`, `undecided`, `all_skipped`,
`first_reason`, and `fields`; each field contains `key`, `label`, `skipped`,
`state`, and `report` (or `null`).

## Background moderation

The background layer queues moderation, stores automatic results, and exposes a
public outcome. The application implements two contracts, optionally registers a
result handler, and uses the public `AutoModerator` API. Internal models, jobs,
events, and storage stay inside the package.

```php
use Goodport\AutoModerator\Concerns\HasModerationRuns;
use Goodport\AutoModerator\Contracts\Moderatable;
use Goodport\AutoModerator\Contracts\ModerationSource;
use Goodport\AutoModerator\Moderation\ModerationField;

final class Post extends Model implements Moderatable
{
    use HasModerationRuns;

    public function moderationScenario(): string
    {
        return 'post';
    }

    public function moderationFields(): array
    {
        return [
            new ModerationField('body', 'Post body', 'post', ['body' => $this->body]),
            new ModerationField('title', 'Post title', 'title', ['text' => $this->title]),
        ];
    }

    public function moderationActor(): Model|Authenticatable|null
    {
        return $this->author;
    }

    public function moderationLanguage(): ?string
    {
        return 'en';
    }
}

final class PostSource implements ModerationSource
{
    public function pending(): iterable
    {
        return Post::query()->where('status', 'pending')->get();
    }
}
```

Register sources and an optional queue:

```php
'background' => [
    'sources' => [PostSource::class],
    'queue' => 'moderation',
    'unique_for_seconds' => 3600,
    'stale_after_minutes' => 60,
],
```

Use the public API:

```php
use Goodport\AutoModerator\Facades\AutoModerator;

AutoModerator::dispatch($post);
AutoModerator::dispatch($post, 60);

$outcome = AutoModerator::latest($post);
$outcomes = AutoModerator::latestFor($posts);
$statuses = AutoModerator::statuses();

AutoModerator::forget($post);
```

`forget()` removes all stored runs and results for the model; it does not cancel
an already queued job. `auto-moderator:dispatch` queues pending models from
registered sources. `auto-moderator:cleanup` marks stale `running` runs as
failed.

### Result handler

An application handler is a separate class implementing
`ModerationResultHandler`:

```php
use Goodport\AutoModerator\Contracts\ModerationResultHandler;
use Goodport\AutoModerator\Moderation\ModerationOutcome;

final class PostModerationHandler implements ModerationResultHandler
{
    public function handle(Model $model, ModerationOutcome $outcome): void
    {
        if ($outcome->isRejected()) {
            $reasons = $outcome->reasonsForFields(['body', 'title']);
            // ...
        }
    }
}
```

Register it by model class or mapped morph alias in a service provider:

```php
AutoModerator::registerHandler(Post::class, PostModerationHandler::class);

// Or by the morph alias when the model uses a morph map:
AutoModerator::registerHandler('post', PostModerationHandler::class);
```

The package calls the handler after a completed run for `approved`, `rejected`,
`needs_review`, and `skipped`; never for `running` or `failed`. A missing handler
is a no-op. A handler exception fails the queue job but does not change the
completed run status. Handlers should be idempotent because a retried job can
call them again.

### Outcome

`ModerationOutcome` exposes:

- `scenario` and `status`;
- `isRunning()`, `isApproved()`, `isRejected()`, `needsReview()`,
  `isSkipped()`, and `isFailed()`;
- `fields()` and `field($key)`;
- `failed($key)` and `failedAny($keys)` for rejected, undecided, or technically
  failed fields;
- `reasonsForFields($keys)` with ready `field_key => "Label: reason"` strings;
- `toArray()`.

`ModerationFieldOutcome` exposes `key`, `label`, `state`, `isPassed()`,
`isRejected()`, `isUndecided()`, `isSkipped()`, `needsReview()`,
`hasTechnicalError()`, `reason`, `error`, `details`, `findings`, and
`reasonsByCheck`. `details` merges check details in profile order; every finding
contains its `check_key`.

Public status values are `ModerationOutcome::STATUS_*`, and public field states
are `ModerationFieldOutcome::STATE_*`. Use `AutoModerator::statuses()` for
filters. The application does not import internal enums.

### Queries and scopes

The trait provides relations and local scopes:

```php
Post::query()->whereLatestModerationStatus('needs_review');
Post::query()->whereHasModerationStatus(['rejected', 'needs_review']);
Post::query()->whereHasModerationRun();
Post::query()->whereHasModerationResult('checklist');
Post::query()->whereLatestModerationResult('checklist', null, 'passed');
```

- `whereLatestModerationStatus` filters by the latest run;
- `whereHasModerationStatus` filters by any run;
- `whereHasModerationRun` checks any run, optionally within a scenario;
- `whereHasModerationResult` checks any run for a field result;
- `whereLatestModerationResult` checks the latest run for a field result.

Each scope accepts an optional scenario, and result scopes accept an optional
field state. Invalid statuses, states, scenarios, and field keys throw
`InvalidArgumentException`.

### Lifecycle and storage

- every pending item must be an Eloquent model implementing `Moderatable`;
  otherwise the command fails before queueing;
- `dispatch()` and `auto-moderator:dispatch` queue a unique job for
  `morph + id + scenario`;
- the job creates a run in `running`, builds `moderationCase()` from
  `moderationFields()`, and stores one result row per check;
- each field keeps its own profile; the scenario must match
  `moderationScenario()`;
- empty fields produce no result rows; declared fields are still visible in the
  outcome for terminal successful runs;
- technical check errors become `needs_review`, never `approved`;
- a configuration or job error marks the run `failed` and rethrows; failed runs
  do not call the handler;
- the package writes only its own background tables and does not change
  application state or record manual moderator decisions.

Run statuses: `running`, `approved`, `rejected`, `needs_review`, `skipped`,
`failed`. `needs_review` replaces a separate manual-review flag in the
application.

`latestFor()` accepts a homogeneous list of persisted models and returns
outcomes keyed by primary key in first-seen order. It uses a bounded number of
queries and does not create N+1. Models without runs are omitted. Mixed model
classes and unpersisted models throw `InvalidArgumentException`.

### Background imports

For the background layer, the application imports only:

- `AutoModerator`;
- `ModerationOutcome`, `ModerationFieldOutcome`, synchronous `Report`,
  `CheckResult`, `ModerationState`, `AuditContext`, `PendingProfile`, and
  `PendingModerationCase`;
- `Contracts\Moderatable`, `Contracts\ModerationSource`, and
  `Contracts\ModerationResultHandler`;
- `Moderation\ModerationField`;
- `Concerns\HasModerationRuns`.

Custom checks continue to use the existing public check contracts and DTOs.
Internal models, events, jobs, enums, readers, and table details are marked
`@internal` and must not be imported by the application. The background schema
belongs to the package: migrations are loaded automatically and are not
published.

## Profiles

A profile is an ordered list. The list order is the execution order.

```php
'profiles' => [
    'post' => [
        'enabled' => true,
        'stop_on_reject' => false,
        'checks' => [
            ['check' => 'stop_words', 'settings' => ['fields' => ['body'], 'words' => ['example']]],
            ['check' => 'ai_text_name'],
        ],
    ],
],
```

- `enabled` disables every check in the profile while keeping the structure
  valid.
- `stop_on_reject` stops after the first rejection. `undecided` and `skipped`
  never stop the pipeline. The default is `false` so the report contains every
  reason.
- A check key may appear only once in a profile. Unknown keys, duplicates, an
  empty list, unregistered check keys, and unknown entry keys are configuration
  errors.
- The global `auto-moderator.enabled` switch skips every profile without
  resolving check classes or settings or calling external services.

### Fallback profiles

Profiles are needed only for composite scenarios, deterministic checks, and
per-profile overrides. A single AI check runs by its AI check key: if
`auto-moderator.profiles.<key>` is absent but `config/ai-moderator.php` defines
a check with the same key, the package builds a one-check profile automatically.

The fallback profile:

- uses the registered AI check from `auto-moderator.fallback.ai_check`
  (default `ai`);
- sets `check = <profile key>` and no `fields`;
- is enabled and uses `auto-moderator.defaults.stop_on_reject`;
- returns its result under the registered AI check key (`ai` by default), not
  the profile key.

Explicit profiles always win. An invalid explicit profile is an error, not a
fallback. If neither a profile nor an AI check exists, the profile is unknown.
If the AI check exists but the fallback check is not registered, configuration
fails.

```php
AutoModerator::profile('text_name')->input(['text' => $text])->run();
```

## Check registry

The `checks` section maps a key to a class and optional default settings:

```php
'checks' => [
    'ai_post' => [
        'class' => AiModerationCheck::class,
        'settings' => [],
    ],
    'ai_comment' => [
        'class' => AiModerationCheck::class,
        'settings' => [],
    ],
],
```

Register the same class under several keys to run it with different settings.
Profile settings override registry defaults. Associative maps such as
`alphabets` are merged; lists such as `fields`, `words`, and `languages` are
replaced.

## Built-in checks

### Stop words

```php
[
    'check' => 'stop_words',
    'settings' => [
        'fields' => ['body'],
        'words' => ['example-word', 'example phrase'],
        'blocking' => true,
    ],
],
```

The package contains no word list. `words` is required and must not be empty.
Matching is case-insensitive, works with phrases, normalizes whitespace, and
respects word boundaries. Matched words are returned in
`details.matched_words`.

### Profanity

```php
[
    'check' => 'profanity',
    'settings' => [
        'fields' => ['body'],
        'languages' => ['en', 'ru'],
    ],
],
```

`languages` is required and contains dictionary codes from the installed
profanity package. Every code is validated against the dictionary; an unknown
code is a configuration error. The check ignores `->language()` on purpose: run
language must not disable a dictionary. Found words are returned with their
language in `details.matched_words`.

### Language

```php
[
    'check' => 'language',
    'settings' => [
        'fields' => ['body'],
        'language' => 'en',
        'min_length' => 20,
        'threshold' => 0.6,
    ],
],
```

The check counts letters that belong to the expected alphabet. The run language
is resolved from `->language()`, the check `language` setting, then
`app()->getLocale()`. An unknown language without a configured alphabet is a
configuration error. Text shorter than `min_length` or without letters is
skipped. Each field reports its `letters`, `matching`, and `ratio` in
`details.fields`.

The default alphabets are `en` and `ru`. Add or override them in the registry
settings:

```php
'language' => [
    'class' => LanguageCheck::class,
    'settings' => [
        'alphabets' => [
            'en' => 'abcdefghijklmnopqrstuvwxyz',
            'ru' => 'абвгдеёжзийклмнопрстуфхцчшщъыьэюя',
            'de' => 'abcdefghijklmnopqrstuvwxyzäöüß',
        ],
    ],
],
```

### Toxicity

```php
[
    'check' => 'toxicity',
    'settings' => [
        'fields' => ['body'],
        'language' => 'en',
        'threshold' => 0.7,
        'max_chars' => 10000,
    ],
],
```

Text longer than `max_chars` is truncated before it is sent. The default is
10000 characters. Truncated fields are listed in `details.truncated`.

The check calls the Perspective API through the Laravel HTTP client. The run
language is resolved from `->language()`, the check `language` setting, then
`app()->getLocale()`. Connection settings live in `services.perspective`:

```php
'services' => [
    'perspective' => [
        'url' => 'https://commentanalyzer.googleapis.com/v1alpha1/comments:analyze',
        'key' => env('PERSPECTIVE_API_KEY'),
        'timeout' => 10,
    ],
],
```

The key is required. A missing key is a configuration error. A transport error,
an HTTP error, or a malformed response produces `undecided`, never `passed`.
Scores are returned in `details.scores`.

### AI moderation

A direct AI check is the preferred mode for one AI check. For a single check,
the auto profile is optional: the fallback profile uses the AI check key
directly.

```php
AutoModerator::profile('text_name')->input(['text' => $text])->run();
```

Describe the check once in `config/ai-moderator.php` and reference it from the
auto moderator registry when you need overrides or several checks:

```php
'checks' => [
    'ai_text_name' => [
        'class' => AiModerationCheck::class,
        'settings' => ['check' => 'text_name'],
    ],
],
'profiles' => [
    'post' => [
        'checks' => [
            ['check' => 'ai_text_name'],
        ],
    ],
],
```

`config/ai-moderator.php` contains the check itself:

```php
'checks' => [
    'text_name' => [
        'kind' => 'verdict',
        'input' => [
            'fields' => [
                'text' => ['type' => 'text', 'label' => 'Name', 'max_chars' => 4000],
            ],
        ],
        'instructions' => 'Reject profanity, contact details, and advertising.',
        'violations' => [
            'profanity' => ['severity' => 'high', 'blocking' => true],
            'contacts' => ['severity' => 'high', 'blocking' => true],
            'ads' => ['severity' => 'medium', 'blocking' => false],
        ],
    ],
],
```

In direct mode:

- do not set `fields` in the auto moderator settings;
- the whole input array is passed to the AI check;
- the AI check selects its declared fields and ignores extra fields;
- the AI check key is written to the audit context as `policy`;
- the check result contains `details.check` and `details.result`.

Several AI checks can run in one profile through different registry keys:

```php
'checks' => [
    'ai_text_name' => [
        'class' => AiModerationCheck::class,
        'settings' => ['check' => 'text_name'],
    ],
    'ai_text_body' => [
        'class' => AiModerationCheck::class,
        'settings' => ['check' => 'text_body'],
    ],
],
'profiles' => [
    'post' => [
        'checks' => [
            ['check' => 'ai_text_name'],
            ['check' => 'ai_text_body'],
        ],
    ],
],
```

Policy mode remains available for group scenarios:

```php
[
    'check' => 'ai',
    'settings' => [
        'policy' => 'post',
        'fields' => ['body'],
    ],
],
```

Policy mode also needs the policy declaration in `config/ai-moderator.php`:

```php
'policies' => [
    'post' => ['checks' => ['text_name', 'text_body']],
],
```

Exactly one of `check` or `policy` is required. `fields` is required in policy
mode and forbidden in direct mode. An empty key, both keys, or `fields` in
direct mode is a configuration error. An unknown AI check or policy key is also
a configuration error.

The adapter does not rebuild prompts or schemas. It forwards input, language,
and audit context, then maps the AI result or report. The run language is
resolved from `->language()`, the check `language` setting, then
`app()->getLocale()`.

- A direct check or policy where every AI result is skipped becomes `skipped`,
  not `passed`.
- AI violations become findings. Reasons, labels, answers, and errors are kept
  in `details` (`details.result` for direct mode, `details.checks` for policy
  mode).
- AI configuration and input errors throw. Provider failures are normalized to
  `undecided`.
- The AI check does not accept a `blocking` setting; violation blocking belongs
  to the AI check or policy.

## Custom checks

Implement `ModerationCheck`:

```php
use Goodport\AutoModerator\Contracts\ModerationCheck;
use Goodport\AutoModerator\Exceptions\InvalidInputException;
use Goodport\AutoModerator\Moderation\CheckResult;
use Goodport\AutoModerator\Moderation\Finding;
use Goodport\AutoModerator\Moderation\FindingSeverity;
use Goodport\AutoModerator\Moderation\ModerationContext;

final class DuplicatePostCheck implements ModerationCheck
{
    public function check(ModerationContext $context): CheckResult
    {
        if (! array_key_exists('body', $context->fields)) {
            throw InvalidInputException::missingField($context->check, 'body');
        }

        $body = $context->fields['body'];

        if (! is_string($body)) {
            throw InvalidInputException::invalidText($context->check, 'body');
        }

        if ($body === '') {
            return CheckResult::rejectedResult(
                $context->check,
                'The body is empty.',
                [new Finding('empty_body', 'The body is empty.', FindingSeverity::High, true)],
            );
        }

        return CheckResult::passedResult($context->check);
    }
}
```

Register it and add it to a profile:

```php
'checks' => [
    'duplicate_post' => [
        'class' => DuplicatePostCheck::class,
        'settings' => [],
    ],
],
'profiles' => [
    'post' => [
        'checks' => [
            ['check' => 'duplicate_post'],
        ],
    ],
],
```

The check is resolved through the container, so constructor dependencies are
injected. It receives the full named input in `fields`, the resolved language,
the merged `settings`, and the audit context. Checks should not read
`config()` themselves.

Implement `ValidatesSettings` as well to validate settings while the profile is
being resolved:

```php
use Goodport\AutoModerator\Contracts\ValidatesSettings;

final class DuplicatePostCheck implements ModerationCheck, ValidatesSettings
{
    public function validateSettings(string $check, array $settings): void
    {
        // Throw InvalidConfigurationException for invalid settings.
    }

    // ...
}
```

Only a rejected result may contain a blocking finding, and it must contain at
least one. Any other state cannot contain one. A check that returns an
inconsistent result throws `InvalidCheckResultException`.

## Errors

- Configuration errors throw `InvalidConfigurationException`.
- Missing or invalid input throws `InvalidInputException`.
- An inconsistent check result throws `InvalidCheckResultException`.
- A non-empty `error` is allowed only with the `undecided` state; any other
  state with an error throws `InvalidCheckResultException`.
- An exception inside a check, a transport failure, or a malformed external
  response becomes `undecided`.
- `undecided` is never converted to `passed`.
- The package does not log. The application decides how to report the outcome.

## Limitations

- Synchronous runs are available directly; background moderation is optional
  and requires a queue worker.
- The background layer stores only automatic results in its two tables. Manual
  moderator decisions and all domain effects belong to the application.
- The package has no application models, statuses, translations, or admin
  interface. The background schema is fixed and not published.
- The AI dependency writes its own audit and journal tables. Run its migrations.
- The synchronous run context factory reads `Auth`, the current request, and the
  application locale by default. Checks never read them directly. Background
  runs build an explicit audit context from the actor and the moderated model.

## Development

```bash
composer test
composer format
composer validate --strict
```

CI runs the suite on SQLite and MySQL. To run the MySQL suite locally, set
`DB_CONNECTION=mysql` and the usual `DB_HOST`, `DB_PORT`, `DB_DATABASE`,
`DB_USERNAME`, and `DB_PASSWORD` environment variables.

## License

MIT. See [LICENSE](LICENSE).

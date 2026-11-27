# AGENTS.md

Technical description of `goodport/laravel-auto-moderator`.

## Purpose

The package is a content moderation orchestrator with a synchronous API and an
optional background layer.

Synchronous:

1. read an explicit or fallback profile and its ordered check list;
2. resolve registered checks and merge registry defaults with profile settings;
3. resolve the run language and build the audit context from the Laravel environment;
4. build one `ModerationContext` per check;
5. run checks in order and stop after the first rejection when configured;
6. aggregate results into one `Report`;
7. return `passed`, `rejected`, `undecided`, or `skipped`.

Background:

1. the dispatch command asks registered `ModerationSource` instances for pending models;
2. each model is queued as a unique `RunModerationJob` keyed by morph type, ID, and scenario;
3. the job creates a `ModerationRun`, builds a moderation case from
   `Moderatable::moderationFields()`, saves `ModerationResult` rows, sets the
   final run status, and publishes `ModerationCompleted`.

The package owns the background job, command, event, tables, and models. It does
not own application models, statuses, notifications, or domain effects, and it
stores only automatic moderation results. The AI check delegates the AI pipeline
to `goodport/laravel-ai-moderator`; the profanity check delegates dictionaries
to `consoletvs/profanity`.

## Directory map

```text
config/auto-moderator.php              publishable configuration
database/migrations/                   fixed background moderation schema
src/
  AutoModeratorManager.php             facade entry point
  AutoModeratorServiceProvider.php     config, bindings, migrations, command, publishing
  PendingProfile.php                   fluent input/language/actor/subject/audit/run
  PendingModerationCase.php            fluent field/language/actor/subject/audit/run
  Facades/AutoModerator.php            facade accessor
  Contracts/
    ModerationCheck.php                check contract
    ValidatesSettings.php              optional early settings validation contract
    Moderatable.php                    model that can be moderated in the background
    ModerationSource.php               source of pending models
  Moderation/
    ModerationState.php                passed | rejected | undecided | skipped
    ModerationRunStatus.php            running | approved | rejected | needs_review | skipped | failed
    FindingSeverity.php                low | medium | high
    AuditContext.php                   neutral audit DTO
    ModerationContext.php              check input: key, fields, language, settings, audit
    Finding.php                        one violation or warning
    CheckResult.php                    one check result
    Report.php                         aggregated profile result
    ModerationField.php                one case field description
    ModerationFieldResult.php          result of one case field
    ModerationCaseResult.php           aggregated case result
  Models/
    ModerationRun.php                  background run
    ModerationResult.php               one stored check result
  Jobs/
    RunModerationJob.php               unique background moderation attempt
  Events/
    ModerationCompleted.php            completed run event
  Console/
    DispatchModerationCommand.php      queues jobs for registered sources
  Exceptions/
    InvalidConfigurationException.php  configuration errors
    InvalidInputException.php          missing or invalid input
    InvalidCheckResultException.php    inconsistent custom check result
  Support/
    ConfigMerger.php                   maps merge, lists replace
    SettingsGuard.php                  shared built-in settings validation
    InputFields.php                    declared field selection
    TextFields.php                     text field selection and UTF-8 validation
  Checks/
    StopWords/                         configured words and phrases
    Profanity/                         dictionary languages and wrapper
    Language/                          alphabet ratio check
    Toxicity/                          Perspective API adapter
    Ai/                                direct check / policy adapter and mapper
  Profiles/
    ProfileConfig.php                  validated profile DTO
    ProfileConfigReader.php            explicit and fallback profile validation
    CheckFactory.php                   registry entry, settings merge, service, container
    ProfileResolver.php                thin orchestration
    ResolvedProfile.php                executable profile
    ResolvedCheck.php                  executable check entry
  Orchestration/
    ModerationRunner.php               ordered execution and aggregation
    ModerationCaseRunner.php           ordered case field execution and aggregation
    RunContextFactory.php              default actor, request URL, language, audit
tests/
  TestCase.php                         Testbench base with AI providers and SQLite
  Fixtures/                            custom check and service fixtures
  Feature/                             facade-level scenarios
  Unit/                                report, result, and merge logic
```

## Public API

```php
use Goodport\AutoModerator\Facades\AutoModerator;

$report = AutoModerator::profile('post')
    ->input(['body' => $body])
    ->subject($post)
    ->run();
```

A single AI check can run by its AI check key. The fallback profile returns its
result under the registered AI check key (`ai` by default), not the profile key.

A moderation case runs several fields with one shared run context:

```php
$result = AutoModerator::moderationCase()
    ->subject($post)
    ->language('en')
    ->field(key: 'body', label: 'Post body', profile: 'post', input: ['body' => $body])
    ->field(key: 'comment', label: 'Comment', profile: 'comment', input: ['body' => $comment])
    ->run();
```

- `AutoModerator::profile(string $profile): PendingProfile`
- `PendingProfile::input(array $fields): self`
- `PendingProfile::language(string $language): self`
- `PendingProfile::actor(Model|Authenticatable $actor): self`
- `PendingProfile::subject(Model $subject): self`
- `PendingProfile::audit(AuditContext $audit): self`
- `PendingProfile::run(): Report`
- `AutoModerator::moderationCase(): PendingModerationCase`
- `PendingModerationCase::field(string $key, string $label, string $profile, array $input): self`
- `PendingModerationCase::language(string $language): self`
- `PendingModerationCase::actor(Model|Authenticatable $actor): self`
- `PendingModerationCase::subject(Model $subject): self`
- `PendingModerationCase::audit(AuditContext $audit): self`
- `PendingModerationCase::run(): ModerationCaseResult`

`ModerationCaseResult` exposes `state()`, `passed()`, `rejected()`,
`undecided()`, `allSkipped()`, `results()`, `result($key)`, `firstReason()`,
and `toArray()`. `ModerationFieldResult` exposes `key`, `label`, `skipped`,
`state()`, `report`, and `toArray()`; `report` is `null` for a skipped field.

`Report` exposes `state()`, `passed()`, `rejected()`, `undecided()`,
`allSkipped()`, `results()`, `result()`, `firstReason()`, `messages()`,
`violations()`, `warnings()`, `findings()`, and `toArray()`.

`CheckResult` exposes `check`, `state`, `reason`, `findings`, `details`, and
`error`, plus `passed()`, `rejected()`, `undecided()`, `skipped()`, and
`toArray()`. Static factories are `passedResult()`, `rejectedResult()`,
`undecidedResult()`, and `skippedResult()`.

## Background moderation

- `Contracts\Moderatable` — application model contract: `moderationScenario()`,
  `moderationFields()` (an array of `ModerationField`), `moderationActor()`, and
  `moderationLanguage()`.
- `Contracts\ModerationSource` — `pending(): iterable` returns models to queue.
- `Jobs\RunModerationJob` — accepts only the morph type, ID, and scenario. It is
  unique for `morph + id + scenario` with a finite
  `background.unique_for_seconds` lock.
- `Console\DispatchModerationCommand` — `auto-moderator:dispatch` iterates
  `auto-moderator.background.sources` and queues one job per pending model.
- `Console\CleanupStaleModerationRunsCommand` — `auto-moderator:cleanup` marks
  `running` runs older than `background.stale_after_minutes` as `failed`.
- `Models\ModerationRun` — `moderatable()` and `results()` relations; `status`
  casts to `ModerationRunStatus`.
- `Models\ModerationResult` — `moderationRun()` relation; `state` casts to
  `ModerationState`, `findings` and `details` cast to arrays.
- `Events\ModerationCompleted` — carries the saved `ModerationRun` with loaded
  results. It is published after the run is stored and is not published for
  `failed` runs.

A record in `auto_moderator_runs` is one attempt, not a job state. The job
creates it in `running` with `started_at`, and a retry creates a new attempt
while the failed attempt stays in history. The scenario passed to the job must
match `Moderatable::moderationScenario()`.

The run context is built explicitly by the job: `context.language` is
`moderationLanguage()` as returned (including `null`), and `context.audit` is
built from `moderationActor()` and the moderated model with `request_url` set to
`null`. `AuditContext::fromActorAndSubject()` is shared by synchronous and
background runs.

Results are written per check: one `ModerationResult` row per `CheckResult` of
a non-skipped field with `field_key`, `check_key`, `state`, `reason`, `error`,
`findings`, and `details`. `reason` is the moderation reason; `error` is a
technical check failure. Skipped checks of non-empty fields are stored; empty
fields produce no rows.

Run status mapping: any rejection → `rejected`; otherwise any undecided →
`needs_review`; otherwise any passed → `approved`; otherwise `skipped`. There is
no separate manual-review flag in this layer.

A configuration or job error marks the run `failed`, sets `finished_at` and
`reason`, and rethrows. Missing models return silently. Not-`Moderatable` models
and scenario mismatches fail the run. Sources that return a non-`Moderatable`
model fail before a job is queued.

## Configuration resolution

- `ProfileConfigReader` validates the profile structure, global switch, global
  defaults, and profile entries. It normalizes every entry to
  `check` + `enabled` + `settings`.
- A profile is explicit when `auto-moderator.profiles.<key>` exists. An invalid
  explicit profile is an error, never a fallback. When the profile is absent,
  the reader looks for `ai-moderator.checks.<key>` and builds a fallback profile
  from one AI check.
- The fallback check key is `auto-moderator.fallback.ai_check` (default `ai`).
  The fallback profile sets `check = <profile key>`, no `fields`, `enabled`
  true, and the default `stop_on_reject`; its result is keyed by the fallback
  check key. If the AI check exists but the fallback check is not registered,
  configuration fails. If neither the profile nor the AI check exists, the
  profile is unknown.
- `CheckFactory::definition()` reads and validates the registry entry for every
  listed check, including disabled checks.
- `CheckFactory::make()` merges registry settings with profile overrides,
  resolves a `service` reference through `auto-moderator.services.*`, resolves
  the class through the container, and calls `ValidatesSettings` when
  implemented.
- `ProfileResolver` is a thin orchestrator. Disabled checks and disabled
  profiles produce `skipped` entries without resolving a class or settings.
- Checks receive merged settings in `ModerationContext->settings`. Checks must
  not read configuration directly.
- `RunContextFactory` resolves the fallback language and the default audit
  context. `auto-moderator.audit.enabled` controls the automatic audit.
  Explicit `->language()` and `->audit()` calls override the defaults.
- The run language is resolved from `->language()`, the check `language`
  setting, then `app()->getLocale()`. The default audit actor is `Auth::user()`,
  the request URL is `request()->fullUrl()`, and the subject is the explicit
  `->subject()` model. An Eloquent actor uses `getMorphClass()`/`getKey()`;
  another `Authenticatable` uses its class and `getAuthIdentifier()`.

Merge rules: associative maps merge recursively; lists replace as a whole.
Unknown profile, entry, registry, and settings keys are configuration errors.

## Result semantics

- Any rejection makes the report `rejected`.
- Otherwise any undecided makes it `undecided`.
- Otherwise any passed check makes it `passed`.
- Otherwise the report is `skipped`.
- `undecided` is never an approval: `Report::passed()` returns `null`.
- A warning is a non-blocking finding. It does not reject the report.
- Only a rejected check result may contain a blocking finding, and it must
  contain at least one. Violations throw `InvalidCheckResultException`.
- A check exception or an external failure becomes `undecided`.
- Configuration and input errors throw.
- The AI adapter deliberately detects the case where every AI result is
  skipped, because the AI report treats that case as passed.
- `Report::firstReason()` returns the first reason or error from a rejected or
  undecided check. Passed and skipped checks are ignored; otherwise it returns
  `null`.
- A moderation case uses the same aggregation over its fields: rejected →
  undecided → passed → skipped. A case without fields and a case where every
  field is skipped are `skipped`, never approved.
- A case field is skipped when every input value is `null`, an empty string, a
  whitespace-only string, or an empty array. A skipped field is not resolved and
  has no report. Strings are checked, not trimmed or changed.
- `ModerationCaseResult::firstReason()` returns `Report::firstReason()` of the
  first rejected field in field order, then of the first undecided field. A
  rejected field may therefore return an earlier undecided reason from the same
  profile.
- The case resolves every non-empty field profile before running any check, so
  an invalid later profile fails before earlier fields run or produce audit
  records.
- The case resolves its audit context and default language once and shares them
  with every field. An explicit `->audit()` still wins; `actor`, `subject`, and
  `language` apply to all fields.
- A case keeps configuration, input, and check-contract errors visible. An
  unexpected exception inside a check becomes `undecided` for that field, and
  the remaining fields still run.
- Duplicate case field keys, empty keys, empty labels, and empty profile names
  throw `InvalidInputException` when the field is added.

## Extension points

- `Contracts\ModerationCheck` — application checks.
- `Contracts\ValidatesSettings` — optional early validation for custom settings.
- `Contracts\Moderatable` — application model that can be moderated in the
  background.
- `Contracts\ModerationSource` — application source of pending models.
- `config/auto-moderator.php`:
  - `enabled` — global switch;
  - `defaults` — shared profile defaults;
  - `audit` — automatic audit switch;
  - `fallback.ai_check` — registered AI check used by fallback profiles;
  - `checks` — registry key → class + default settings;
  - `services` — named external service settings;
  - `background.sources` — source class names resolved through the container;
  - `background.queue` — queue name, or `null` for the default queue;
  - `background.unique_for_seconds` — finite unique lock lifetime;
  - `background.stale_after_minutes` — age after which a `running` run is marked
    failed;
  - `profiles` — ordered check lists.
- `Checks\Ai\AiModerationCheck` — a direct AI check or policy adapter. The check
  or policy itself is defined in `config/ai-moderator.php`.
- `Moderation\AuditContext` — neutral audit context mapped to the AI
  moderator DTO inside the AI check.
- `Orchestration\RunContextFactory` — the only place that reads `Auth`, the
  current request, and the application locale.

## Built-in checks

- Stop words: required `fields`, required `words`, optional `blocking`.
  Case-insensitive, phrase-aware, boundary-safe. No built-in list.
- Profanity: required `fields`, required `languages`, optional `blocking`.
  Languages are validated against the installed dictionary. `->language()` is
  ignored.
- Language: required `fields`, default alphabets for `en` and `ru`, optional
  `language`, `min_length`, `threshold`, `blocking`. Short text and text
  without letters are skipped. Unknown language is a configuration error.
- Toxicity: required `fields`, optional `language`, `threshold`, `max_chars`,
  `blocking`. Text longer than `max_chars` is truncated before the request.
  Connection settings live in `services.perspective`. Missing key, HTTP errors,
  and malformed responses are handled explicitly; failures are `undecided`.
- AI: exactly one of `check` or `policy`, optional `language`. Direct `check`
  must not set `fields`; the AI check declares its own fields. Policy mode
  requires `fields`. No `blocking` setting. The AI result or report drives the
  state and findings. Direct mode writes the AI check key as the audit policy.
  A single AI check runs without an explicit auto profile through the fallback
  profile; its result key is the fallback check key (`ai` by default).

## Tests

```bash
composer test
composer format
composer validate --strict
```

Tests use PHPUnit and Orchestra Testbench. Feature tests cover profile order,
stop-on-reject, warnings, all-skipped, every built-in check, a custom check,
direct and policy AI checks, fallback profiles, AI fake agents, Perspective
HTTP fakes, automatic run context, configuration/input errors, moderation case
order, empty fields, shared context, collected reasons, duplicate field keys,
background run creation, result mapping, run statuses, failed and missing
models, morph maps, unique dispatch, source traversal, and the completion event.
Unit tests cover report aggregation, case aggregation, field emptiness, result
invariants, single AI result mapping, fallback profile resolution, and config
merging.

The base test case registers the AI, journal, resilience, AI moderator, and
auto moderator providers and uses an in-memory SQLite database for the AI
dependency tables. Background tests load an extra fixture migration for their
test model.

## Maintenance rules

- Do not add application models, statuses, notifications, translations, admin
  screens, or browser tests.
- The background layer owns only `auto_moderator_runs`, `auto_moderator_results`,
  their models, the job, the command, and the event. Do not add domain effects,
  manual moderator decisions, or application-specific branches.
- Reuse `moderationCase()` in the background job. Do not duplicate the check
  engine.
- Do not copy AI moderator internals. Use its public check, policy, report, and
  audit API only.
- Keep `config/ai-moderator.php` the only source of AI check fields and prompts.
  A single AI check uses the fallback profile; do not add duplicate one-check
  auto profiles.
- Do not add a built-in stop word list.
- Do not read `config()` inside checks. For synchronous runs,
  `RunContextFactory` is the only place that reads `Auth`, the current request,
  or `App::getLocale()`. Background runs build an explicit audit context in the
  job.
- Do not treat `undecided` as approval.
- Keep public documentation and code comments in English.
- Update this file and `README.md` when the public API or configuration changes.

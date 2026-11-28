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
   final run status, calls the registered result handler, and publishes the
   internal `ModerationCompleted` event.

The package owns the background job, command, event, tables, models, outcome
reader, and handler registry. It does not own application models, statuses,
notifications, or domain effects, and it stores only automatic moderation
results. The application integrates through `AutoModerator`, the public DTOs,
the contracts, and `Concerns\HasModerationRuns`; internal classes are
`@internal`. The AI check delegates the AI pipeline to
`goodport/laravel-ai-moderator`; the profanity check delegates dictionaries to
`consoletvs/profanity`.

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
    ModerationResultHandler.php        application handler for completed runs
  Concerns/
    HasModerationRuns.php              public relations and query scopes
  Moderation/
    ModerationState.php                passed | rejected | undecided | skipped
    ModerationRunStatus.php            internal run status enum
    ModerationOutcome.php              public background run outcome
    ModerationFieldOutcome.php         public field outcome
    ModerationOutcomeReader.php        internal outcome reader
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
    ModerationRun.php                  internal background run
    ModerationResult.php               internal stored check result
  Jobs/
    RunModerationJob.php               internal unique background attempt
  Events/
    ModerationCompleted.php            internal completed run event
  Console/
    DispatchModerationCommand.php      internal queue dispatcher
    CleanupStaleModerationRunsCommand.php internal stale run cleanup
  Exceptions/
    InvalidConfigurationException.php  configuration errors
    InvalidInputException.php          missing or invalid input
    InvalidCheckResultException.php    inconsistent custom check result
  Support/
    ConfigMerger.php                   maps merge, lists replace
    SettingsGuard.php                  shared built-in settings validation
    InputFields.php                    declared field selection
    TextFields.php                     text field selection and UTF-8 validation
    ModerationQueryGuard.php           internal status/state/scenario validation
    ModerationValue.php                internal non-empty string helpers
    ModerationResultHandlerRegistry.php internal handler registry
    ModerationRunForgetter.php         internal run and result deletion
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
    ModerationDispatcher.php           internal background job dispatcher
    RunContextFactory.php              default actor, request URL, language, audit
tests/
  TestCase.php                         Testbench base with AI providers and SQLite
  Fixtures/                            custom check and service fixtures
  Feature/                             facade-level scenarios
    Background/                        background API, handlers, outcomes, scopes
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

Public API:

- `AutoModerator::dispatch(Model $model, ?int $delaySeconds = null): void` —
  queues a unique run for `morph + id + scenario`; the scenario comes from
  `Moderatable::moderationScenario()`. Null or zero delay dispatches
  immediately; a negative delay is `InvalidArgumentException`.
- `AutoModerator::latest(Model $model, ?string $scenario = null): ?ModerationOutcome`
  — the latest run by maximum run ID, optionally within a scenario.
- `AutoModerator::latestFor(iterable $models, ?string $scenario = null): array`
  — outcomes for a homogeneous list of persisted models, keyed by primary key
  in first-seen order. It uses a bounded number of queries. Mixed model classes,
  unpersisted models, and non-`Moderatable` models are `InvalidArgumentException`.
- `AutoModerator::statuses(): array` — public status strings.
- `AutoModerator::registerHandler(string $modelClass, string $handlerClass): void`
  — validates both classes and stores the mapping; the last registration wins.
- `AutoModerator::forget(Model $model): void` — deletes all runs and results of
  the model in a transaction, without events.

Public DTOs:

- `ModerationOutcome` — `scenario`, `status`, immutable `fields` map;
  `isRunning`, `isApproved`, `isRejected`, `needsReview`, `isSkipped`,
  `isFailed`, `fields()`, `field()`, `failed()`, `failedAny()`,
  `reasonsForFields()`, `toArray()`. Status constants are `STATUS_*`; status
  values are strings.
- `ModerationFieldOutcome` — `key`, `label`, `state`, `isPassed`, `isRejected`,
  `isUndecided`, `isSkipped`, `needsReview`, `hasTechnicalError`, `reason`,
  `error`, `details`, `findings`, `reasonsByCheck`, `toArray()`. State constants
  are `STATE_*`; state values are strings.
- `failed()` covers rejected, undecided, and technical errors. A technical error
  is represented by an undecided check with `error`; it raises the case and run
  to `needs_review`.
- `details` merges check details with `array_replace` in profile order; later
  checks win. `findings` is a flat list with `check_key` added to each finding.
  `reasonsByCheck` includes rejected, undecided, and checks with an error.
- `reasonsForFields()` returns `field_key => "Label: reason"` for failed fields
  using `reason ?? error`; passed and skipped fields are omitted.
- The DTO never exposes the run model, result model, or internal identifiers.

Public trait `Concerns\HasModerationRuns`:

- relations `moderationRuns()` and `latestModerationRun()`;
- `whereLatestModerationStatus(string|array $status, ?string $scenario = null)`;
- `whereHasModerationStatus(string|array $status, ?string $scenario = null)`;
- `whereHasModerationRun(?string $scenario = null)`;
- `whereHasModerationResult(string $fieldKey, ?string $scenario = null, ?string $state = null)`;
- `whereLatestModerationResult(string $fieldKey, ?string $scenario = null, ?string $state = null)`.

Status and state values are validated against the public lists; invalid values
throw `InvalidArgumentException`. The latest scopes use a correlated
`not exists` subquery, so they do not create N+1.

Handler contract:

- `Contracts\ModerationResultHandler::handle(Model $model, ModerationOutcome $outcome): void`.
- The job looks up `$model::class`, then `getMorphClass()`. Registration accepts
  a model class or a mapped morph alias. A missing handler is a no-op. The
  handler is called after commit and before the internal event, only for
  `approved`, `rejected`, `needs_review`, and `skipped`.
- A handler exception propagates to the queue/worker and does not change the
  completed run status. Handlers must be idempotent because a retry can call
  them again.

Internal implementation:

- `Jobs\RunModerationJob`, `Models\ModerationRun`, `Models\ModerationResult`,
  `Events\ModerationCompleted`, `ModerationRunStatus`, the commands, the reader,
  registry, forgetter, dispatcher, and table schema are `@internal`.
- A record in `auto_moderator_runs` is one attempt, not a job state. The job
  creates it in `running` with `started_at`; a retry creates a new attempt while
  the failed attempt stays in history.
- The run stores `field_labels` from `moderationFields()` at start. Terminal
  successful outcomes include every declared field; a declared field without a
  result row is `skipped`. Running and failed outcomes include only real result
  rows. Old runs without labels use the field key as the label and include only
  result fields.
- The context is built explicitly by the job: `context.language` is
  `moderationLanguage()` as returned, and `context.audit` is built from
  `moderationActor()` and the moderated model with `request_url = null`.
- Results are written per check with `field_key`, `check_key`, `state`,
  `reason`, `error`, `findings`, `details`. `reason` is the moderation reason;
  `error` is a technical failure. Skipped checks of non-empty fields are stored;
  empty fields produce no rows.
- Run status mapping: any rejection → `rejected`; otherwise any `undecided`
  (including technical errors) → `needs_review`; otherwise any passed →
  `approved`; otherwise `skipped`.
- A configuration or job error marks the run `failed`, sets `finished_at` and
  `reason`, and rethrows. Missing models return silently. Not-`Moderatable`
  models and scenario mismatches fail the run. Sources returning a
  non-`Moderatable` model fail before a job is queued.

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
- Background field state uses the same priority: rejected → rejected; otherwise
  undecided → undecided; otherwise passed → passed; otherwise skipped. A
  technical error is represented by an undecided check with `error`.
- Empty `reason` and `error` strings are treated as absent. The first non-empty
  reason and the first non-empty error win; an empty reason does not hide a
  later reason or a technical error.
- Background run status maps the case state: rejected → `rejected`; undecided →
  `needs_review`; passed → `approved`; skipped → `skipped`. A technical error is
  never `approved`.
- Run and field state priority is centralized in `ModerationState::aggregate`;
  sync and background APIs use one state-based rule. A non-empty `error` is
  valid only with the `undecided` state; any other combination throws
  `InvalidCheckResultException`.
- Field `details` merge with `array_replace` in profile order; later checks win.
  `findings` is a flat list with `check_key` in every element. `reasonsByCheck`
  includes rejected, undecided, and checks with an error. `reasonsForFields()`
  skips passed and skipped fields and uses `reason ?? error`.
- Background outcomes expose only public strings, arrays, and DTOs; they never
  expose `ModerationRun` or `ModerationResult`.
- Handler exceptions propagate to the queue and do not change a completed run.
  The handler is called once per job attempt, after commit and before the
  internal event.

## Extension points

- `Contracts\ModerationCheck` — application checks.
- `Contracts\ValidatesSettings` — optional early validation for custom settings.
- `Contracts\Moderatable` — application model that can be moderated in the
  background.
- `Contracts\ModerationSource` — application source of pending models.
- `Contracts\ModerationResultHandler` — application handler called by the job
  for completed runs.
- `Concerns\HasModerationRuns` — relations and query scopes for moderated
  models.
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
models, morph maps, unique dispatch, source traversal, the completion event,
result handlers, latest and latestFor outcomes, N+1-free bulk reads, query
scopes, delayed dispatch, and forget. Unit tests cover report aggregation, case
aggregation, field emptiness, result invariants, public outcome DTOs, single AI
result mapping, fallback profile resolution, and config merging.

The base test case registers the AI, journal, resilience, AI moderator, and
auto moderator providers and uses an in-memory SQLite database by default.
CI runs the suite on SQLite and MySQL; the MySQL job uses the environment
database configuration. Background tests load an extra fixture migration for
their test model.

## Maintenance rules

- Application-facing background imports are limited to `AutoModerator`,
  `ModerationOutcome`, `ModerationFieldOutcome`, synchronous `Report`,
  `CheckResult`, `ModerationState`, `AuditContext`, `PendingProfile`,
  `PendingModerationCase`, `Contracts\Moderatable`, `Contracts\ModerationSource`,
  `Contracts\ModerationResultHandler`, `Moderation\ModerationField`, and
  `Concerns\HasModerationRuns`. Keep every other background class `@internal`.
- Custom checks keep using the public check contracts and DTOs
  (`ModerationCheck`, `ValidatesSettings`, `CheckResult`, `Finding`,
  `FindingSeverity`, `ModerationContext`).
- Do not expose `ModerationRun`, `ModerationResult`, `RunModerationJob`,
  `ModerationCompleted`, `ModerationRunStatus`, or table columns through public
  DTOs, scopes, or documentation.
- Do not add application models, statuses, notifications, translations, admin
  screens, or browser tests.
- The background layer owns only `auto_moderator_runs`, `auto_moderator_results`,
  their models, the job, the commands, the internal event, the outcome reader,
  the handler registry, the dispatcher, and the forgetter. Do not add domain
  effects, manual moderator decisions, or application-specific branches.
- Call result handlers from the job after commit and before the internal event.
  Do not make the event the application extension point.
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

# AGENTS.md

Technical description of `goodport/laravel-auto-moderator`.

## Purpose

The package is a synchronous content moderation orchestrator:

1. read a profile and its ordered check list;
2. resolve registered checks and merge registry defaults with profile settings;
3. build one `ModerationContext` per check;
4. run checks in order and stop after the first rejection when configured;
5. aggregate results into one `Report`;
6. return `passed`, `rejected`, `undecided`, or `skipped`.

The package owns no models, migrations, statuses, queues, translations, admin
screens, or persistence. The AI check delegates the AI pipeline to
`goodport/laravel-ai-moderator`; the profanity check delegates dictionaries to
`consoletvs/profanity`.

## Directory map

```text
config/auto-moderator.php              publishable configuration
src/
  AutoModeratorManager.php             facade entry point
  AutoModeratorServiceProvider.php     config merge, bindings, config publishing
  PendingProfile.php                   fluent input/language/audit/run
  Facades/AutoModerator.php            facade accessor
  Contracts/
    ModerationCheck.php                check contract
    ValidatesSettings.php              optional early settings validation contract
  Moderation/
    ModerationState.php                passed | rejected | undecided | skipped
    FindingSeverity.php                low | medium | high
    AuditContext.php                   neutral audit DTO
    ModerationContext.php              check input: key, fields, language, settings, audit
    Finding.php                        one violation or warning
    CheckResult.php                    one check result
    Report.php                         aggregated profile result
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
    Ai/                                AI policy adapter and report mapper
  Profiles/
    ProfileConfig.php                  validated profile DTO
    ProfileConfigReader.php            profile structure and defaults validation
    CheckFactory.php                   registry entry, settings merge, service, container
    ProfileResolver.php                thin orchestration
    ResolvedProfile.php                executable profile
    ResolvedCheck.php                  executable check entry
  Orchestration/
    ModerationRunner.php               ordered execution and aggregation
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
    ->language('en')
    ->audit(new AuditContext(...))
    ->run();
```

- `AutoModerator::profile(string $profile): PendingProfile`
- `PendingProfile::input(array $fields): self`
- `PendingProfile::language(string $language): self`
- `PendingProfile::audit(AuditContext $audit): self`
- `PendingProfile::run(): Report`

`Report` exposes `state()`, `passed()`, `rejected()`, `undecided()`,
`allSkipped()`, `results()`, `result()`, `messages()`, `violations()`,
`warnings()`, `findings()`, and `toArray()`.

`CheckResult` exposes `check`, `state`, `reason`, `findings`, `details`, and
`error`, plus `passed()`, `rejected()`, `undecided()`, `skipped()`, and
`toArray()`. Static factories are `passedResult()`, `rejectedResult()`,
`undecidedResult()`, and `skippedResult()`.

## Configuration resolution

- `ProfileConfigReader` validates the profile structure, global switch, global
  defaults, and profile entries. It normalizes every entry to
  `check` + `enabled` + `settings`.
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

## Extension points

- `Contracts\ModerationCheck` — application checks.
- `Contracts\ValidatesSettings` — optional early validation for custom settings.
- `config/auto-moderator.php`:
  - `enabled` — global switch;
  - `defaults` — shared profile defaults;
  - `checks` — registry key → class + default settings;
  - `services` — named external service settings;
  - `profiles` — ordered check lists.
- `Checks\Ai\AiModerationCheck` — a single AI policy adapter. The policy itself
  is defined in `config/ai-moderator.php`.
- `Moderation\AuditContext` — neutral audit context mapped to the AI
  moderator DTO inside the AI check.

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
- AI: required `policy`, required `fields`, optional `language`. No `blocking`
  setting. The AI report drives the state and findings.

## Tests

```bash
composer test
composer format
composer validate --strict
```

Tests use PHPUnit and Orchestra Testbench. Feature tests cover profile order,
stop-on-reject, warnings, all-skipped, every built-in check, a custom check,
AI fake agents, Perspective HTTP fakes, and configuration/input errors. Unit
tests cover report aggregation, result invariants, and config merging.

The base test case registers the AI, journal, resilience, AI moderator, and
auto moderator providers and uses an in-memory SQLite database for the AI
dependency tables.

## Maintenance rules

- Do not add models, migrations, statuses, queues, translations, admin screens,
  or browser tests.
- Do not copy AI moderator internals. Use its public policy, report, and audit
  API only.
- Do not add a built-in stop word list.
- Do not read `config()`, `Auth`, the current request, or `App::getLocale()`
  inside checks.
- Do not treat `undecided` as approval.
- Keep public documentation and code comments in English.
- Update this file and `README.md` when the public API or configuration changes.

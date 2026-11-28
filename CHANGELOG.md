# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [1.5.0] - 2026-11-28

### Added

- Public background outcome DTOs `ModerationOutcome` and
  `ModerationFieldOutcome` with string status and state constants, field
  reasons, details, findings, and `toArray()`.
- Public API `AutoModerator::dispatch()`, `latest()`, `latestFor()`,
  `statuses()`, `registerHandler()`, and `forget()`.
- `ModerationResultHandler` contract called by the package after a completed
  run. `ModerationCompleted` remains internal and is no longer the extension
  point. Handlers can be registered by model class or mapped morph alias.
- `Concerns\HasModerationRuns` with status and result scopes.
- `field_labels` stored on runs so outcomes keep declared field labels,
  including fields without result rows.

### Changed

- Background models, events, jobs, run status enum, commands, reader, handler
  registry, dispatcher, and forgetter are marked `@internal` and removed from
  the public API documentation. `ModerationRunStatus` is no longer part of the
  application integration.
- Sync and background statuses use one state-based rule. A non-empty `error` is
  valid only with the `undecided` state; any other combination throws
  `InvalidCheckResultException`.

### Fixed

- Empty `reason` and `error` strings are treated as absent when selecting field
  reasons, technical errors, and per-check reasons.
- A check result with a non-empty error and a non-undecided state is rejected
  instead of being interpreted differently by sync and background APIs.
- The test suite includes a MySQL job next to the SQLite job.

### Removed

- Public documentation of Eloquent models, jobs, events, and internal status
  enums for the background layer.

## [1.4.0] - 2026-11-28

### Added

- Background moderation layer: `Moderatable` and `ModerationSource` contracts,
  `RunModerationJob`, the `auto-moderator:dispatch` command, fixed
  `auto_moderator_runs` and `auto_moderator_results` tables, and the
  `ModerationRun` and `ModerationResult` models.
- Run statuses `running`, `approved`, `rejected`, `needs_review`, `skipped`,
  and `failed`. `needs_review` replaces a separate AI attention status at the
  application level; the package has no manual-review flag.
- `ModerationCompleted` event published after a completed run with loaded
  results. Failed runs are stored but do not publish the event.
- `background.sources` and `background.queue` configuration, plus finite
  `background.unique_for_seconds` and `background.stale_after_minutes`.
  Package migrations are loaded automatically and are not published.
- `auto-moderator:cleanup` marks stale `running` runs as failed.
- `AuditContext::fromActorAndSubject()` and `AuditContext::toArray()` shared by
  synchronous and background runs.
- Tests for run creation, result mapping, statuses, empty fields, missing and
  non-moderatable models, morph maps, unique dispatch, source traversal, the
  completion event, and the absence of domain writes.

## [1.3.0] - 2026-11-27

### Added

- Moderation case API through `AutoModerator::moderationCase()`. A case runs
  several fields in order with its own key, label, profile, and input, sharing
  one actor, subject, language, and audit context. Empty fields are skipped
  without resolving their profile. Profiles for non-empty fields are resolved
  before any check runs, so an invalid later profile fails before earlier
  fields execute. Results are available per field through
  `ModerationCaseResult` and can be exported with `toArray()`.
- `ModerationField`, `ModerationFieldResult`, and `ModerationCaseResult`.
- Unit and facade tests for empty fields, field order, collected reasons,
  rejection priority, undecided results, all-skipped cases, shared run context,
  duplicate field keys, and the array representation.

## [1.2.0] - 2026-11-27

### Added

- Fallback profiles: when a profile is absent but `config/ai-moderator.php`
  defines a check with the same key, the package builds a one-check AI profile.
  The fallback check key is `auto-moderator.fallback.ai_check` (default `ai`).
  The fallback profile uses `check = <profile key>`, no `fields`, the default
  `stop_on_reject`, and returns its result under the fallback check key.
- Tests for fallback profiles, explicit-profile priority, unknown profiles,
  missing fallback checks, disabled AI checks, and composite profiles.

### Changed

- Profiles are needed only for composite scenarios, deterministic checks, and
  overrides. A single AI check runs by its AI check key. Explicit profiles
  always win; an invalid explicit profile is an error, not a fallback.

## [1.1.0] - 2026-11-27

### Added

- Direct AI check mode through `AiModerator::check()`. The AI check declares its
  fields only in `config/ai-moderator.php`; the adapter passes the whole input,
  writes the AI check key as the audit policy, and supports several AI checks in
  one profile through different registry keys.
- Automatic run context: the authenticated user and request URL by default;
  language precedence from `->language()`, the check `language` setting, then
  `app()->getLocale()`; explicit `->actor()` and `->subject()`; configurable
  automatic audit through `auto-moderator.audit`; explicit `->audit()` override.
- `Report::firstReason()` for the first rejected or undecided reason or error.
- Direct and policy AI tests, automatic context tests, and single-result mapper
  tests.

### Changed

- AI settings must contain exactly one of `check` or `policy`. `fields` is
  forbidden in direct mode and required in policy mode.
- The AI mapper now exposes `mapReport()` and `mapResult()`.
- README and AGENTS describe the new run defaults and no longer claim that the
  package never reads `Auth`, the request, or the application locale.

## [1.0.0] - 2026-11-26

### Added

- Synchronous moderation profiles with an ordered check list, enable switches,
  and configurable stop-on-reject behavior.
- `AutoModerator` facade, `PendingProfile` fluent API, `ModerationContext`,
  `CheckResult`, `Finding`, and `Report`.
- Built-in stop word, profanity, language, toxicity, and AI policy checks.
- Configurable `max_chars` truncation for Perspective requests, with warnings
  preserved when another field fails.
- Custom `ModerationCheck` support with early `ValidatesSettings` validation and
  container resolution.
- Registry defaults merged with profile overrides, including named external
  service settings.
- Neutral audit context mapped to the AI moderator package.
- Published configuration, README, AGENTS.md, CI workflow, and full test suite.

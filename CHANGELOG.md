# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

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

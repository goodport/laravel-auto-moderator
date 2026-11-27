# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

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

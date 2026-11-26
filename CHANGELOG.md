# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

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

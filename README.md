# Laravel Auto Moderator

Synchronous content moderation profiles for Laravel applications.

The package runs an ordered list of checks over named input fields, collects one
report, and returns a final state: `passed`, `rejected`, `undecided`, or
`skipped`. It stores nothing, knows nothing about your models, and never reads
the current request, the authenticated user, or the application locale.

## Requirements

- PHP 8.3 or newer;
- Laravel 12.62+ or 13.x;
- a configured AI provider in `config/ai.php` when the AI check is used;
- a database connection for the AI moderator audit table when the AI check is
  used (see below).

The package depends on `goodport/laravel-ai-moderator` for AI policies and on
`consoletvs/profanity` for profanity dictionaries. It has no migrations, models,
statuses, queues, translations, or admin screens of its own.

## Installation

```bash
composer require goodport/laravel-auto-moderator
```

Publish the configuration:

```bash
php artisan vendor:publish --tag=auto-moderator-config
```

The AI dependency writes its own audit and journal tables. Run the migrations
once:

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

Define a profile in `config/auto-moderator.php`:

```php
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
                    'language' => 'en',
                    'min_length' => 20,
                    'threshold' => 0.6,
                ],
            ],
            [
                'check' => 'toxicity',
                'settings' => [
                    'fields' => ['body'],
                    'language' => 'en',
                    'threshold' => 0.7,
                ],
            ],
            [
                'check' => 'ai',
                'settings' => [
                    'policy' => 'post',
                    'fields' => ['body'],
                ],
            ],
        ],
    ],
],
```

Run the profile:

```php
use Goodport\AutoModerator\Facades\AutoModerator;
use Goodport\AutoModerator\Moderation\AuditContext;

$report = AutoModerator::profile('post')
    ->input([
        'title' => $post->title,
        'body' => $post->body,
    ])
    ->language('en')
    ->audit(new AuditContext(
        actorType: 'user',
        actorId: (string) $user->id,
        subjectType: 'post',
        subjectId: (string) $post->id,
        requestUrl: request()->fullUrl(),
    ))
    ->run();

if ($report->rejected()) {
    // The application decides what to do with the content.
}

if ($report->undecided()) {
    // The result is not an approval. Retry, queue, or ask for a manual review.
}
```

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
$report->messages();     // reasons and errors for checks that did not pass
$report->violations();  // blocking findings grouped by check
$report->warnings();    // non-blocking findings grouped by check
$report->findings();    // all findings grouped by check
$report->toArray();     // full array representation
```

`CheckResult` exposes `check`, `state`, `reason`, `findings`, `details`, and
`error`. A `Finding` exposes `code`, `message`, `severity`, `blocking`, and
`details`.

## Profiles

A profile is an ordered list. The list order is the execution order.

```php
'profiles' => [
    'post' => [
        'enabled' => true,
        'stop_on_reject' => false,
        'checks' => [
            ['check' => 'stop_words', 'settings' => ['fields' => ['body'], 'words' => ['example']]],
            ['check' => 'ai', 'settings' => ['policy' => 'post', 'fields' => ['body']]],
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

The check counts letters that belong to the expected alphabet. `language` is
the default; `->language()` overrides it. An unknown language without a
configured alphabet is a configuration error. Text shorter than `min_length` or
without letters is skipped. Each field reports its `letters`, `matching`, and
`ratio` in `details.fields`.

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

The check calls the Perspective API through the Laravel HTTP client. Connection
settings live in `services.perspective`:

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

```php
[
    'check' => 'ai',
    'settings' => [
        'policy' => 'post',
        'fields' => ['body'],
    ],
],
```

Define the policy and its checks in `config/ai-moderator.php`:

```php
'checks' => [
    'post_body' => [
        'kind' => 'verdict',
        'input' => [
            'fields' => [
                'body' => ['type' => 'text', 'label' => 'Body', 'max_chars' => 4000],
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
'policies' => [
    'post' => ['checks' => ['post_body']],
],
```

The field names in the AI check settings must match the fields declared by the
AI policy checks. The adapter does not rebuild prompts or schemas; it forwards
the declared fields, the resolved language, and the audit context to the ready
policy and maps its report.

- A policy where every check is skipped becomes `skipped`, not `passed`.
- AI violations become findings. Reasons, labels, answers, and errors are kept
  in `details.checks`.
- AI configuration and input errors throw. Provider failures are normalized to
  `undecided`.
- The AI check does not accept a `blocking` setting; violation blocking belongs
  to the AI policy.

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
- An exception inside a check, a transport failure, or a malformed external
  response becomes `undecided`.
- `undecided` is never converted to `passed`.
- The package does not log. The application decides how to report the outcome.

## Limitations

- The package is synchronous. Queueing, retries, model loading, status changes,
  and notifications belong to the application.
- The package has no models, migrations, translations, or admin interface.
- The AI dependency writes its own audit and journal tables. Run its migrations.
- The package never reads the current request, `Auth`, or `App::getLocale()`.
  Pass input, language, and audit context explicitly.

## Development

```bash
composer test
composer format
composer validate --strict
```

## License

MIT. See [LICENSE](LICENSE).

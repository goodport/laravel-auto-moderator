<?php

use Goodport\AutoModerator\Checks\Ai\AiModerationCheck;
use Goodport\AutoModerator\Checks\Language\LanguageCheck;
use Goodport\AutoModerator\Checks\Profanity\ProfanityCheck;
use Goodport\AutoModerator\Checks\StopWords\StopWordsCheck;
use Goodport\AutoModerator\Checks\Toxicity\ToxicityCheck;

return [

    /*
    |--------------------------------------------------------------------------
    | Global switch
    |--------------------------------------------------------------------------
    |
    | When disabled, every profile returns skipped results without resolving
    | checks or calling external services. The profile structure is still
    | validated.
    |
    */

    'enabled' => true,

    /*
    |--------------------------------------------------------------------------
    | Defaults
    |--------------------------------------------------------------------------
    |
    | Shared profile defaults. A profile may override any value.
    |
    */

    'defaults' => [
        'stop_on_reject' => false,
    ],

    /*
    |--------------------------------------------------------------------------
    | Automatic audit
    |--------------------------------------------------------------------------
    |
    | When enabled, the package builds an audit context from the authenticated
    | user, the current request URL, and the optional actor and subject passed
    | to the profile. An explicit audit() call always overrides this context.
    |
    */

    'audit' => [
        'enabled' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Fallback profile
    |--------------------------------------------------------------------------
    |
    | A profile is required only for composite scenarios and overrides. When a
    | profile is absent but an AI check with the same key exists, the package
    | builds a one-check profile automatically. This key selects the registered
    | AI check used by that fallback profile.
    |
    */

    'fallback' => [
        'ai_check' => 'ai',
    ],

    /*
    |--------------------------------------------------------------------------
    | Background moderation
    |--------------------------------------------------------------------------
    |
    | Sources are class names implementing ModerationSource. The dispatch
    | command asks every source for pending models and queues a moderation job
    | for each one. A null queue uses the default application queue. The unique
    | lock expires after unique_for_seconds; cleanup marks runs older than
    | stale_after_minutes as failed.
    |
    */

    'background' => [
        'sources' => [],
        'queue' => null,
        'unique_for_seconds' => 3600,
        'stale_after_minutes' => 60,
    ],

    /*
    |--------------------------------------------------------------------------
    | Check registry
    |--------------------------------------------------------------------------
    |
    | Every check instance is registered by key. The key is used in profiles.
    | A registry entry contains a class name and optional default settings.
    | Register the same class under several keys to run it with different
    | settings.
    |
    */

    'checks' => [
        'stop_words' => [
            'class' => StopWordsCheck::class,
            'settings' => [
                'blocking' => true,
            ],
        ],

        'profanity' => [
            'class' => ProfanityCheck::class,
            'settings' => [
                'blocking' => true,
            ],
        ],

        'language' => [
            'class' => LanguageCheck::class,
            'settings' => [
                'blocking' => true,
                'min_length' => 20,
                'threshold' => 0.6,
                'alphabets' => [
                    'en' => 'abcdefghijklmnopqrstuvwxyz',
                    'ru' => 'абвгдеёжзийклмнопрстуфхцчшщъыьэюя',
                ],
            ],
        ],

        'toxicity' => [
            'class' => ToxicityCheck::class,
            'settings' => [
                'blocking' => true,
                'threshold' => 0.7,
                'max_chars' => 10000,
                'service' => 'perspective',
            ],
        ],

        'ai' => [
            'class' => AiModerationCheck::class,
            'settings' => [],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | External services
    |--------------------------------------------------------------------------
    |
    | Settings for external providers. A check refers to a service by name in
    | its `service` setting. The Perspective key is read from the environment
    | of the application. There is no fallback key.
    |
    */

    'services' => [
        'perspective' => [
            'url' => 'https://commentanalyzer.googleapis.com/v1alpha1/comments:analyze',
            'key' => env('PERSPECTIVE_API_KEY'),
            'timeout' => 10,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Profiles
    |--------------------------------------------------------------------------
    |
    | A profile is an ordered list of checks. The list order is the execution
    | order. Each entry contains a registered check key, an optional enabled
    | flag, and optional settings that override the registry defaults. A single
    | AI check does not need a profile; the fallback profile uses the AI check
    | key.
    |
    | 'post' => [
    |     'enabled' => true,
    |     'stop_on_reject' => false,
    |     'checks' => [
    |         ['check' => 'stop_words', 'settings' => ['fields' => ['body'], 'words' => ['example']]],
    |         ['check' => 'ai_text_name'],
    |     ],
    | ],
    |
    */

    'profiles' => [
        //
    ],

];

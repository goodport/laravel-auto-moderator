<?php

namespace Goodport\AutoModerator\Tests;

use Goodport\AiJournal\AiJournalServiceProvider;
use Goodport\AiModerator\AiModeratorServiceProvider;
use Goodport\AiResilience\AiResilienceServiceProvider;
use Goodport\AutoModerator\AutoModeratorServiceProvider;
use Goodport\AutoModerator\Tests\Fixtures\RecordingCheck;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Ai\AiServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        RecordingCheck::reset();
    }

    /**
     * @return array<int, class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [
            AiServiceProvider::class,
            AiJournalServiceProvider::class,
            AiResilienceServiceProvider::class,
            AiModeratorServiceProvider::class,
            AutoModeratorServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $connection = getenv('DB_CONNECTION') ?: 'testing';

        if ($connection === 'testing') {
            $app['config']->set('database.default', 'testing');
            $app['config']->set('database.connections.testing', [
                'driver' => 'sqlite',
                'database' => ':memory:',
                'prefix' => '',
                'foreign_key_constraints' => true,
            ]);
        } else {
            $app['config']->set('database.default', $connection);
            $app['config']->set("database.connections.{$connection}", [
                'driver' => $connection,
                'host' => getenv('DB_HOST') ?: '127.0.0.1',
                'port' => (int) (getenv('DB_PORT') ?: 3306),
                'database' => getenv('DB_DATABASE') ?: 'test',
                'username' => getenv('DB_USERNAME') ?: 'root',
                'password' => getenv('DB_PASSWORD') ?: '',
                'charset' => 'utf8mb4',
                'collation' => 'utf8mb4_unicode_ci',
                'prefix' => '',
                'foreign_key_constraints' => true,
            ]);
        }

        $app['config']->set('ai-moderator.defaults.provider', 'openai');
        $app['config']->set('ai-moderator.defaults.model', 'gpt-test');
        $app['config']->set('ai-moderator.defaults.strict', false);
        $app['config']->set('ai-journal.enabled', false);
        $app['config']->set('ai-moderator.journal.replay.enabled', false);
        $app['config']->set('ai-resilience.limits.enabled', false);
        $app['config']->set('ai-resilience.retries.enabled', false);
    }

    /**
     * @param  array<string, mixed>  $profile
     */
    protected function setProfile(string $key, array $profile): void
    {
        $this->app['config']->set("auto-moderator.profiles.{$key}", $profile);
    }

    /**
     * @param  array<string, mixed>  $settings
     */
    protected function setCheck(string $key, string $class, array $settings = []): void
    {
        $this->app['config']->set("auto-moderator.checks.{$key}", [
            'class' => $class,
            'settings' => $settings,
        ]);
    }

    protected function setPerspectiveService(
        string $key = 'test-key',
        string $url = 'https://example.com/v1alpha1/comments:analyze',
        int $timeout = 10,
    ): void {
        $this->app['config']->set('auto-moderator.services.perspective', [
            'url' => $url,
            'key' => $key,
            'timeout' => $timeout,
        ]);
    }

    /**
     * @param  array<string, mixed>  $check
     */
    protected function setAiCheck(string $key, array $check): void
    {
        $this->app['config']->set("ai-moderator.checks.{$key}", $check);
    }

    /**
     * @param  array<string, mixed>  $policy
     */
    protected function setAiPolicy(string $key, array $policy): void
    {
        $this->app['config']->set("ai-moderator.policies.{$key}", $policy);
    }
}

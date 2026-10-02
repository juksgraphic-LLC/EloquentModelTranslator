<?php

declare(strict_types=1);

namespace Juksgraphic\EloquentModelTranslator\Laravel;

use Illuminate\Support\ServiceProvider;
use Juksgraphic\EloquentModelTranslator\Contracts\TranslationPrompt;
use Juksgraphic\EloquentModelTranslator\Contracts\TranslationProvider;
use Juksgraphic\EloquentModelTranslator\Prompts\DefaultTranslationPrompt;
use Juksgraphic\EloquentModelTranslator\Providers\ArrayTranslationProvider;
use Juksgraphic\EloquentModelTranslator\Providers\CerebrasTranslationProvider;
use Juksgraphic\EloquentModelTranslator\Services\TranslationService;
use Juksgraphic\EloquentModelTranslator\Support\TranslatorConfig;
use Juksgraphic\EloquentModelTranslator\Translator;

/**
 * Optional Laravel adapter. The package works without it (see Translator::configure()).
 */
class EloquentModelTranslatorServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../../config/eloquent-model-translator.php', 'eloquent-model-translator');

        $this->app->singleton(TranslatorConfig::class, function ($app): TranslatorConfig {
            $c = $app['config']->get('eloquent-model-translator');

            return new TranslatorConfig(
                $c['locales'],
                $c['source_locale'],
                $c['fallback_locale'],
                $c['table'],
            );
        });

        $this->app->bind(TranslationPrompt::class, DefaultTranslationPrompt::class);

        $this->app->bind(TranslationProvider::class, function ($app): TranslationProvider {
            $config = $app['config']->get('eloquent-model-translator');

            if ($config['provider'] === 'cerebras') {
                // HTTP client and factories are detected automatically (Guzzle, Symfony HttpClient...).
                return new CerebrasTranslationProvider(
                    $config['cerebras']['api_key'],
                    $config['cerebras']['model'],
                    prompt: $app->make(TranslationPrompt::class),
                );
            }

            return new ArrayTranslationProvider();
        });

        $this->app->bind(TranslationService::class, fn ($app) => new TranslationService(
            $app->make(TranslationProvider::class),
            $app->make(TranslatorConfig::class),
        ));
    }

    public function boot(): void
    {
        Translator::configure($this->app->make(TranslatorConfig::class));
        Translator::resolveLocaleUsing(fn (): string => $this->app->getLocale());

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../../config/eloquent-model-translator.php' => config_path('eloquent-model-translator.php'),
            ], 'translator-config');

            $this->publishes([
                __DIR__ . '/../../database/migrations/create_translations_table.php.stub'
                    => database_path('migrations/' . date('Y_m_d_His') . '_create_translations_table.php'),
            ], 'translator-migrations');
        }
    }
}
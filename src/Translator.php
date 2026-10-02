<?php

declare(strict_types=1);

namespace Juksgraphic\EloquentModelTranslator;

use Juksgraphic\EloquentModelTranslator\Contracts\LocaleResolver;
use Juksgraphic\EloquentModelTranslator\Resolvers\CallableLocaleResolver;
use Juksgraphic\EloquentModelTranslator\Support\TranslatorConfig;

/**
 * Framework-agnostic registry holding the package configuration and the locale resolver.
 *
 * Laravel: wired by the service provider.
 * Flight / plain PHP: call Translator::configure() and Translator::resolveLocaleUsing() once at bootstrap.
 */
final class Translator
{
    /**
     * @var TranslatorConfig|null
     */
    private static ?TranslatorConfig $config = null;

    /**
     * @var LocaleResolver|null
     */
    private static ?LocaleResolver $resolver = null;

    /**
     * Static registry: not instantiable.
     */
    private function __construct() {}

    /**
     * Sets the package configuration.
     *
     * @param TranslatorConfig $config
     * @return void
     */
    public static function configure(TranslatorConfig $config): void
    {
        self::$config = $config;
    }

    /**
     * Returns the configuration (default values if none was set).
     *
     * @return TranslatorConfig
     */
    public static function config(): TranslatorConfig
    {
        return self::$config ??= new TranslatorConfig();
    }

    /**
     * Sets the resolver giving the active locale.
     *
     * @param LocaleResolver|callable(): (string|null) $resolver A resolver or a callable returning the locale.
     * @return void
     */
    public static function resolveLocaleUsing(LocaleResolver|callable $resolver): void
    {
        self::$resolver = $resolver instanceof LocaleResolver
            ? $resolver
            : new CallableLocaleResolver($resolver);
    }

    /**
     * Returns the active locale: resolver result, or the source locale when unknown.
     * The result is NOT checked against the allowed locales.
     *
     * @return string
     */
    public static function currentLocale(): string
    {
        return self::$resolver?->resolve() ?? self::config()->sourceLocale;
    }

    /**
     * Clears configuration and resolver (useful between tests).
     *
     * @return void
     */
    public static function reset(): void
    {
        self::$config   = null;
        self::$resolver = null;
    }
}

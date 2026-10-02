# Eloquent Model Translator

[![Latest Version on Packagist](https://img.shields.io/packagist/v/juksgraphic/eloquentmodeltranslator.svg?style=flat-square)](https://packagist.org/packages/juksgraphic/eloquentmodeltranslator)
[![Tests](https://github.com/juksgraphic/eloquentmodeltranslator/actions/workflows/run-tests.yml/badge.svg)](https://github.com/juksgraphic/eloquentmodeltranslator/actions/workflows/run-tests.yml)
[![Total Downloads](https://img.shields.io/packagist/dt/juksgraphic/eloquentmodeltranslator.svg?style=flat-square)](https://packagist.org/packages/juksgraphic/eloquentmodeltranslator)

Per-attribute translations for Eloquent models, **without depending on Laravel** (only `illuminate/database` is required).

- Source-locale values stay in the **model columns**.
- Other locales live in a **`translations` table** (one row per model and locale, JSON content).
- Reads follow a **fallback chain** (requested locale → fallback locale → source).
- Optional **machine translation** through a provider (Cerebras, or any OpenAI-compatible API) with a swappable prompt.
- Writes happen on `save()` / `delete()`: no event dispatcher is required.
- The package **never translates on its own**: your application decides when to call it (see [Triggering translation](#triggering-translation)).

```php
class Article extends Model implements Translatable
{
    use HasTranslations;

    protected array $translatable = ['title', 'body'];
}

$article = Article::create(['title' => 'Bonjour']);   // source locale (fr)
$article->setTranslation('title', 'en', 'Hello')->save();

app()->setLocale('en');
echo $article->title;                                  // "Hello"
```

## Installation

```bash
composer require juksgraphic/eloquentmodeltranslator
```

Requirements: PHP ^8.4, `illuminate/database` ^13.34, PSR interfaces (`psr/http-client`, `psr/http-factory`, `psr/http-message`).

For machine translation, install any PSR-18 client and PSR-17 factories. If you do not pass them to a provider, the package detects installed ones (Guzzle, Symfony HttpClient, Nyholm, Laminas) and throws an `InvalidConfigurationException` when none is found. For example:

```bash
composer require guzzlehttp/guzzle guzzlehttp/psr7
```

## Database

One `translations` table (the name is configurable in `TranslatorConfig`):

```php
Schema::create('translations', function (Blueprint $table) {
    $table->id();
    $table->morphs('translatable');       // translatable_type + translatable_id
    $table->string('locale', 12);
    $table->json('content')->nullable();  // ['title' => '...', 'body' => '...']
    $table->timestamps();

    $table->unique(['translatable_type', 'translatable_id', 'locale']);
});
```

With Laravel, publish the bundled migration (`php artisan vendor:publish --tag=translator-migrations`).

## Configuration

### Plain PHP (Flight, Slim, scripts...)

Do this **once** at bootstrap:

```php
use Juksgraphic\EloquentModelTranslator\Support\TranslatorConfig;
use Juksgraphic\EloquentModelTranslator\Translator;

Translator::configure(new TranslatorConfig(
    locales: ['fr', 'en', 'es', 'ht'],   // every locale, source included
    sourceLocale: 'fr',                  // locale of the model columns
    fallbackLocale: null,                // null = source locale
    table: 'translations',
));

// How to know the active locale (a callable or a LocaleResolver)
Translator::resolveLocaleUsing(fn () => Flight::getLocale());
```

### Laravel

The service provider is **optional**: it only wires the code above. If it is auto-discovered (`extra.laravel.providers` in `composer.json`), publish the config:

```bash
php artisan vendor:publish --tag=translator-config
```

then edit `config/eloquent-model-translator.php` (locales, source locale, provider `array` or `cerebras`) and your `.env`:

```
TRANSLATOR_PROVIDER=cerebras
CEREBRAS_API_KEY=...
CEREBRAS_MODEL=...
```

The active locale then follows `app()->getLocale()`.

## Making a model translatable

```php
use Illuminate\Database\Eloquent\Model;
use Juksgraphic\EloquentModelTranslator\Concerns\HasTranslations;
use Juksgraphic\EloquentModelTranslator\Contracts\Translatable;

class Article extends Model implements Translatable
{
    use HasTranslations;

    protected array $translatable = ['title', 'body'];
}
```

A translatable model needs **all three** of the following:

1. `implements Translatable`: the contract (a type). `TranslationService` type-hints it, so without it the service throws a `TypeError`.
2. `use HasTranslations`: the implementation of that contract.
3. A `$translatable` array listing the attributes to translate.

PHP traits cannot implement interfaces, which is why the model itself declares `implements Translatable`.

## Usage

```php
$article = Article::create(['title' => 'Bonjour', 'body' => 'Le monde']); // source locale (fr)

// Manual translation (written on save())
$article->setTranslation('title', 'en', 'Hello')->save();
$article->setTranslations('es', ['title' => 'Hola', 'body' => 'El mundo'])->save();

// Reading
app()->setLocale('en');
$article->title;                          // "Hello" (follows the active locale)
$article->setLocale('es')->title;         // "Hola"  (locale forced on this instance)
$article->setLocale(null);                // back to the resolved locale
$article->getTranslation('title', 'ht');  // falls back to the source when missing
$article->getTranslation('title', 'ht', useFallback: false); // null when missing
$article->getTranslations('title');       // ['fr' => ..., 'en' => ..., 'es' => ..., 'ht' => null]
$article->hasTranslation('title', 'en');  // true / false

// Serialization: translatable attributes are rendered in the active locale
$article->toArray();
$article->toJson();

// Avoid N+1 on lists
Article::withTranslations()->get();
Article::withTranslations('en')->get();
```

Writing to the source locale with `setTranslation('title', 'fr', '...')` changes the model column itself.

## Triggering translation

**The package never translates by itself.** Creating or updating a model does not call any provider. This is deliberate: applications differ (Laravel queues, Symfony Messenger, cron, synchronous calls), and an HTTP call inside `save()` would be slow, costly and able to fail mid-save.

| Model action | What the package does |
|---|---|
| Create | Stores the source locale only. |
| Update | Nothing. Existing translations stay as they are, even if the source text changed. |
| Delete | Deletes the translations too (kept on soft delete, removed on `forceDelete()`). |
| `setTranslation()` + `save()` | Writes exactly what you provided. |
| `TranslationService::translate()` | The only entry point that calls a provider. |

Your application calls `TranslationService` from wherever suits it: a job, an observer, a console command, a controller.

### Recipe: Laravel observer + queued job

This lives in your application, not in the package. Adapt it to your own conventions.

```php
// app/Observers/ArticleObserver.php
class ArticleObserver
{
    public function saved(Article $article): void
    {
        // Translatable attributes that actually changed (all of them on creation)
        $changed = array_values(array_intersect(
            array_keys($article->getChanges()),
            $article->getTranslatableAttributes()
        ));

        if ($changed !== []) {
            TranslateArticleJob::dispatch($article->id, $changed);
        }
    }
}
```

```php
// app/Jobs/TranslateArticleJob.php
class TranslateArticleJob implements ShouldQueue
{
    use Dispatchable, Queueable;

    public function __construct(public int $id, public array $attributes) {}

    public function handle(TranslationService $service): void
    {
        if ($article = Article::find($this->id)) {
            // overwrite: true because the source text changed and old translations are stale
            $service->translate($article, $this->attributes, overwrite: true);
        }
    }
}
```

Things to decide in your application:

- **`overwrite`**: `true` also replaces manual corrections on those attributes; `false` keeps them but leaves stale translations after a source edit.
- **Queue vs. synchronous**: prefer a job. If the provider fails, the model is already saved and the job can be retried.

## Machine translation

```php
use Juksgraphic\EloquentModelTranslator\Services\TranslationService;

$service = new TranslationService($provider);

$service->translate($article);                       // every target locale, keeps existing translations
$service->translate($article, ['title']);            // only some attributes
$service->translate($article, overwrite: true);      // replace existing translations

$service->saveTranslations($article, [               // grouped manual save
    'en' => ['title' => 'Hello'],
]);
```

Guarantee: **nothing is written if a single locale fails** (all or nothing).

With the Laravel service provider, `TranslationService` can be injected from the container.

### Creating a provider

The HTTP client and the PSR-17 factories are **optional**. When omitted, the package uses the ones already installed in your project (detection order: Guzzle, Symfony HttpClient, Nyholm, Laminas), with a 30 s timeout on the default client. If nothing is found, an `InvalidConfigurationException` tells you what to install.

```php
use Juksgraphic\EloquentModelTranslator\Providers\CerebrasTranslationProvider;

$provider = new CerebrasTranslationProvider(
    apiKey: getenv('CEREBRAS_API_KEY'),
    model: 'MODEL_ID',   // see the Cerebras documentation
);
```

Any other OpenAI-compatible API (OpenAI, Groq, Mistral, Ollama...):

```php
use Juksgraphic\EloquentModelTranslator\Providers\OpenAiCompatibleTranslationProvider;

$provider = new OpenAiCompatibleTranslationProvider(
    apiKey: 'API_KEY',
    model: 'MODEL',
    baseUri: 'https://api.groq.com/openai/v1/',
);
```

To control the HTTP layer yourself (custom timeout, proxy, middleware, mocked client), pass any PSR-18 client and PSR-17 factories:

```php
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\HttpFactory;

$factory  = new HttpFactory();   // implements PSR-17 (requests and streams)
$provider = new CerebrasTranslationProvider(
    apiKey: getenv('CEREBRAS_API_KEY'),
    model: 'MODEL_ID',
    client: new Client(['timeout' => 60]), // any PSR-18 client
    requestFactory: $factory,
    streamFactory: $factory,
);
```

If the client you pass also implements PSR-17 (for example Symfony's `Psr18Client`), the factories can be omitted: the client is reused for them.

Other options: `baseUri`, `prompt`, `maxRetries` (default 2), `retryDelayMs` (default 500, doubled on each retry) and `temperature` (default 0.2).

Offline (tests, development): `new ArrayTranslationProvider()` prefixes every value with the target locale, for example `[en] Bonjour`.

### Customizing the prompt

```php
use Juksgraphic\EloquentModelTranslator\Prompts\DefaultTranslationPrompt;

$prompt = new DefaultTranslationPrompt(
    extraInstructions: 'Never translate the word "Juksgraphic". Use a formal tone.',
);

$provider = new CerebrasTranslationProvider(..., prompt: $prompt);
```

For a completely different prompt, implement `Contracts\TranslationPrompt` (`system()`, `user()`, `parse()`).

### Writing your own provider

Implement `Contracts\TranslationProvider`:

```php
public function translate(array $fields, string $sourceLocale, string $targetLocale): array;
```

It must return **exactly the same keys** with non-empty string values, and must **throw** `TranslationFailedException` rather than return untranslated content.

## Errors

Every exception extends `TranslationException`:

| Exception | When |
|---|---|
| `InvalidConfigurationException` | Empty locales, source or fallback outside the list |
| `UnsupportedLocaleException` | Locale not allowed |
| `NotTranslatableException` | Attribute not listed in `$translatable` |
| `ModelNotSavedException` | Writing translations before the first `save()` |
| `TranslationFailedException` | Provider failure (network, HTTP, invalid response, missing keys) |

## Known limitations

- Queries (`where('title', ...)`) only hit the **source locale**.
- Mass updates (`Article::where(...)->update(...)`) bypass translations.
- Without `withTranslations()`, reading a translation loads the relation, one query per model.
- `translatable_type` stores the full class name: use `Relation::morphMap()` if you rename classes.
- Translation is never triggered automatically on `save()`: call `TranslationService` yourself (see above).

## Testing

```bash
composer test
```

Tests use in-memory SQLite (the `pdo_sqlite` extension is required) and a fake PSR-18 client: no network calls. To try the package by hand: `php -S localhost:8000 index.php`.

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Please see [CONTRIBUTING](https://github.com/spatie/.github/blob/main/CONTRIBUTING.md) for details.

## Security Vulnerabilities

Please review [our security policy](../../security/policy) on how to report security vulnerabilities.

## Credits

- [Kingsley Guillaume](https://github.com/juksgraphic-LLC)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
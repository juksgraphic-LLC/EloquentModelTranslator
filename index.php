<?php

declare(strict_types=1);

/**
 * Playground (HTML) : php -S localhost:8000 index.php   puis ouvrir http://localhost:8000
 *
 * Traduction réelle avec Cerebras (optionnel) :
 *   CEREBRAS_API_KEY=xxx CEREBRAS_MODEL=<model-id> php -S localhost:8000 index.php
 *   (nécessite : composer require --dev guzzlehttp/guzzle guzzlehttp/psr7)
 */

require __DIR__ . '/vendor/autoload.php';

use GuzzleHttp\Client;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Juksgraphic\EloquentModelTranslator\Concerns\HasTranslations;
use Juksgraphic\EloquentModelTranslator\Contracts\Translatable;
use Juksgraphic\EloquentModelTranslator\Providers\ArrayTranslationProvider;
use Juksgraphic\EloquentModelTranslator\Providers\CerebrasTranslationProvider;
use Juksgraphic\EloquentModelTranslator\Services\TranslationService;
use Juksgraphic\EloquentModelTranslator\Support\TranslatorConfig;
use Juksgraphic\EloquentModelTranslator\Translator;

// ------------------------------------------------------------------ Database (SQLite in memory)

$capsule = new Capsule();
$capsule->addConnection(['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']);
$capsule->setAsGlobal();
$capsule->bootEloquent();

$capsule->schema()->create('translations', function (Blueprint $table) {
    $table->id();
    $table->morphs('translatable');
    $table->string('locale', 12);
    $table->json('content')->nullable();
    $table->timestamps();
    $table->unique(['translatable_type', 'translatable_id', 'locale']);
});

$capsule->schema()->create('articles', function (Blueprint $table) {
    $table->id();
    $table->string('title')->nullable();
    $table->text('body')->nullable();
    $table->timestamps();
});

// ------------------------------------------------------------------ Model + configuration

class Article extends Model implements Translatable
{
    use HasTranslations;

    protected $guarded = [];

    /** @var list<string> */
    protected array $translatable = ['title', 'body'];
}

$ctx         = new stdClass();
$ctx->locale = 'fr'; // simulates app()->getLocale()

Translator::configure(new TranslatorConfig(['fr', 'en', 'es', 'ht'], sourceLocale: 'fr'));
Translator::resolveLocaleUsing(fn () => $ctx->locale);

// ------------------------------------------------------------------ Tiny view helpers

final class Raw
{
    public function __construct(public string $html) {}
}

function e(mixed $v): string
{
    return htmlspecialchars((string) $v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function badge(string $text, string $kind = 'locale'): Raw
{
    return new Raw('<span class="badge ' . e($kind) . '">' . e($text) . '</span>');
}

function nullValue(): Raw
{
    return new Raw('<span class="muted">null</span>');
}

/** @param list<string> $headers @param list<list<mixed>> $rows */
function table(array $headers, array $rows): string
{
    $html = '<div class="scroll"><table><thead><tr>';
    foreach ($headers as $h) {
        $html .= '<th>' . e($h) . '</th>';
    }
    $html .= '</tr></thead><tbody>';
    foreach ($rows as $row) {
        $html .= '<tr>';
        foreach ($row as $cell) {
            $html .= '<td>' . ($cell instanceof Raw ? $cell->html : e($cell)) . '</td>';
        }
        $html .= '</tr>';
    }

    return $html . '</tbody></table></div>';
}

function pre(string $text): string
{
    return '<pre>' . e($text) . '</pre>';
}

function note(string $text): string
{
    return '<p class="note">' . e($text) . '</p>';
}

/** @var list<array{title:string,hint:string,html:string,ok:bool}> $sections */
$sections = [];

function step(array &$sections, string $title, string $hint, callable $fn): void
{
    try {
        $html = $fn();
        $ok   = true;
    } catch (Throwable $e) {
        $ok   = false;
        $html = '<p class="err"><strong>' . e($e::class) . '</strong><br>' . e($e->getMessage()) . '</p>';
    }

    $sections[] = compact('title', 'hint', 'html', 'ok');
}

// ------------------------------------------------------------------ 1. Manual translation

step($sections, '1. Traduction manuelle', 'setTranslation() puis save() ; lecture avec fallback vers la source.', function () use ($ctx) {
    $ctx->article = Article::create(['title' => 'Bonjour le monde', 'body' => 'Ceci est mon premier article.']);
    $ctx->article->setTranslation('title', 'en', 'Hello world')->save();

    $rows = [];
    foreach (['fr', 'en', 'es'] as $locale) {
        $has    = $ctx->article->hasTranslation('title', $locale);
        $rows[] = [
            badge($locale),
            $ctx->article->setLocale($locale)->title,
            $locale === 'fr' ? 'colonne source' : ($has ? 'traduction en base' : 'absente → fallback vers la source'),
        ];
    }
    $ctx->article->setLocale(null);

    return table(['Locale', 'title', 'Origine'], $rows);
});

// ------------------------------------------------------------------ 2. App locale

step($sections, '2. Locale de l\'application', 'Sans setLocale(), le modèle suit le LocaleResolver (ici une variable simulant app()->getLocale()).', function () use ($ctx) {
    $rows = [];
    foreach (['fr', 'en', 'ht'] as $locale) {
        $ctx->locale = $locale;
        $rows[]      = [badge($locale), $ctx->article->title];
    }
    $ctx->locale = 'fr';

    return table(['Locale active', 'title'], $rows);
});

// ------------------------------------------------------------------ 3. Automatic translation

step($sections, '3. Traduction automatique', 'TranslationService + ArrayTranslationProvider (hors ligne). La traduction manuelle « en » est conservée.', function () use ($ctx) {
    $provider = new ArrayTranslationProvider();
    (new TranslationService($provider))->translate($ctx->article);

    $rows = [];
    foreach (['title', 'body'] as $field) {
        foreach ($ctx->article->getTranslations($field) as $locale => $value) {
            $origin = $value === null ? nullValue()
                : badge($locale === 'fr' ? 'source' : (str_starts_with((string) $value, '[') ? 'provider' : 'manuel'), 'origin');
            $rows[] = [$field, badge($locale), $value ?? nullValue(), $origin];
        }
    }

    return table(['Attribut', 'Locale', 'Valeur', 'Origine'], $rows)
        . note('Appels au provider : ' . count($provider->calls()));
});

// ------------------------------------------------------------------ 4. JSON

step($sections, '4. toArray() / toJson()', 'Les attributs traduisibles sont rendus dans la locale active (ici « es »).', function () use ($ctx) {
    $ctx->locale = 'es';
    $json        = Article::find($ctx->article->id)->toJson(JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    $ctx->locale = 'fr';

    return pre((string) $json);
});

// ------------------------------------------------------------------ 5. Eager loading

step($sections, '5. Eager loading (sans N+1)', 'Article::withTranslations() charge les traductions en une seule requête supplémentaire.', function () use ($ctx) {
    foreach (range(2, 4) as $i) {
        Article::create(['title' => "Titre {$i}"])->setTranslation('title', 'en', "Title {$i}")->save();
    }

    $ctx->locale = 'en';
    $connection  = Capsule::connection();
    $connection->enableQueryLog();
    $connection->flushQueryLog();

    $rows = [];
    foreach (Article::withTranslations()->get() as $item) {
        $rows[] = [$item->id, $item->title];
    }

    $log         = $connection->getQueryLog();
    $ctx->locale = 'fr';

    return table(['id', 'title (en)'], $rows)
        . note('Requêtes exécutées : ' . count($log))
        . pre(implode("\n", array_column($log, 'query')));
});

// ------------------------------------------------------------------ 6. Real provider

step($sections, '6. Provider réel (Cerebras)', 'Optionnel : définir CEREBRAS_API_KEY (et CEREBRAS_MODEL).', function () {
    $apiKey =getenv("CEREBRAS_API_KEY");

    if (!$apiKey || !class_exists(Client::class)) {
        return note('Ignoré : CEREBRAS_API_KEY absente ou guzzlehttp/guzzle non installé.');
    }

    $real    = new CerebrasTranslationProvider(
        $apiKey,
        getenv("CEREBRAS_MODEL")
    );

    $fresh = Article::create(['title' => 'Bienvenue sur notre site', 'body' => 'Découvrez nos services.']);
    (new TranslationService($real))->translate($fresh);

    $rows = [];
    foreach ($fresh->getTranslations('title') as $locale => $value) {
        $rows[] = [badge($locale), $value ?? nullValue()];
    }

    return table(['Locale', 'title'], $rows);
});

// ------------------------------------------------------------------ 7. Errors

step($sections, '7. Erreurs attendues', 'Les exceptions du package héritent toutes de TranslationException.', function () use ($ctx) {
    $rows  = [];
    $tries = [
        'setLocale(\'de\')'        => fn () => $ctx->article->setLocale('de'),
        'getTranslation(\'id\')'   => fn () => $ctx->article->getTranslation('id'),
        'flush sur modèle non sauvé' => function () {
            $a = new Article(['title' => 'x']);
            $a->setTranslation('title', 'en', 'y');
            $a->flushTranslations();
        },
    ];

    foreach ($tries as $label => $try) {
        try {
            $try();
            $rows[] = [$label, 'aucune exception', ''];
        } catch (Throwable $e) {
            $rows[] = [$label, badge((new ReflectionClass($e))->getShortName(), 'err'), $e->getMessage()];
        }
    }

    return table(['Appel', 'Exception', 'Message'], $rows);
});

// ------------------------------------------------------------------ 8. Raw DB dump

step($sections, '8. Contenu brut de la table translations', 'Ce qui est réellement stocké en base.', function () {
    $rows = [];
    foreach (Capsule::table('translations')->orderBy('id')->get() as $r) {
        $rows[] = [$r->id, class_basename($r->translatable_type) . ' #' . $r->translatable_id, badge($r->locale), $r->content];
    }

    return table(['id', 'Modèle', 'Locale', 'content (JSON)'], $rows);
});

$failed = count(array_filter($sections, fn ($s) => !$s['ok']));

if (PHP_SAPI !== 'cli') {
    header('Content-Type: text/html; charset=utf-8');
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Eloquent Model Translator — Playground</title>
<style>
  :root {
    --bg: #f6f7f9; --card: #ffffff; --text: #1c2330; --muted: #6b7686; --border: #e3e7ee;
    --accent: #4f46e5; --accent-soft: #eef0ff; --ok: #15803d; --ok-soft: #e6f6ec;
    --err: #b91c1c; --err-soft: #fdecec; --code: #f1f3f8;
  }
  @media (prefers-color-scheme: dark) {
    :root {
      --bg: #0f131a; --card: #171c26; --text: #e6e9ef; --muted: #8f9aad; --border: #2a3140;
      --accent: #8b87ff; --accent-soft: #232650; --ok: #4ade80; --ok-soft: #14301f;
      --err: #f87171; --err-soft: #3a1b1b; --code: #0f131a;
    }
  }
  * { box-sizing: border-box; }
  body {
    margin: 0; background: var(--bg); color: var(--text);
    font: 15px/1.55 system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
  }
  .wrap { max-width: 880px; margin: 0 auto; padding: 32px 16px 64px; }
  header h1 { margin: 0 0 4px; font-size: 26px; }
  header p { margin: 0; color: var(--muted); }
  .summary { display: flex; gap: 8px; flex-wrap: wrap; margin: 16px 0 24px; }
  .pill { padding: 4px 12px; border-radius: 999px; font-size: 13px; background: var(--accent-soft); color: var(--accent); }
  .pill.ok { background: var(--ok-soft); color: var(--ok); }
  .pill.err { background: var(--err-soft); color: var(--err); }
  .card {
    background: var(--card); border: 1px solid var(--border); border-radius: 12px;
    padding: 18px 20px; margin-bottom: 16px;
  }
  .card h2 { margin: 0; font-size: 17px; display: flex; align-items: center; gap: 8px; }
  .card h2::before { content: ""; width: 9px; height: 9px; border-radius: 50%; background: var(--ok); flex: none; }
  .card.failed h2::before { background: var(--err); }
  .hint { margin: 2px 0 14px; color: var(--muted); font-size: 13.5px; }
  .scroll { overflow-x: auto; }
  table { width: 100%; border-collapse: collapse; font-size: 14px; }
  th, td { text-align: left; padding: 8px 10px; border-bottom: 1px solid var(--border); vertical-align: top; }
  th { color: var(--muted); font-weight: 600; font-size: 12px; text-transform: uppercase; letter-spacing: .04em; }
  tr:last-child td { border-bottom: 0; }
  td { word-break: break-word; }
  pre {
    margin: 10px 0 0; padding: 12px 14px; background: var(--code); border: 1px solid var(--border);
    border-radius: 8px; overflow-x: auto; font: 12.5px/1.5 ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
  }
  .badge {
    display: inline-block; padding: 1px 9px; border-radius: 6px; font-size: 12px; font-weight: 600;
    background: var(--accent-soft); color: var(--accent);
    font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
  }
  .badge.origin { background: var(--code); color: var(--muted); font-family: inherit; font-weight: 500; }
  .badge.err { background: var(--err-soft); color: var(--err); }
  .muted { color: var(--muted); font-style: italic; }
  .note { margin: 10px 0 0; color: var(--muted); font-size: 13.5px; }
  .err { margin: 0; padding: 10px 12px; border-radius: 8px; background: var(--err-soft); color: var(--err); }
  footer { margin-top: 24px; text-align: center; color: var(--muted); font-size: 12.5px; }
</style>
</head>
<body>
<div class="wrap">
  <header>
    <h1>Eloquent Model Translator</h1>
    <p>Playground — SQLite en mémoire, aucune donnée persistante.</p>
  </header>

  <div class="summary">
    <span class="pill"><?= count($sections) ?> étapes</span>
    <span class="pill ok"><?= count($sections) - $failed ?> réussies</span>
    <?php if ($failed > 0): ?><span class="pill err"><?= $failed ?> en erreur</span><?php endif; ?>
    <span class="pill">Locales : <?= e(implode(', ', Translator::config()->locales)) ?></span>
    <span class="pill">Source : <?= e(Translator::config()->sourceLocale) ?></span>
  </div>

  <?php foreach ($sections as $s): ?>
    <section class="card<?= $s['ok'] ? '' : ' failed' ?>">
      <h2><?= e($s['title']) ?></h2>
      <p class="hint"><?= e($s['hint']) ?></p>
      <?= $s['html'] ?>
    </section>
  <?php endforeach; ?>

  <footer>PHP <?= e(PHP_VERSION) ?> · juksgraphic/eloquent-model-translator</footer>
</div>
</body>
</html>
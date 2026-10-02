<?php

declare(strict_types=1);

namespace Juksgraphic\EloquentModelTranslator\Tests;

use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Schema\Blueprint;
use Juksgraphic\EloquentModelTranslator\Translator;
use PHPUnit\Framework\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected string $currentLocale = 'fr';

    protected function setUp(): void
    {
        parent::setUp();

        $capsule = new Capsule();
        $capsule->addConnection(['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']);
        $capsule->setAsGlobal();
        $capsule->bootEloquent();

        $schema = $capsule->schema();

        $schema->create('translations', function (Blueprint $table) {
            $table->id();
            $table->morphs('translatable');
            $table->string('locale', 12);
            $table->json('content')->nullable();
            $table->timestamps();
            $table->unique(['translatable_type', 'translatable_id', 'locale']);
        });

        $schema->create('articles', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->nullable();
            $table->string('title')->nullable();
            $table->text('body')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Translator::reset();
        $this->currentLocale = 'fr';
        Translator::resolveLocaleUsing(fn (): string => $this->currentLocale);
    }

    protected function tearDown(): void
    {
        Translator::reset();

        parent::tearDown();
    }

    protected function useLocale(string $locale): void
    {
        $this->currentLocale = $locale;
    }
}

<?php

declare(strict_types=1);

namespace Juksgraphic\EloquentModelTranslator\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Juksgraphic\EloquentModelTranslator\Concerns\HasTranslations;
use Juksgraphic\EloquentModelTranslator\Contracts\Translatable;

class Article extends Model implements Translatable
{
    use HasTranslations;

    protected $table = 'articles';

    protected $guarded = [];

    /** @var list<string> */
    protected array $translatable = ['title', 'body'];
}

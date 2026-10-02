<?php

declare(strict_types=1);

namespace Juksgraphic\EloquentModelTranslator\Tests\Fixtures;

use Illuminate\Database\Eloquent\SoftDeletes;

class SoftArticle extends Article
{
    use SoftDeletes;
}

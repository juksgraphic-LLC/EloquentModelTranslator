<?php

declare(strict_types=1);

namespace Juksgraphic\EloquentModelTranslator\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Juksgraphic\EloquentModelTranslator\Translator;

/**
 * One row per translatable model and locale; `content` holds the translated attributes.
 *
 * @property int                   $id
 * @property string                $translatable_type
 * @property int|string            $translatable_id
 * @property string                $locale
 * @property array<string, mixed>  $content
 *
 * @method static Builder<Translation> forLocale(string $locale)
 */
class Translation extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'translatable_type',
        'translatable_id',
        'locale',
        'content',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'content' => 'array',
    ];

    /**
     * Table name comes from the package configuration.
     *
     * @return string
     */
    public function getTable(): string
    {
        return Translator::config()->table;
    }

    /**
     * The model owning this translation.
     *
     * @return MorphTo<Model, $this>
     */
    public function translatable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Restricts the query to one locale.
     *
     * @param Builder<Translation> $query
     * @param string $locale
     * @return Builder<Translation>
     */
    public function scopeForLocale(Builder $query, string $locale): Builder
    {
        return $query->where('locale', $locale);
    }
}

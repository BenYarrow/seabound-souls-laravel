<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Model;

/**
 * Enforces a single featured row per model. When a row is saved with
 * is_featured = true, every OTHER row of the same model is un-featured. Uses a
 * query-builder update() (no model events) so it never recurses.
 */
trait HasSingleFeatured
{
    public static function bootHasSingleFeatured(): void
    {
        static::saved(function (Model $model) {
            if ($model->is_featured) {
                static::query()
                    ->whereKeyNot($model->getKey())
                    ->where('is_featured', true)
                    ->update(['is_featured' => false]);
            }
        });
    }
}

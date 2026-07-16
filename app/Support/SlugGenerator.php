<?php

// Generates a slug unique within a model's table by appending -2, -3, … on
// collision. Shared so slug logic isn't duplicated per model.

namespace App\Support;

use Illuminate\Database\Eloquent\Model;

class SlugGenerator
{
    /**
     * Return $base, or $base-N, guaranteed unique in $modelClass.$column,
     * ignoring the row with id $ignoreId (for updates).
     *
     * @param  class-string<Model>  $modelClass
     */
    public static function unique(string $base, string $modelClass, string $column = 'slug', ?int $ignoreId = null): string
    {
        $candidate = $base;
        $suffix = 2;

        while ($modelClass::query()
            ->where($column, $candidate)
            ->when($ignoreId, fn ($query) => $query->whereKeyNot($ignoreId))
            ->exists()
        ) {
            $candidate = "{$base}-{$suffix}";
            $suffix++;
        }

        return $candidate;
    }
}

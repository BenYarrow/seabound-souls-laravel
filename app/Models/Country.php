<?php

// Country lookup — groups spot guides by continent for the destinations page.

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Country extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'slug', 'continent'];

    public function spotGuides(): HasMany
    {
        return $this->hasMany(SpotGuide::class);
    }
}

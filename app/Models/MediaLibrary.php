<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class MediaLibrary extends Model implements HasMedia
{
    use InteractsWithMedia;

    protected $table = 'media_library';

    protected $fillable = ['name', 'folder'];

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('file')->singleFile();
    }

    public function getUrl(): string
    {
        return $this->getFirstMediaUrl('file');
    }

    public function getThumbnailUrl(): string
    {
        return $this->getFirstMediaUrl('file', 'thumb');
    }
}

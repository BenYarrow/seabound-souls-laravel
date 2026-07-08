<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class MediaLibrary extends Model implements HasMedia
{
    use InteractsWithMedia;

    protected $table = 'media_library';

    protected $fillable = ['name', 'folder', 'focal_x', 'focal_y'];

    protected $casts = [
        'focal_x' => 'integer',
        'focal_y' => 'integer',
    ];

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

    /**
     * The canonical image shape consumed across the app (controllers, content
     * blocks, front-end <CoverImage>). Focal values drive CSS object-position.
     *
     * @return array{url: string, alt: string, focal_x: int, focal_y: int}
     */
    public function imagePayload(): array
    {
        return [
            'url' => $this->getUrl(),
            'alt' => $this->name,
            'focal_x' => $this->focal_x ?? 50,
            'focal_y' => $this->focal_y ?? 50,
        ];
    }
}

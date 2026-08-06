<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class MediaLibrary extends Model implements HasMedia
{
    use InteractsWithMedia;

    protected $table = 'media_library';

    protected $fillable = ['name', 'folder', 'focal_x', 'focal_y', 'user_id', 'photographer_id'];

    protected $casts = [
        'focal_x' => 'integer',
        'focal_y' => 'integer',
    ];

    /**
     * Always eager-load the photographer.
     *
     * imagePayload() is called from 45+ sites across every controller and the
     * content-block resolver; adding `.photographer` to each eager-load call
     * individually would be one missed site away from an N+1 on a page of cards.
     * One relation, loaded once per parent query, is both cheaper and impossible
     * to forget.
     */
    protected $with = ['photographer'];

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
     * Owning user. Null means house media (owner-owned, hidden from contributors).
     *
     * @return BelongsTo<User, MediaLibrary>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The photographer credited for this image. Null means it is the site's own
     * work, which is the default and the majority case.
     *
     * @return BelongsTo<Photographer, MediaLibrary>
     */
    public function photographer(): BelongsTo
    {
        return $this->belongsTo(Photographer::class);
    }

    /**
     * The canonical image shape consumed across the app (controllers, content
     * blocks, front-end <CoverImage>). Focal values drive CSS object-position;
     * `credit` is null for the site's own images.
     *
     * @return array{url: string, alt: string, focal_x: int, focal_y: int, credit: array{name: string, url: string|null}|null}
     */
    public function imagePayload(): array
    {
        return [
            'url' => $this->getUrl(),
            'alt' => $this->name,
            'focal_x' => $this->focal_x ?? 50,
            'focal_y' => $this->focal_y ?? 50,
            'credit' => $this->photographer?->creditPayload(),
        ];
    }
}

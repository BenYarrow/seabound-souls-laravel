<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Support\SlugGenerator;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;

class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'first_name',
        'last_name',
        'email',
        'password',
        'role',
        'slug',
        'profile_image_media_id',
        'static_masthead_media_id',
        'profile_blocks',
        'socials',
    ];

    /** Role values. Owner = the house account(s); Contributor = invited contributor. */
    public const ROLE_OWNER = 'owner';

    public const ROLE_CONTRIBUTOR = 'contributor';

    /**
     * Keep `name` (the canonical display column used by auth/account UIs) in sync
     * with the structured contributor first/last names. Only runs when first/last are
     * set, so the owner's brand name ("Seabound Sessions", first/last null) is left
     * untouched.
     */
    protected static function booted(): void
    {
        static::saving(function (User $user) {
            if ($user->isDirty(['first_name', 'last_name']) && ($user->first_name || $user->last_name)) {
                $user->name = trim(($user->first_name ?? '').' '.($user->last_name ?? ''));
            }

            // Contributors get a public URL slug from their full name, generated
            // once and kept stable. Collision-suffixed so identical names differ.
            if ($user->role === self::ROLE_CONTRIBUTOR && blank($user->slug) && ($user->first_name || $user->last_name)) {
                $base = Str::slug(trim(($user->first_name ?? '').' '.($user->last_name ?? '')));
                $user->slug = SlugGenerator::unique($base, static::class, 'slug', $user->id);
            }
        });
    }

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'profile_blocks' => 'array',
            'socials' => 'array',
        ];
    }

    /**
     * Panel access is granted to any recognised role. Fine-grained gating of
     * resources (contact PII, house media, other people's guides) is enforced by
     * per-model Policies, not here.
     */
    public function canAccessPanel(Panel $panel): bool
    {
        return in_array($this->role, [self::ROLE_OWNER, self::ROLE_CONTRIBUTOR], true);
    }

    /** True when this account is a house owner. */
    public function isOwner(): bool
    {
        return $this->role === self::ROLE_OWNER;
    }

    /** True when this account is an invited contributor. */
    public function isContributor(): bool
    {
        return $this->role === self::ROLE_CONTRIBUTOR;
    }

    /**
     * Spot guides authored by this user.
     *
     * @return HasMany<SpotGuide>
     */
    public function authoredSpotGuides(): HasMany
    {
        return $this->hasMany(SpotGuide::class);
    }

    /** Portrait image — roll-up card thumbnail + profile-page portrait. */
    public function profileImageMedia(): BelongsTo
    {
        return $this->belongsTo(MediaLibrary::class, 'profile_image_media_id');
    }

    /** Profile-page hero image (null → gradient masthead fallback). */
    public function staticMastheadMedia(): BelongsTo
    {
        return $this->belongsTo(MediaLibrary::class, 'static_masthead_media_id');
    }

    /**
     * Authored guides that are published — the ones shown on the public profile
     * and the gate for whether the profile exists at all.
     *
     * @return HasMany<SpotGuide>
     */
    public function publishedAuthoredGuides(): HasMany
    {
        return $this->authoredSpotGuides()->where('is_published', true);
    }

    /**
     * A contributor's public profile is live only once they have a published
     * guide — public presence earned by contributing. Owners have no profile.
     */
    public function hasPublicProfile(): bool
    {
        return $this->isContributor() && $this->publishedAuthoredGuides()->exists();
    }

    /** Contributors whose public profile is live (≥1 published guide). */
    public function scopeWithPublicProfile(Builder $query): Builder
    {
        return $query->where('role', self::ROLE_CONTRIBUTOR)
            ->whereHas('authoredSpotGuides', fn (Builder $guideQuery) => $guideQuery->where('is_published', true));
    }
}

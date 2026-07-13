<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

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
    ];

    /** Role values. Owner = the house account(s); Rider = invited contributor. */
    public const ROLE_OWNER = 'owner';

    public const ROLE_RIDER = 'rider';

    /**
     * Keep `name` (the canonical display column used by auth/account UIs) in sync
     * with the structured rider first/last names. Only runs when first/last are
     * set, so the owner's brand name ("Seabound Souls", first/last null) is left
     * untouched.
     */
    protected static function booted(): void
    {
        static::saving(function (User $user) {
            if ($user->isDirty(['first_name', 'last_name']) && ($user->first_name || $user->last_name)) {
                $user->name = trim(($user->first_name ?? '').' '.($user->last_name ?? ''));
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
        ];
    }

    /**
     * Panel access is granted to any recognised role. Fine-grained gating of
     * resources (contact PII, house media, other people's guides) is enforced by
     * per-model Policies, not here.
     */
    public function canAccessPanel(Panel $panel): bool
    {
        return in_array($this->role, [self::ROLE_OWNER, self::ROLE_RIDER], true);
    }

    /** True when this account is a house owner. */
    public function isOwner(): bool
    {
        return $this->role === self::ROLE_OWNER;
    }

    /** True when this account is an invited rider contributor. */
    public function isRider(): bool
    {
        return $this->role === self::ROLE_RIDER;
    }

    /**
     * Spot guides authored by this user.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany<SpotGuide>
     */
    public function authoredSpotGuides(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(SpotGuide::class);
    }
}

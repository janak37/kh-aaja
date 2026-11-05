<?php

namespace App\Models;

use Database\Factories\ShopFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property string $name
 * @property string $slug
 * @property string|null $description
 * @property string $category
 * @property string $area
 * @property string $phone
 * @property string|null $whatsapp_number
 * @property string|null $address
 * @property string|null $cover_photo_path
 * @property string $timezone
 * @property string|null $opens_at
 * @property string|null $closes_at
 * @property bool $is_pro
 * @property Carbon|null $suspended_at
 * @property int $favourites_count
 * @property int $score
 * @property array<string, int>|null $score_parts
 * @property Carbon|null $score_updated_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User $owner
 * @property-read Collection<int, Item> $items
 * @property-read Collection<int, BoardUpdate> $boardUpdates
 * @property-read Collection<int, Reservation> $reservations
 */
#[Fillable([
    'name', 'slug', 'description', 'category', 'area',
    'phone', 'whatsapp_number', 'address', 'cover_photo_path',
    'timezone', 'opens_at', 'closes_at',
])]
class Shop extends Model
{
    /** @use HasFactory<ShopFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_pro' => 'boolean',
            'suspended_at' => 'datetime',
            'favourites_count' => 'integer',
            'score' => 'integer',
            'score_parts' => 'array',
            'score_updated_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * The shop's menu, in the owner's chosen order.
     *
     * @return HasMany<Item, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(Item::class)->orderBy('sort_order');
    }

    /**
     * Days on which this shop updated its board (drives the streak).
     *
     * @return HasMany<BoardUpdate, $this>
     */
    public function boardUpdates(): HasMany
    {
        return $this->hasMany(BoardUpdate::class);
    }

    /**
     * Customer reservations for this shop's items.
     *
     * @return HasMany<Reservation, $this>
     */
    public function reservations(): HasMany
    {
        return $this->hasMany(Reservation::class);
    }

    public function isSuspended(): bool
    {
        return $this->suspended_at !== null;
    }

    /**
     * "Momo", "Khaja set"… from config/khaaja.php.
     */
    public function categoryLabel(): string
    {
        return __(config('khaaja.categories.'.$this->category, $this->category));
    }

    /**
     * True/false in the shop's own timezone; null when hours are not set.
     * Handles shops that close after midnight (e.g. 18:00–02:00).
     */
    public function isOpenNow(): ?bool
    {
        if ($this->opens_at === null || $this->closes_at === null) {
            return null;
        }

        $now = now($this->timezone)->format('H:i');
        $open = substr($this->opens_at, 0, 5);
        $close = substr($this->closes_at, 0, 5);

        return $open <= $close
            ? $now >= $open && $now < $close
            : $now >= $open || $now < $close;
    }

    /**
     * wa.me link with a friendly message typed in, or null without a WhatsApp number.
     */
    public function whatsappUrl(?string $dish = null): ?string
    {
        if ($this->whatsapp_number === null) {
            return null;
        }

        $text = $dish === null
            ? __('Namaste! I found you on Kh-aaja.')
            : __('Namaste! Is :dish available today? (via Kh-aaja)', ['dish' => $dish]);

        return 'https://wa.me/977'.$this->whatsapp_number.'?text='.rawurlencode($text);
    }

    /**
     * Public URLs use the slug: /s/{slug}
     */
    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}

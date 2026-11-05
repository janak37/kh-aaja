<?php

namespace App\Models;

use Database\Factories\ItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property int $shop_id
 * @property string $name
 * @property string $dish_key
 * @property string|null $description
 * @property int|null $default_price
 * @property bool $is_veg
 * @property string|null $photo_path
 * @property int $sort_order
 * @property bool $is_archived
 * @property int $favourites_count
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Shop $shop
 * @property-read Collection<int, DailyStatus> $dailyStatuses
 */
#[Fillable([
    'name', 'description', 'default_price', 'is_veg',
    'photo_path', 'sort_order', 'is_archived',
])]
class Item extends Model
{
    /** @use HasFactory<ItemFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::saving(function (Item $item): void {
            $item->dish_key = self::makeDishKey($item->name);
        });
    }

    /**
     * "Jhol Momo" -> "jhol-momo". Nepali names that can't be turned
     * into English letters are kept as lowercase words joined by "-".
     */
    public static function makeDishKey(string $name): string
    {
        $key = Str::slug($name);

        return $key !== '' ? $key : Str::of($name)->squish()->lower()->replace(' ', '-')->toString();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'default_price' => 'integer',
            'is_veg' => 'boolean',
            'sort_order' => 'integer',
            'is_archived' => 'boolean',
            'favourites_count' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Shop, $this>
     */
    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    /**
     * @return HasMany<DailyStatus, $this>
     */
    public function dailyStatuses(): HasMany
    {
        return $this->hasMany(DailyStatus::class);
    }

    /**
     * Public URL of the dish photo, or null when there is none.
     */
    public function photoUrl(): ?string
    {
        return $this->photo_path === null ? null : Storage::disk('public')->url($this->photo_path);
    }

    /**
     * Items still on the menu (not archived).
     *
     * @param  Builder<Item>  $query
     */
    #[Scope]
    protected function active(Builder $query): void
    {
        $query->where('is_archived', false);
    }
}

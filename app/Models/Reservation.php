<?php

namespace App\Models;

use App\Enums\DeclineReason;
use App\Enums\ReservationStatus;
use Database\Factories\ReservationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property int $shop_id
 * @property int $item_id
 * @property Carbon $board_date
 * @property string|null $customer_name
 * @property string|null $customer_phone
 * @property string|null $note
 * @property Carbon $consent_at
 * @property Carbon|null $purged_at
 * @property int $quantity
 * @property ReservationStatus $status
 * @property DeclineReason|null $decline_reason
 * @property Carbon|null $decided_at
 * @property string $public_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Shop $shop
 * @property-read Item $item
 */
#[Fillable(['board_date', 'customer_name', 'customer_phone', 'note', 'quantity', 'consent_at'])]
#[Hidden(['customer_phone'])]
class Reservation extends Model
{
    /** @use HasFactory<ReservationFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::creating(function (Reservation $reservation): void {
            $reservation->public_token ??= Str::random(32);
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'board_date' => 'date',
            'consent_at' => 'datetime',
            'purged_at' => 'datetime',
            'quantity' => 'integer',
            'status' => ReservationStatus::class,
            'decline_reason' => DeclineReason::class,
            'decided_at' => 'datetime',
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
     * @return BelongsTo<Item, $this>
     */
    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    /**
     * The customer's status link works until 2 days after the reservation date
     * (in the shop's timezone), then shows 404.
     */
    public function statusLinkExpired(): bool
    {
        return $this->board_date->toDateString() < now($this->shop->timezone)->subDays(2)->toDateString();
    }
}

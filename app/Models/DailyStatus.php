<?php

namespace App\Models;

use App\Enums\ItemStatus;
use Database\Factories\DailyStatusFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One row = how one dish is doing on one day (the shop's local date).
 *
 * @property int $id
 * @property int $item_id
 * @property Carbon $board_date
 * @property ItemStatus $status
 * @property int|null $price
 * @property bool $is_special
 * @property string|null $special_note
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Item $item
 */
#[Fillable(['board_date', 'status', 'price', 'is_special', 'special_note'])]
class DailyStatus extends Model
{
    /** @use HasFactory<DailyStatusFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'board_date' => 'date',
            'status' => ItemStatus::class,
            'price' => 'integer',
            'is_special' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Item, $this>
     */
    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }
}

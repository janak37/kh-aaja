<?php

namespace App\Models;

use Database\Factories\BoardUpdateFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One row per shop per (shop-local) day on which the board was updated.
 *
 * @property int $id
 * @property int $shop_id
 * @property Carbon $board_date
 * @property Carbon $last_updated_at
 * @property-read Shop $shop
 */
#[Fillable(['board_date', 'last_updated_at'])]
class BoardUpdate extends Model
{
    /** @use HasFactory<BoardUpdateFactory> */
    use HasFactory;

    public $timestamps = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'board_date' => 'date',
            'last_updated_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Shop, $this>
     */
    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }
}

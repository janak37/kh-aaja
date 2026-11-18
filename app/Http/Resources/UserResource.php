<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The only user fields the API ever shows: never the password, tokens or 2FA secrets.
 *
 * @mixin User
 */
class UserResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $shop = $this->shop;

        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'roles' => $this->getRoleNames()->values(),
            'shop' => $shop === null ? null : [
                'id' => $shop->id,
                'name' => $shop->name,
                'slug' => $shop->slug,
                'board_url' => route('board.show', $shop),
            ],
        ];
    }
}

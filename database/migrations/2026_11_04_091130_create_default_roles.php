<?php

use App\Enums\UserRole;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    public function up(): void
    {
        foreach (UserRole::cases() as $role) {
            Role::findOrCreate($role->value, 'web');
        }
    }

    public function down(): void
    {
        Role::query()
            ->whereIn('name', array_column(UserRole::cases(), 'value'))
            ->delete();
    }
};

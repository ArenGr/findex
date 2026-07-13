<?php

namespace App\Filament\Resources\Admins\Pages;

use App\Enums\UserRole;
use App\Filament\Resources\Admins\AdminResource;
use App\Models\User;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateAdmin extends CreateRecord
{
    protected static string $resource = AdminResource::class;

    /**
     * `role` is deliberately excluded from User::$fillable (see
     * User::canAccessPanel's docblock) so a plain `User::create($data)`
     * here would silently drop it - forceFill it after creation instead.
     */
    protected function handleRecordCreation(array $data): Model
    {
        $user = User::create($data);
        $user->forceFill(['role' => UserRole::ADMIN])->save();

        return $user;
    }
}

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

    protected function handleRecordCreation(array $data): Model
    {
        $user = User::create($data);
        $user->forceFill(['role' => UserRole::ADMIN])->save();

        return $user;
    }
}

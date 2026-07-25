<?php

namespace App\Models;

use Database\Factories\WriterFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Writer extends Model
{
    /** @use HasFactory<WriterFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'slug',
        'expertise',
        'topics',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /**
     * Staff accounts logging in on this writer profile's behalf (guard
     * 'writer', role 'writer') - see User::writer(). A HasMany rather than
     * a single owner, mirroring Organization::users(), in case a writer
     * profile ever supports multiple co-author logins.
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }
}

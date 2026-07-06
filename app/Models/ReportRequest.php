<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ReportRequest extends Model
{
    protected $fillable = [
        'organization_id',
        'branch_id',
        'period_from',
        'period_to',
        'status',
        'error_message',
    ];

    protected $casts = [
        'period_from' => 'date',
        'period_to' => 'date',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function report(): HasOne
    {
        return $this->hasOne(Report::class);
    }

    /**
     * Mark the request as currently being processed.
     */
    public function markAsProcessing(): void
    {
        $this->update(['status' => 'processing']);
    }

    /**
     * Mark the request as successfully completed.
     */
    public function markAsCompleted(): void
    {
        $this->update([
            'status' => 'completed',
            'error_message' => null,
        ]);
    }

    /**
     * Mark the request as failed.
     */
    public function markAsFailed(string $errorMessage): void
    {
        $this->update([
            'status' => 'failed',
            'error_message' => $errorMessage,
        ]);
    }
}

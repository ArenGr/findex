<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Report extends Model
{
    protected $fillable = [
        'report_request_id',
        'organization_id',
        'branch_id',
        'review_count',
        'positive_pct',
        'neutral_pct',
        'negative_pct',
        'summary',
        'themes',
    ];

    protected $casts = [
        'themes' => 'array',
        'positive_pct' => 'decimal:2',
        'neutral_pct' => 'decimal:2',
        'negative_pct' => 'decimal:2',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function reportRequest(): BelongsTo
    {
        return $this->belongsTo(ReportRequest::class);
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }
}

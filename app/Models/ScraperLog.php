<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScraperLog extends Model
{
    protected $table = 'scraper_logs';

    protected $fillable = [
        'scraping_job_id',
        'level',
        'message',
        'context',
    ];

    protected $casts = [
        'context' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the scraping job this log belongs to.
     */
    public function scrapingJob(): BelongsTo
    {
        return $this->belongsTo(ScrapingJob::class, 'scraping_job_id');
    }
}

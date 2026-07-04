<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ScrapingJob extends Model
{
    protected $fillable = [
        'organization_id',
        'source_type',
        'status',
        'started_at',
        'finished_at',
        'records_found',
        'error_message',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the organization this job belongs to.
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * Get all logs for this job.
     */
    public function logs(): HasMany
    {
        return $this->hasMany(ScraperLog::class, 'scraping_job_id');
    }

    /**
     * Mark job as running.
     */
    public function markAsRunning(): void
    {
        $this->update([
            'status' => 'running',
            'started_at' => now(),
        ]);
    }

    /**
     * Mark job as successful.
     */
    public function markAsSuccess(int $recordsFound = 0): void
    {
        $this->update([
            'status' => 'success',
            'finished_at' => now(),
            'records_found' => $recordsFound,
            'error_message' => null,
        ]);
    }

    /**
     * Mark job as failed.
     */
    public function markAsFailed(string $errorMessage): void
    {
        $this->update([
            'status' => 'failed',
            'finished_at' => now(),
            'error_message' => $errorMessage,
        ]);
    }

    /**
     * Get the duration of the job in seconds.
     */
    public function getDuration(): ?int
    {
        if (!$this->started_at || !$this->finished_at) {
            return null;
        }

        return $this->finished_at->diffInSeconds($this->started_at);
    }

    /**
     * Add a log entry.
     */
    public function log(string $level, string $message, array $context = []): void
    {
        $this->logs()->create([
            'level' => $level,
            'message' => $message,
            'context' => !empty($context) ? $context : null,
        ]);
    }
}

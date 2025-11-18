<?php

namespace Masum\AiTranslator\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TranslationHistory extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'translation_id',
        'old_value',
        'new_value',
        'changed_by_user_id',
        'change_type',
        'ip_address',
        'user_agent',
    ];

    /**
     * Get the translation that owns this history.
     */
    public function translation(): BelongsTo
    {
        return $this->belongsTo(Translation::class);
    }

    /**
     * Get the user who made the change.
     */
    public function changedBy(): BelongsTo
    {
        $userModel = config('ai-translator.user_model', 'App\\Models\\User');

        return $this->belongsTo($userModel, 'changed_by_user_id');
    }

    /**
     * Scope to get history for a specific translation.
     */
    public function scopeForTranslation($query, int $translationId)
    {
        return $query->where('translation_id', $translationId)
            ->orderBy('created_at', 'desc');
    }

    /**
     * Scope to get history by change type.
     */
    public function scopeByChangeType($query, string $changeType)
    {
        return $query->where('change_type', $changeType);
    }

    /**
     * Scope to get recent changes.
     */
    public function scopeRecent($query, int $limit = 50)
    {
        return $query->orderBy('created_at', 'desc')->limit($limit);
    }

    /**
     * Get formatted change summary.
     */
    public function getChangeSummary(): string
    {
        return match ($this->change_type) {
            'created' => "Created with value: {$this->new_value}",
            'updated' => "Updated from '{$this->old_value}' to '{$this->new_value}'",
            'deleted' => "Deleted value: {$this->old_value}",
            default => "Unknown change",
        };
    }
}

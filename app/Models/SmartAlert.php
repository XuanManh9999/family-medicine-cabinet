<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SmartAlert extends Model
{
    use HasFactory;

    protected $fillable = [
        'family_id',
        'user_id',
        'type',
        'level',
        'title',
        'content',
        'is_read',
        'action_url',
    ];

    protected function casts(): array
    {
        return [
            'is_read' => 'boolean',
        ];
    }

    public function family()
    {
        return $this->belongsTo(Family::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // Scopes
    public function scopeUnread(Builder $query): Builder
    {
        return $query->where('is_read', false);
    }

    public function scopeDanger(Builder $query): Builder
    {
        return $query->where('level', 'danger');
    }

    public function scopeWarning(Builder $query): Builder
    {
        return $query->where('level', 'warning');
    }

    public function scopeRecent(Builder $query, int $limit = 10): Builder
    {
        return $query->orderBy('created_at', 'desc')->limit($limit);
    }

    // Accessors
    public function getBadgeClassAttribute(): string
    {
        return match ($this->level) {
            'danger' => 'bg-red-100 text-red-700 border-red-300',
            'warning' => 'bg-amber-100 text-amber-700 border-amber-300',
            'info' => 'bg-blue-100 text-blue-700 border-blue-300',
            default => 'bg-gray-100 text-gray-700 border-gray-300',
        };
    }
}

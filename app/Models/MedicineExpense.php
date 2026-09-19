<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MedicineExpense extends Model
{
    use HasFactory;

    protected $fillable = [
        'family_id',
        'treatment_course_id',
        'family_member_id',
        'title',
        'amount',
        'purchase_date',
        'receipt_image_path',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'purchase_date' => 'date',
        ];
    }

    public function family()
    {
        return $this->belongsTo(Family::class);
    }

    public function treatmentCourse()
    {
        return $this->belongsTo(TreatmentCourse::class);
    }

    public function familyMember()
    {
        return $this->belongsTo(FamilyMember::class);
    }

    // Scopes
    public function scopeThisMonth(Builder $query): Builder
    {
        $start = Carbon::now()->startOfMonth();
        $end = Carbon::now()->endOfMonth();
        return $query->whereBetween('purchase_date', [$start, $end]);
    }

    public function scopeForMember(Builder $query, int $memberId): Builder
    {
        return $query->where('family_member_id', $memberId);
    }

    // Accessors
    public function getFormattedAmountAttribute(): string
    {
        return number_format($this->amount, 0, ',', '.') . ' đ';
    }
}

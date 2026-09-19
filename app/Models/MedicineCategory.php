<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MedicineCategory extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'is_prescription_required',
    ];

    protected function casts(): array
    {
        return [
            'is_prescription_required' => 'boolean',
        ];
    }

    public function medicines()
    {
        return $this->hasMany(Medicine::class, 'category_id');
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Medicine extends Model
{
    use HasFactory;

    protected $fillable = [
        'category_id',
        'code',
        'name',
        'active_ingredient',
        'concentration',
        'dosage_form',
        'default_unit',
        'manufacturer',
        'usage_instructions',
        'side_effects',
        'contraindications',
    ];

    public function category()
    {
        return $this->belongsTo(MedicineCategory::class, 'category_id');
    }

    public function cabinetItems()
    {
        return $this->hasMany(CabinetItem::class);
    }

    public function prescriptionItems()
    {
        return $this->hasMany(PrescriptionItem::class);
    }
}

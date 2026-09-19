<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Family extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'code',
        'created_by',
        'address',
    ];

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function users()
    {
        return $this->hasMany(User::class);
    }

    public function members()
    {
        return $this->hasMany(FamilyMember::class);
    }

    public function cabinetItems()
    {
        return $this->hasMany(CabinetItem::class);
    }

    public function treatmentCourses()
    {
        return $this->hasMany(TreatmentCourse::class);
    }

    public function medicationSchedules()
    {
        return $this->hasMany(MedicationSchedule::class);
    }

    public function expenses()
    {
        return $this->hasMany(MedicineExpense::class);
    }

    public function smartAlerts()
    {
        return $this->hasMany(SmartAlert::class);
    }
}

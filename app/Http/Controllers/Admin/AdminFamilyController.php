<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Family;
use Illuminate\Http\Request;

class AdminFamilyController extends Controller
{
    /**
     * Danh sách tất cả các Hộ gia đình trong hệ thống
     */
    public function index(Request $request)
    {
        $search = trim((string) $request->query('q'));

        $query = Family::withCount([
            'users',
            'members',
            'cabinetItems',
            'treatmentCourses',
            'expenses',
        ])->with('creator');

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('code', 'like', "%{$search}%")
                  ->orWhere('address', 'like', "%{$search}%");
            });
        }

        $families = $query->latest()->paginate(15)->withQueryString();

        return view('admin.families.index', compact('families', 'search'));
    }

    /**
     * Chi tiết một Hộ gia đình
     */
    public function show(Family $family)
    {
        $family->load([
            'creator',
            'users',
            'members.medicationSchedules',
            'cabinetItems.medicine.category',
            'treatmentCourses.familyMember',
            'expenses' => fn ($q) => $q->latest()->take(10),
        ]);

        return view('admin.families.show', compact('family'));
    }
}

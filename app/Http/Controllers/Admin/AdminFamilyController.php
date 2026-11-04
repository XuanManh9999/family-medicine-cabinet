<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Family;
use App\Support\DateRangeFilter;
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

        $dateFilter = DateRangeFilter::fromRequest($request);
        $dateFilter->apply($query);

        $families = $query->latest()->paginate(15)->withQueryString();

        return view('admin.families.index', compact('families', 'search', 'dateFilter'));
    }

    /**
     * Chi tiết một Hộ gia đình
     */
    public function show(Request $request, Family $family)
    {
        // Lọc đơn điều trị theo ngày bắt đầu và chi phí theo ngày mua
        $dateFilter = DateRangeFilter::fromRequest($request);

        $family->load([
            'creator',
            'users',
            'members.medicationSchedules',
            'cabinetItems.medicine.category',
            'treatmentCourses' => fn ($q) => $dateFilter->apply($q, 'start_date')->with('familyMember')->orderByDesc('start_date'),
        ]);

        $expensesQuery = $dateFilter->apply($family->expenses()->getQuery(), 'purchase_date');
        $expenseTotal = (float) (clone $expensesQuery)->sum('amount');
        $expenseCount = (clone $expensesQuery)->count();
        $expenses = $expensesQuery->with('familyMember')->orderByDesc('purchase_date')->take(20)->get();

        return view('admin.families.show', compact('family', 'dateFilter', 'expenses', 'expenseTotal', 'expenseCount'));
    }
}

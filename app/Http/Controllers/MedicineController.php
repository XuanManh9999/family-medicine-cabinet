<?php

namespace App\Http\Controllers;

use App\Models\CabinetItem;
use App\Models\Medicine;
use App\Models\MedicineCategory;
use Carbon\Carbon;
use Illuminate\Http\Request;

class MedicineController extends Controller
{
    /**
     * Tra cứu danh mục thuốc mẫu chuẩn Việt Nam (Master Data)
     */
    public function index(Request $request)
    {
        $family = $this->ensureFamily($request);

        $search = trim((string) $request->query('q'));
        $categoryId = $request->query('category_id');
        $dosageForm = $request->query('dosage_form');

        $query = Medicine::with('category');

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('code', 'like', "%{$search}%")
                  ->orWhere('active_ingredient', 'like', "%{$search}%")
                  ->orWhere('manufacturer', 'like', "%{$search}%")
                  ->orWhere('usage_instructions', 'like', "%{$search}%");
            });
        }

        if ($categoryId) {
            $query->where('category_id', $categoryId);
        }

        if ($dosageForm) {
            $query->where('dosage_form', $dosageForm);
        }

        $medicines = $query->orderBy('name')->paginate(12)->withQueryString();

        $categories = MedicineCategory::withCount('medicines')->orderBy('name')->get();

        $dosageForms = Medicine::whereNotNull('dosage_form')
            ->where('dosage_form', '!=', '')
            ->distinct()
            ->pluck('dosage_form')
            ->sort()
            ->values();

        // Danh sách ID thuốc đã có sẵn trong tủ thuốc gia đình để hiển thị badge/indicator
        $existingCabinetMedicineIds = $family->cabinetItems()
            ->whereNotNull('medicine_id')
            ->pluck('medicine_id')
            ->unique()
            ->toArray();

        // Thống kê tổng số thuốc
        $totalMedicinesCount = Medicine::count();

        return view('medicines.index', compact(
            'medicines',
            'categories',
            'dosageForms',
            'search',
            'categoryId',
            'dosageForm',
            'existingCabinetMedicineIds',
            'totalMedicinesCount'
        ));
    }

    /**
     * Xem thông tin chi tiết một loại thuốc mẫu
     */
    public function show(Medicine $medicine)
    {
        $medicine->load('category');

        if (request()->wantsJson() || request()->ajax()) {
            return response()->json([
                'success' => true,
                'medicine' => $medicine,
            ]);
        }

        return view('medicines.show', compact('medicine'));
    }

    /**
     * Thêm nhanh một thuốc từ danh mục mẫu vào tủ thuốc gia đình
     */
    public function quickAddToCabinet(Request $request, Medicine $medicine)
    {
        $family = $this->ensureFamily($request);

        $validated = $request->validate([
            'quantity' => 'required|numeric|min:0.5',
            'unit' => 'nullable|string|max:30',
            'expiry_date' => 'required|date|after_or_equal:today',
            'manufacture_date' => 'nullable|date|before_or_equal:today',
            'storage_location' => 'nullable|string|max:100',
            'batch_number' => 'nullable|string|max:50',
            'price_per_unit' => 'nullable|numeric|min:0',
        ], [
            'quantity.required' => 'Vui lòng nhập số lượng thuốc thêm vào tủ.',
            'quantity.min' => 'Số lượng tối thiểu là 0.5.',
            'expiry_date.required' => 'Vui lòng nhập hạn sử dụng của thuốc.',
            'expiry_date.after_or_equal' => 'Hạn sử dụng không thể là ngày trong quá khứ.',
        ]);

        $item = CabinetItem::create([
            'family_id' => $family->id,
            'medicine_id' => $medicine->id,
            'custom_name' => $medicine->name,
            'batch_number' => $validated['batch_number'] ?? null,
            'manufacture_date' => $validated['manufacture_date'] ?? null,
            'expiry_date' => $validated['expiry_date'],
            'quantity' => $validated['quantity'],
            'unit' => $validated['unit'] ?: ($medicine->default_unit ?? 'viên'),
            'storage_location' => $validated['storage_location'] ?? 'Ngăn chính',
            'price_per_unit' => $validated['price_per_unit'] ?? 0,
            'status' => 'con_han',
            'notes' => 'Thêm từ danh mục chuẩn: ' . $medicine->name,
        ]);

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => "Đã thêm thuốc \"{$medicine->name}\" vào tủ thuốc gia đình!",
                'item' => $item,
            ]);
        }

        return redirect()->route('cabinet.index')
            ->with('success', "Đã thêm thuốc \"{$medicine->name}\" ({$validated['quantity']} {$item->unit}) vào tủ thuốc gia đình thành công!");
    }

    /**
     * Đảm bảo người dùng thuộc về hộ gia đình
     */
    protected function ensureFamily(Request $request)
    {
        $family = $request->user()->family;
        if (!$family) {
            abort(403, 'Tài khoản chưa thuộc về hộ gia đình.');
        }
        return $family;
    }
}

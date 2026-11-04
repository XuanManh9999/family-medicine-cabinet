<?php

namespace App\Http\Controllers;

use App\Models\CabinetItem;
use App\Models\Medicine;
use App\Models\MedicineCategory;
use App\Services\AlertScannerService;
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

        $family = $this->ensureFamily(request());
        $cabinetItems = $family->cabinetItems()
            ->where('medicine_id', $medicine->id)
            ->orderBy('expiry_date')
            ->get();

        return view('medicines.show', compact('medicine', 'cabinetItems'));
    }

    /**
     * Thêm nhanh một thuốc từ danh mục mẫu vào tủ thuốc gia đình
     */
    public function quickAddToCabinet(Request $request, Medicine $medicine)
    {
        $family = $this->ensureFamily($request);
        abort_unless($request->user()->canManageCabinet(), 403, 'Chỉ Bác sĩ/Người chăm sóc y tế hoặc Quản trị viên hệ thống mới có quyền thêm thuốc vào tủ thuốc gia đình.');

        // Chấp nhận cả tên trường cũ "manufacturing_date" (đưa về manufacture_date trước khi validate)
        if (!$request->filled('manufacture_date') && $request->filled('manufacturing_date')) {
            $request->merge(['manufacture_date' => $request->input('manufacturing_date')]);
        }

        $validated = $request->validate([
            'quantity' => 'required|numeric|min:0.5|max:999999.99',
            'unit' => 'nullable|string|max:30',
            'expiry_date' => 'required|date|after_or_equal:today',
            'manufacture_date' => 'nullable|date|before_or_equal:today|before_or_equal:expiry_date',
            'storage_location' => 'nullable|string|max:255',
            'batch_number' => 'nullable|string|max:255',
            'price_per_unit' => 'nullable|numeric|min:0|max:999999999.99',
        ], [
            'quantity.required' => 'Vui lòng nhập số lượng thuốc thêm vào tủ.',
            'quantity.numeric' => 'Số lượng phải là số.',
            'quantity.min' => 'Số lượng tối thiểu là 0.5.',
            'quantity.max' => 'Số lượng tối đa là 999.999,99.',
            'expiry_date.required' => 'Vui lòng nhập hạn sử dụng của thuốc.',
            'expiry_date.after_or_equal' => 'Hạn sử dụng không thể là ngày trong quá khứ.',
            'manufacture_date.before_or_equal' => 'Ngày sản xuất không thể ở tương lai hoặc sau hạn sử dụng.',
            'price_per_unit.numeric' => 'Đơn giá phải là số.',
            'price_per_unit.min' => 'Đơn giá không được âm.',
            'price_per_unit.max' => 'Đơn giá vượt quá giới hạn cho phép.',
        ]);

        $item = CabinetItem::create([
            'family_id' => $family->id,
            'medicine_id' => $medicine->id,
            'custom_name' => $medicine->name,
            'batch_number' => $validated['batch_number'] ?? null,
            'manufacturing_date' => $validated['manufacture_date'] ?? null,
            'expiry_date' => $validated['expiry_date'],
            'quantity' => $validated['quantity'],
            'unit' => ($validated['unit'] ?? null) ?: ($medicine->default_unit ?: 'viên'),
            'storage_location' => ($validated['storage_location'] ?? null) ?: 'Ngăn chính',
            'price_per_unit' => $validated['price_per_unit'] ?? null,
            'status' => CabinetItem::computeStatus($validated['expiry_date'], $validated['quantity']),
            'notes' => 'Thêm từ danh mục chuẩn: ' . $medicine->name,
        ]);

        app(AlertScannerService::class)->scanFamily($family);

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

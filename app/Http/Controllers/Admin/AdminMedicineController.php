<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Medicine;
use App\Models\MedicineCategory;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AdminMedicineController extends Controller
{
    /**
     * Danh sách dược phẩm Master Data trong hệ thống
     */
    public function index(Request $request)
    {
        $search = trim((string) $request->query('q'));
        $categoryId = $request->query('category_id');
        $dosageForm = $request->query('dosage_form');

        $query = Medicine::with('category')->withCount('cabinetItems');

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('code', 'like', "%{$search}%")
                  ->orWhere('active_ingredient', 'like', "%{$search}%")
                  ->orWhere('manufacturer', 'like', "%{$search}%");
            });
        }

        if ($categoryId) {
            $query->where('category_id', $categoryId);
        }

        if ($dosageForm) {
            $query->where('dosage_form', $dosageForm);
        }

        $medicines = $query->orderBy('name')->paginate(15)->withQueryString();
        $categories = MedicineCategory::orderBy('name')->get();
        $dosageForms = Medicine::whereNotNull('dosage_form')
            ->where('dosage_form', '!=', '')
            ->distinct()
            ->pluck('dosage_form')
            ->sort()
            ->values();

        return view('admin.medicines.index', compact(
            'medicines',
            'categories',
            'dosageForms',
            'search',
            'categoryId',
            'dosageForm'
        ));
    }

    /**
     * Form thêm thuốc mới vào Master Data
     */
    public function create()
    {
        $medicine = new Medicine();
        $categories = MedicineCategory::orderBy('name')->get();

        return view('admin.medicines.form', [
            'medicine' => $medicine,
            'categories' => $categories,
            'editing' => false,
        ]);
    }

    /**
     * Lưu thuốc mới vào Master Data
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'category_id' => 'required|exists:medicine_categories,id',
            'code' => 'required|string|max:50|unique:medicines,code',
            'name' => 'required|string|max:255',
            'active_ingredient' => 'required|string|max:255',
            'concentration' => 'nullable|string|max:100',
            'dosage_form' => 'nullable|string|max:100',
            'default_unit' => 'required|string|max:30',
            'manufacturer' => 'nullable|string|max:255',
            'usage_instructions' => 'nullable|string|max:5000',
            'side_effects' => 'nullable|string|max:5000',
            'contraindications' => 'nullable|string|max:5000',
        ], [
            'code.required' => 'Mã lưu hành thuốc không được để trống.',
            'code.unique' => 'Mã lưu hành này đã tồn tại trong hệ thống.',
            'name.required' => 'Tên thương mại của thuốc không được để trống.',
            'active_ingredient.required' => 'Hoạt chất chính không được để trống.',
            'category_id.required' => 'Vui lòng chọn nhóm dược lý cho thuốc.',
        ]);

        $medicine = Medicine::create($validated);

        return redirect()->route('admin.medicines.index')
            ->with('success', "Đã thêm mới thuốc \"{$medicine->name}\" ({$medicine->code}) vào Dược thư Quốc gia thành công!");
    }

    /**
     * Form chỉnh sửa thông tin thuốc
     */
    public function edit(Medicine $medicine)
    {
        $categories = MedicineCategory::orderBy('name')->get();

        return view('admin.medicines.form', [
            'medicine' => $medicine,
            'categories' => $categories,
            'editing' => true,
        ]);
    }

    /**
     * Cập nhật thông tin thuốc
     */
    public function update(Request $request, Medicine $medicine)
    {
        $validated = $request->validate([
            'category_id' => 'required|exists:medicine_categories,id',
            'code' => ['required', 'string', 'max:50', Rule::unique('medicines', 'code')->ignore($medicine->id)],
            'name' => 'required|string|max:255',
            'active_ingredient' => 'required|string|max:255',
            'concentration' => 'nullable|string|max:100',
            'dosage_form' => 'nullable|string|max:100',
            'default_unit' => 'required|string|max:30',
            'manufacturer' => 'nullable|string|max:255',
            'usage_instructions' => 'nullable|string|max:5000',
            'side_effects' => 'nullable|string|max:5000',
            'contraindications' => 'nullable|string|max:5000',
        ]);

        $medicine->update($validated);

        return redirect()->route('admin.medicines.index')
            ->with('success', "Đã cập nhật thông tin thuốc \"{$medicine->name}\" thành công!");
    }

    /**
     * Xóa thuốc khỏi Master Data
     */
    public function destroy(Medicine $medicine)
    {
        $name = $medicine->name;
        $inCabinets = $medicine->cabinetItems()->count();

        $medicine->delete();

        $msg = "Đã xóa thuốc \"{$name}\" khỏi danh mục Master Data!";
        if ($inCabinets > 0) {
            $msg .= " (Các tủ thuốc gia đình có chứa thuốc này vẫn được bảo toàn với tư cách thuốc nhập tay).";
        }

        return redirect()->route('admin.medicines.index')->with('success', $msg);
    }
}

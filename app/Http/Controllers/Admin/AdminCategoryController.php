<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MedicineCategory;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AdminCategoryController extends Controller
{
    /**
     * Danh sách các nhóm dược lý
     */
    public function index(Request $request)
    {
        $search = trim((string) $request->query('q'));
        $type = $request->query('type'); // 'rx', 'otc'

        $query = MedicineCategory::withCount('medicines');

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        if ($type === 'rx') {
            $query->where('is_prescription_required', true);
        } elseif ($type === 'otc') {
            $query->where('is_prescription_required', false);
        }

        $categories = $query->orderBy('name')->paginate(15)->withQueryString();

        return view('admin.categories.index', compact('categories', 'search', 'type'));
    }

    /**
     * Thêm nhóm dược lý mới
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100|unique:medicine_categories,name',
            'description' => 'nullable|string|max:500',
            'is_prescription_required' => 'nullable|boolean',
        ], [
            'name.required' => 'Vui lòng nhập tên nhóm dược lý.',
            'name.unique' => 'Tên nhóm dược lý này đã tồn tại.',
        ]);

        $validated['is_prescription_required'] = $request->boolean('is_prescription_required');

        $category = MedicineCategory::create($validated);

        return redirect()->route('admin.categories.index')
            ->with('success', "Đã thêm nhóm dược lý \"{$category->name}\" thành công!");
    }

    /**
     * Cập nhật nhóm dược lý
     */
    public function update(Request $request, MedicineCategory $category)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('medicine_categories', 'name')->ignore($category->id)],
            'description' => 'nullable|string|max:500',
            'is_prescription_required' => 'nullable|boolean',
        ]);

        $validated['is_prescription_required'] = $request->boolean('is_prescription_required');

        $category->update($validated);

        return redirect()->route('admin.categories.index')
            ->with('success', "Đã cập nhật thông tin nhóm dược lý \"{$category->name}\"!");
    }

    /**
     * Xóa nhóm dược lý
     */
    public function destroy(MedicineCategory $category)
    {
        if ($category->medicines()->count() > 0) {
            return back()->with('warning', "Không thể xóa nhóm \"{$category->name}\" vì vẫn còn {$category->medicines()->count()} loại thuốc đang trực thuộc nhóm này!");
        }

        $name = $category->name;
        $category->delete();

        return redirect()->route('admin.categories.index')
            ->with('success', "Đã xóa nhóm dược lý \"{$name}\" thành công!");
    }
}

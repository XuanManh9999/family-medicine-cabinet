<?php

namespace App\Http\Controllers;

use App\Models\Family;
use App\Models\FamilyMember;
use App\Models\MedicineExpense;
use App\Models\TreatmentCourse;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MedicineExpenseController extends Controller
{
    /**
     * Danh sách hóa đơn, biểu đồ phân tích chi phí y tế & viện phí gia đình
     */
    public function index(Request $request)
    {
        $family = $this->ensureFamily($request);

        $selectedYear = (int)$request->query('year', Carbon::now()->year);
        $selectedMonth = $request->query('month');
        $memberId = $request->query('member_id');
        $courseId = $request->query('treatment_course_id');
        $search = trim((string)$request->query('q'));

        $query = $family->expenses()
            ->with(['familyMember', 'treatmentCourse']);

        if ($selectedYear) {
            $query->whereYear('purchase_date', $selectedYear);
        }

        if ($selectedMonth && in_array((int)$selectedMonth, range(1, 12))) {
            $query->whereMonth('purchase_date', (int)$selectedMonth);
        }

        if ($memberId) {
            if ($memberId === 'general') {
                $query->whereNull('family_member_id');
            } else {
                $query->where('family_member_id', $memberId);
            }
        }

        if ($courseId) {
            $query->where('treatment_course_id', $courseId);
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('notes', 'like', "%{$search}%");
            });
        }

        $expenses = $query->orderByDesc('purchase_date')
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        // 1. Thống kê KPI tổng quan & nạp dữ liệu chi phí năm một lần (Tối ưu tránh N+1 Query)
        $totalAll = (float)$family->expenses()->sum('amount');
        $yearExpenses = $family->expenses()
            ->whereYear('purchase_date', $selectedYear)
            ->get(['amount', 'purchase_date', 'family_member_id']);

        $totalYear = (float)$yearExpenses->sum('amount');
        $currentMonth = (int)Carbon::now()->month;
        $totalMonth = (float)$yearExpenses->filter(function ($exp) use ($currentMonth) {
            return (int)Carbon::parse($exp->purchase_date)->month === $currentMonth;
        })->sum('amount');
        $monthsCount = ($selectedYear === (int)Carbon::now()->year) ? max(1, $currentMonth) : 12;
        $avgMonthly = $totalYear > 0 ? round($totalYear / $monthsCount) : 0;

        // 2. Dữ liệu Biểu đồ Chi phí theo 12 tháng (Monthly Trend Chart)
        $monthlyData = array_fill_keys(range(1, 12), 0.0);
        foreach ($yearExpenses as $exp) {
            $m = (int)Carbon::parse($exp->purchase_date)->month;
            if (isset($monthlyData[$m])) {
                $monthlyData[$m] += (float)$exp->amount;
            }
        }

        // 3. Dữ liệu Biểu đồ Cơ cấu Chi phí theo Thành viên (Member Distribution Doughnut Chart)
        $memberExpenses = [];
        $members = $family->members()->orderBy('name')->get();
        $byMember = $yearExpenses->whereNotNull('family_member_id')->groupBy('family_member_id');

        foreach ($members as $mem) {
            $sum = (float)($byMember->get($mem->id)?->sum('amount') ?? 0);
            if ($sum > 0) {
                $memberExpenses[] = [
                    'name' => $mem->name,
                    'amount' => $sum,
                ];
            }
        }

        // Chi phí chung tủ thuốc (chưa gắn riêng thành viên nào)
        $generalSum = (float)$yearExpenses->whereNull('family_member_id')->sum('amount');
        if ($generalSum > 0) {
            $memberExpenses[] = [
                'name' => 'Chi phí chung (Tủ thuốc gia đình)',
                'amount' => $generalSum,
            ];
        }

        $treatmentCourses = $family->treatmentCourses()->orderByDesc('created_at')->get(['id', 'title']);

        return view('expenses.index', compact(
            'expenses',
            'totalAll',
            'totalYear',
            'totalMonth',
            'avgMonthly',
            'monthlyData',
            'memberExpenses',
            'selectedYear',
            'selectedMonth',
            'memberId',
            'courseId',
            'members',
            'treatmentCourses'
        ));
    }

    /**
     * Form thêm khoản chi tiêu / hóa đơn thuốc mới
     */
    public function create(Request $request)
    {
        $family = $this->ensureFamily($request);

        $expense = new MedicineExpense([
            'purchase_date' => Carbon::today(),
            'amount' => 0,
        ]);

        $members = $family->members()->orderBy('name')->get();
        $treatmentCourses = $family->treatmentCourses()->orderByDesc('created_at')->get();

        $preselectedMemberId = $request->query('member_id');
        $preselectedCourseId = $request->query('treatment_course_id');

        return view('expenses.form', [
            'expense' => $expense,
            'editing' => false,
            'members' => $members,
            'treatmentCourses' => $treatmentCourses,
            'preselectedMemberId' => $preselectedMemberId,
            'preselectedCourseId' => $preselectedCourseId,
        ]);
    }

    /**
     * Lưu khoản chi phí mới
     */
    public function store(Request $request)
    {
        $family = $this->ensureFamily($request);

        $validated = $this->validateExpense($request, $family->id);

        $imagePath = null;
        if ($request->hasFile('receipt_image')) {
            $imagePath = $request->file('receipt_image')->store('receipts', 'public');
        }

        $expense = MedicineExpense::create([
            'family_id' => $family->id,
            'family_member_id' => $validated['family_member_id'] ?? null,
            'treatment_course_id' => $validated['treatment_course_id'] ?? null,
            'title' => $validated['title'],
            'amount' => $validated['amount'],
            'purchase_date' => $validated['purchase_date'],
            'receipt_image_path' => $imagePath,
            'notes' => $validated['notes'] ?? null,
        ]);

        return redirect()->route('expenses.index')
            ->with('success', "Đã ghi nhận chi phí thành công: {$expense->title} (" . number_format($expense->amount, 0, ',', '.') . " đ)!");
    }

    /**
     * Form chỉnh sửa hóa đơn chi phí
     */
    public function edit(Request $request, MedicineExpense $expense)
    {
        $this->authorizeExpense($request, $expense);

        $family = $request->user()->family;
        $members = $family->members()->orderBy('name')->get();
        $treatmentCourses = $family->treatmentCourses()->orderByDesc('created_at')->get();

        return view('expenses.form', [
            'expense' => $expense,
            'editing' => true,
            'members' => $members,
            'treatmentCourses' => $treatmentCourses,
            'preselectedMemberId' => $expense->family_member_id,
            'preselectedCourseId' => $expense->treatment_course_id,
        ]);
    }

    /**
     * Cập nhật hóa đơn chi phí
     */
    public function update(Request $request, MedicineExpense $expense)
    {
        $this->authorizeExpense($request, $expense);
        $family = $request->user()->family;

        $validated = $this->validateExpense($request, $family->id);

        if ($request->hasFile('receipt_image')) {
            if ($expense->receipt_image_path) {
                Storage::disk('public')->delete($expense->receipt_image_path);
            }
            $expense->receipt_image_path = $request->file('receipt_image')->store('receipts', 'public');
        }

        $expense->update([
            'family_member_id' => $validated['family_member_id'] ?? null,
            'treatment_course_id' => $validated['treatment_course_id'] ?? null,
            'title' => $validated['title'],
            'amount' => $validated['amount'],
            'purchase_date' => $validated['purchase_date'],
            'notes' => $validated['notes'] ?? null,
        ]);

        return redirect()->route('expenses.index')
            ->with('success', "Đã cập nhật chi phí: {$expense->title}!");
    }

    /**
     * Xóa hóa đơn chi phí
     */
    public function destroy(Request $request, MedicineExpense $expense)
    {
        $this->authorizeExpense($request, $expense);

        if ($expense->receipt_image_path) {
            Storage::disk('public')->delete($expense->receipt_image_path);
        }

        $expense->delete();

        return redirect()->route('expenses.index')
            ->with('success', 'Đã xóa khoản chi phí thành công!');
    }

    /**
     * Xuất danh sách chi phí ra file Excel CSV có UTF-8 BOM
     */
    public function exportCsv(Request $request): StreamedResponse
    {
        $family = $this->ensureFamily($request);

        $expenses = $family->expenses()
            ->with(['familyMember', 'treatmentCourse'])
            ->orderByDesc('purchase_date')
            ->get();

        $fileName = 'So_Chi_Tieu_Y_Te_Gia_Dinh_' . date('Ymd_His') . '.csv';

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$fileName}\"",
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0',
        ];

        return response()->stream(function () use ($expenses) {
            $handle = fopen('php://output', 'w');
            // Chèn UTF-8 BOM để Excel hiển thị đúng dấu tiếng Việt
            fprintf($handle, chr(0xEF).chr(0xBB).chr(0xBF));

            fputcsv($handle, [
                'STT',
                'Ngày chi tiêu',
                'Nội dung hóa đơn / Khoản chi',
                'Số tiền (VNĐ)',
                'Thành viên',
                'Đợt điều trị / Đơn thuốc liên kết',
                'Ghi chú',
            ]);

            foreach ($expenses as $idx => $exp) {
                fputcsv($handle, [
                    $idx + 1,
                    $exp->purchase_date ? $exp->purchase_date->format('d/m/Y') : '',
                    $exp->title,
                    $exp->amount,
                    $exp->familyMember?->name ?? 'Chi phí chung',
                    $exp->treatmentCourse?->title ?? 'Không',
                    $exp->notes ?? '',
                ]);
            }

            fclose($handle);
        }, 200, $headers);
    }

    /**
     * Đảm bảo người dùng thuộc gia đình
     */
    protected function ensureFamily(Request $request): Family
    {
        $family = $request->user()->family;
        if (!$family) {
            abort(403, 'Bạn chưa thuộc về gia đình nào.');
        }
        return $family;
    }

    /**
     * Kiểm tra quyền sở hữu khoản chi phí
     */
    protected function authorizeExpense(Request $request, MedicineExpense $expense): void
    {
        $family = $this->ensureFamily($request);
        if ($expense->family_id !== $family->id) {
            abort(403, 'Bạn không có quyền truy cập khoản chi phí này.');
        }
    }

    /**
     * Validate dữ liệu khoản chi phí
     */
    protected function validateExpense(Request $request, int $familyId): array
    {
        return $request->validate([
            'title' => 'required|string|max:255',
            'amount' => 'required|numeric|min:0',
            'purchase_date' => 'required|date',
            'family_member_id' => "nullable|exists:family_members,id,family_id,{$familyId}",
            'treatment_course_id' => "nullable|exists:treatment_courses,id,family_id,{$familyId}",
            'receipt_image' => 'nullable|file|mimes:jpeg,png,jpg,webp|max:10240',
            'notes' => 'nullable|string|max:1000',
        ], [
            'title.required' => 'Vui lòng nhập tên nội dung hóa đơn hoặc khoản chi thuốc.',
            'amount.required' => 'Vui lòng nhập số tiền chi tiêu.',
            'amount.min' => 'Số tiền chi tiêu phải lớn hơn hoặc bằng 0.',
            'purchase_date.required' => 'Vui lòng chọn ngày chi tiêu.',
        ]);
    }
}

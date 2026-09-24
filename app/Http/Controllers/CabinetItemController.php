<?php

namespace App\Http\Controllers;

use App\Models\CabinetItem;
use App\Models\Medicine;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Validator;

class CabinetItemController extends Controller
{
    public function index(Request $request)
    {
        $family = $request->user()->family;
        abort_unless($family, 403, 'Tài khoản chưa thuộc hộ gia đình.');

        $itemsQuery = $family->cabinetItems()->with('medicine.category');

        if ($search = trim((string) $request->query('q'))) {
            $itemsQuery->where(function ($query) use ($search) {
                $query->where('custom_name', 'like', "%{$search}%")
                    ->orWhere('batch_number', 'like', "%{$search}%")
                    ->orWhere('storage_location', 'like', "%{$search}%")
                    ->orWhereHas('medicine', fn ($medicine) => $medicine->where('name', 'like', "%{$search}%")
                        ->orWhere('active_ingredient', 'like', "%{$search}%"));
            });
        }

        if ($location = trim((string) $request->query('location'))) {
            $itemsQuery->where('storage_location', $location);
        }

        if ($categoryId = $request->query('category_id')) {
            $itemsQuery->whereHas('medicine', fn ($medicine) => $medicine->where('category_id', $categoryId));
        }

        $filter = $request->query('status', 'all');
        match ($filter) {
            'expired' => $itemsQuery->whereDate('expiry_date', '<', today()),
            'near_expiry' => $itemsQuery->whereBetween('expiry_date', [today(), today()->addDays(30)]),
            'low_stock' => $itemsQuery->where('quantity', '>', 0)->where('quantity', '<=', 5),
            'out_of_stock' => $itemsQuery->where('quantity', '<=', 0),
            'available' => $itemsQuery->whereDate('expiry_date', '>', today()->addDays(30))->where('quantity', '>', 0),
            default => null,
        };

        $sort = $request->query('sort', 'expiry_asc');
        match ($sort) {
            'expiry_desc' => $itemsQuery->orderByDesc('expiry_date')->orderBy('custom_name'),
            'quantity_asc' => $itemsQuery->orderBy('quantity')->orderBy('expiry_date'),
            'quantity_desc' => $itemsQuery->orderByDesc('quantity')->orderBy('expiry_date'),
            'name_asc' => $itemsQuery->orderBy('custom_name'),
            default => $itemsQuery->orderBy('expiry_date')->orderBy('custom_name'),
        };

        $items = $itemsQuery->paginate(15)->withQueryString();

        $stats = [
            'total' => $family->cabinetItems()->count(),
            'available' => $family->cabinetItems()->whereDate('expiry_date', '>', today()->addDays(30))->where('quantity', '>', 0)->count(),
            'expired' => $family->cabinetItems()->whereDate('expiry_date', '<', today())->count(),
            'near_expiry' => $family->cabinetItems()->whereBetween('expiry_date', [today(), today()->addDays(30)])->count(),
            'low_stock' => $family->cabinetItems()->where('quantity', '>', 0)->where('quantity', '<=', 5)->count(),
            'out_of_stock' => $family->cabinetItems()->where('quantity', '<=', 0)->count(),
            'total_value' => $family->cabinetItems()->selectRaw('SUM(quantity * COALESCE(price_per_unit, 0)) as val')->value('val') ?? 0,
        ];

        $locations = $family->cabinetItems()
            ->whereNotNull('storage_location')
            ->where('storage_location', '!=', '')
            ->distinct()
            ->pluck('storage_location')
            ->sort()
            ->values();

        $categories = \App\Models\MedicineCategory::orderBy('name')->get();

        // Danh sách thuốc cần bổ sung / mua mới (hết hàng, tồn ít <= 5, hoặc đã quá hạn cần thay thế)
        $shoppingItems = $family->cabinetItems()
            ->with('medicine.category')
            ->where(function ($q) {
                $q->where('quantity', '<=', 5)
                  ->orWhereDate('expiry_date', '<', today());
            })
            ->orderBy('quantity')
            ->orderBy('expiry_date')
            ->get();

        return view('cabinet.index', compact('items', 'stats', 'filter', 'locations', 'categories', 'sort', 'shoppingItems'));
    }

    public function create(Request $request)
    {
        $this->ensureFamily($request);
        return view('cabinet.form', ['item' => new CabinetItem(), 'medicines' => Medicine::orderBy('name')->get(), 'editing' => false]);
    }

    public function store(Request $request)
    {
        $family = $this->ensureAdmin($request);
        $data = $this->validatedData($request);
        $data['family_id'] = $family->id;
        $data['status'] = $this->statusFor($data['expiry_date'], $data['quantity']);
        CabinetItem::create($data);

        return redirect()->route('cabinet.index')->with('success', 'Đã thêm thuốc vào tủ.');
    }

    public function edit(Request $request, CabinetItem $cabinetItem)
    {
        $this->authorizeItem($request, $cabinetItem);
        return view('cabinet.form', ['item' => $cabinetItem, 'medicines' => Medicine::orderBy('name')->get(), 'editing' => true]);
    }

    public function update(Request $request, CabinetItem $cabinetItem)
    {
        $family = $this->ensureAdmin($request);
        abort_unless($cabinetItem->family_id === $family->id, 404);
        $data = $this->validatedData($request);
        $data['status'] = $this->statusFor($data['expiry_date'], $data['quantity']);
        $cabinetItem->update($data);

        return redirect()->route('cabinet.index')->with('success', 'Đã cập nhật thông tin thuốc.');
    }

    public function updateQuantity(Request $request, CabinetItem $cabinetItem)
    {
        $family = $this->ensureAdmin($request);
        abort_unless($cabinetItem->family_id === $family->id, 404);
        $data = $request->validate(['quantity' => ['required', 'numeric', 'min:0', 'max:999999.99']]);
        $cabinetItem->update([
            'quantity' => $data['quantity'],
            'status' => $this->statusFor($cabinetItem->expiry_date, $data['quantity']),
        ]);

        return back()->with('success', 'Đã cập nhật số lượng tồn.');
    }

    public function destroy(Request $request, CabinetItem $cabinetItem)
    {
        $family = $this->ensureAdmin($request);
        abort_unless($cabinetItem->family_id === $family->id, 404);
        $cabinetItem->delete();

        return redirect()->route('cabinet.index')->with('success', 'Đã xóa lô thuốc khỏi tủ.');
    }

    /**
     * Xuất danh sách tủ thuốc ra định dạng Excel (CSV UTF-8 BOM chuẩn Excel)
     */
    public function exportExcel(Request $request)
    {
        $family = $this->ensureFamily($request);
        $itemsQuery = $family->cabinetItems()->with('medicine.category');

        if ($search = trim((string) $request->query('q'))) {
            $itemsQuery->where(function ($query) use ($search) {
                $query->where('custom_name', 'like', "%{$search}%")
                    ->orWhere('batch_number', 'like', "%{$search}%")
                    ->orWhere('storage_location', 'like', "%{$search}%")
                    ->orWhereHas('medicine', fn ($medicine) => $medicine->where('name', 'like', "%{$search}%")
                        ->orWhere('active_ingredient', 'like', "%{$search}%"));
            });
        }

        if ($location = trim((string) $request->query('location'))) {
            $itemsQuery->where('storage_location', $location);
        }

        if ($categoryId = $request->query('category_id')) {
            $itemsQuery->whereHas('medicine', fn ($medicine) => $medicine->where('category_id', $categoryId));
        }

        $filter = $request->query('status', 'all');
        match ($filter) {
            'expired' => $itemsQuery->whereDate('expiry_date', '<', today()),
            'near_expiry' => $itemsQuery->whereBetween('expiry_date', [today(), today()->addDays(30)]),
            'low_stock' => $itemsQuery->where('quantity', '>', 0)->where('quantity', '<=', 5),
            'out_of_stock' => $itemsQuery->where('quantity', '<=', 0),
            'available' => $itemsQuery->whereDate('expiry_date', '>', today()->addDays(30))->where('quantity', '>', 0),
            default => null,
        };

        $items = $itemsQuery->orderBy('expiry_date')->orderBy('custom_name')->get();
        $filename = 'tu_thuoc_gia_dinh_' . now()->format('Ymd_His') . '.csv';

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0',
        ];

        return response()->stream(function () use ($items) {
            $handle = fopen('php://output', 'w');
            // Ghi UTF-8 BOM để Excel hiển thị tiếng Việt có dấu chuẩn 100%
            fwrite($handle, "\xEF\xBB\xBF");

            // Header hàng cột
            fputcsv($handle, [
                'STT',
                'Tên thuốc / Biệt dược',
                'Hoạt chất',
                'Hàm lượng / Nồng độ',
                'Nhóm dược lý',
                'Số lô sản xuất',
                'Ngày sản xuất',
                'Hạn sử dụng',
                'Thời gian còn lại',
                'Số lượng tồn',
                'Đơn vị tính',
                'Đơn giá (VNĐ)',
                'Tổng giá trị (VNĐ)',
                'Vị trí lưu trữ',
                'Tình trạng hạn dùng',
                'Tình trạng tồn kho',
                'Ghi chú bảo quản',
            ]);

            foreach ($items as $idx => $item) {
                $expiryStatus = $item->is_expired ? 'Đã quá hạn' : ($item->is_near_expiry ? 'Cận hạn (<30d)' : 'Còn hạn an toàn');
                $stockStatus = $item->is_out_of_stock ? 'Hết hàng' : ($item->is_low_stock ? 'Tồn ít (≤5)' : 'Đủ dùng');
                $totalVal = (float)$item->quantity * (float)($item->price_per_unit ?? 0);

                fputcsv($handle, [
                    $idx + 1,
                    $item->display_name,
                    $item->medicine?->active_ingredient ?: '—',
                    $item->medicine?->concentration ?: '—',
                    $item->medicine?->category?->name ?: 'Dược phẩm dùng ngoài / Khác',
                    $item->batch_number ?: '—',
                    $item->manufacturing_date ? $item->manufacturing_date->format('d/m/Y') : '—',
                    $item->expiry_date ? $item->expiry_date->format('d/m/Y') : '—',
                    $item->expiry_remaining_text,
                    rtrim(rtrim((string)$item->quantity, '0'), '.'),
                    $item->unit,
                    $item->price_per_unit ? (float)$item->price_per_unit : 0,
                    $totalVal,
                    $item->storage_location ?: 'Tủ thuốc chính',
                    $expiryStatus,
                    $stockStatus,
                    $item->notes ?: '',
                ]);
            }

            fclose($handle);
        }, 200, $headers);
    }

    /**
     * Tải file mẫu Excel (.csv chuẩn UTF-8) để nhập nhiều thuốc
     */
    public function sampleExcel()
    {
        $filename = 'mau_nhap_lieu_tu_thuoc.csv';
        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        return response()->stream(function () {
            $handle = fopen('php://output', 'w');
            fwrite($handle, "\xEF\xBB\xBF");

            // Header hàng cột
            fputcsv($handle, [
                'Tên thuốc',
                'Hoạt chất',
                'Số lô',
                'Ngày sản xuất (YYYY-MM-DD)',
                'Hạn sử dụng (YYYY-MM-DD)',
                'Số lượng',
                'Đơn vị tính',
                'Đơn giá (VNĐ)',
                'Vị trí lưu trữ',
                'Ghi chú sử dụng',
            ]);

            // Dữ liệu mẫu minh họa
            fputcsv($handle, [
                'Panadol Extra Đỏ Giảm Đau',
                'Paracetamol 500mg, Caffeine 65mg',
                'VN-2026-01',
                '2026-01-15',
                '2028-06-30',
                '24',
                'viên',
                '2500',
                'Ngăn 1 - Tủ phòng khách',
                'Mua sẵn dự phòng hạ sốt đau đầu',
            ]);

            fputcsv($handle, [
                'Nước muối súc họng 500ml',
                'Natri Clorid 0.9%',
                'NAC-2026',
                '2026-02-01',
                '2027-12-31',
                '3',
                'chai',
                '10000',
                'Kệ nhà tắm tầng 2',
                'Súc họng sáng và tối',
            ]);

            fputcsv($handle, [
                'Băng dán Urgo chống nước',
                'Băng gạc PU chống thấm',
                'URG-902',
                '2026-03-10',
                '2028-09-30',
                '5',
                'hộp',
                '35000',
                'Hộp sơ cứu gia đình',
                'Dán vết trầy xước',
            ]);

            fclose($handle);
        }, 200, $headers);
    }

    /**
     * Nhập nhiều thuốc cùng lúc từ file Excel / CSV
     */
    public function importExcel(Request $request)
    {
        $family = $this->ensureAdmin($request);

        $request->validate([
            'excel_file' => ['required', 'file', 'max:5120'], // max 5MB
        ], [
            'excel_file.required' => 'Vui lòng chọn file Excel hoặc CSV để tải lên.',
            'excel_file.max' => 'Dung lượng file tối đa là 5MB.',
        ]);

        $file = $request->file('excel_file');
        $extension = strtolower($file->getClientOriginalExtension());

        if (!in_array($extension, ['csv', 'txt'])) {
            return back()->with('danger', 'Hệ thống hỗ trợ file .csv (file bảng tính Excel lưu dưới định dạng CSV UTF-8). Vui lòng sử dụng file mẫu chuẩn của hệ thống.');
        }

        $content = file_get_contents($file->getRealPath());
        if ($content === false || trim($content) === '') {
            return back()->with('danger', 'File tải lên trống, vui lòng kiểm tra lại.');
        }

        // Xóa BOM UTF-8 nếu có
        $content = preg_replace('/^\xEF\xBB\xBF/', '', $content);
        $lines = preg_split('/\r\n|\r|\n/', trim($content));

        if (count($lines) < 2) {
            return back()->with('danger', 'File phải có ít nhất 1 dòng tiêu đề và 1 dòng dữ liệu.');
        }

        // Tự động nhận diện phân cách: dấu phẩy, chấm phẩy hoặc tab
        $firstLine = $lines[0];
        $delimiter = ',';
        if (substr_count($firstLine, ';') > substr_count($firstLine, ',')) {
            $delimiter = ';';
        } elseif (substr_count($firstLine, "\t") > substr_count($firstLine, ',')) {
            $delimiter = "\t";
        }

        $headerCols = str_getcsv($lines[0], $delimiter);
        $headerMap = [];
        foreach ($headerCols as $colIdx => $colName) {
            $cleanName = mb_strtolower(trim($colName), 'UTF-8');
            if (str_contains($cleanName, 'tên thuốc') || str_contains($cleanName, 'custom_name') || str_contains($cleanName, 'tên')) {
                $headerMap['name'] = $colIdx;
            } elseif (str_contains($cleanName, 'hoạt chất') || str_contains($cleanName, 'ingredient')) {
                $headerMap['ingredient'] = $colIdx;
            } elseif (str_contains($cleanName, 'lô') || str_contains($cleanName, 'batch')) {
                $headerMap['batch'] = $colIdx;
            } elseif (str_contains($cleanName, 'sản xuất') || str_contains($cleanName, 'mfg') || str_contains($cleanName, 'nsx')) {
                $headerMap['mfg'] = $colIdx;
            } elseif (str_contains($cleanName, 'hạn') || str_contains($cleanName, 'exp') || str_contains($cleanName, 'hsd')) {
                $headerMap['exp'] = $colIdx;
            } elseif (str_contains($cleanName, 'lượng') || str_contains($cleanName, 'qty') || str_contains($cleanName, 'số lượng')) {
                $headerMap['qty'] = $colIdx;
            } elseif (str_contains($cleanName, 'đơn vị') || str_contains($cleanName, 'unit') || str_contains($cleanName, 'đvt')) {
                $headerMap['unit'] = $colIdx;
            } elseif (str_contains($cleanName, 'giá') || str_contains($cleanName, 'price') || str_contains($cleanName, 'đơn giá')) {
                $headerMap['price'] = $colIdx;
            } elseif (str_contains($cleanName, 'vị trí') || str_contains($cleanName, 'location') || str_contains($cleanName, 'ngăn')) {
                $headerMap['location'] = $colIdx;
            } elseif (str_contains($cleanName, 'ghi chú') || str_contains($cleanName, 'note') || str_contains($cleanName, 'lưu ý')) {
                $headerMap['notes'] = $colIdx;
            }
        }

        // Mặc định vị trí theo mẫu chuẩn nếu không khớp tên cột
        $colNameIdx = $headerMap['name'] ?? 0;
        $colIngIdx = $headerMap['ingredient'] ?? 1;
        $colBatchIdx = $headerMap['batch'] ?? 2;
        $colMfgIdx = $headerMap['mfg'] ?? 3;
        $colExpIdx = $headerMap['exp'] ?? 4;
        $colQtyIdx = $headerMap['qty'] ?? 5;
        $colUnitIdx = $headerMap['unit'] ?? 6;
        $colPriceIdx = $headerMap['price'] ?? 7;
        $colLocIdx = $headerMap['location'] ?? 8;
        $colNotesIdx = $headerMap['notes'] ?? 9;

        $imported = 0;
        $errors = [];

        // Đọc sẵn danh mục thuốc để map
        $allMedicines = Medicine::all();

        for ($i = 1; $i < count($lines); $i++) {
            $line = trim($lines[$i]);
            if ($line === '') continue;

            $row = str_getcsv($line, $delimiter);
            $customName = trim($row[$colNameIdx] ?? '');
            if ($customName === '') {
                $errors[] = "Dòng " . ($i + 1) . ": Bỏ qua vì thiếu Tên thuốc.";
                continue;
            }

            // Xử lý hạn sử dụng
            $rawExp = trim($row[$colExpIdx] ?? '');
            $expiryDate = null;
            if ($rawExp) {
                try {
                    if (preg_match('/^(\d{1,2})[\/\-](\d{1,2})[\/\-](\d{4})$/', $rawExp, $m)) {
                        $expiryDate = Carbon::createFromDate((int)$m[3], (int)$m[2], (int)$m[1])->toDateString();
                    } else {
                        $expiryDate = Carbon::parse($rawExp)->toDateString();
                    }
                } catch (\Throwable $e) {
                    $errors[] = "Dòng " . ($i + 1) . " ('{$customName}'): Hạn sử dụng '{$rawExp}' không đúng định dạng (YYYY-MM-DD hoặc DD/MM/YYYY).";
                    continue;
                }
            } else {
                $errors[] = "Dòng " . ($i + 1) . " ('{$customName}'): Thiếu hạn sử dụng.";
                continue;
            }

            // Xử lý ngày sản xuất
            $rawMfg = trim($row[$colMfgIdx] ?? '');
            $mfgDate = null;
            if ($rawMfg) {
                try {
                    if (preg_match('/^(\d{1,2})[\/\-](\d{1,2})[\/\-](\d{4})$/', $rawMfg, $m)) {
                        $mfgDate = Carbon::createFromDate((int)$m[3], (int)$m[2], (int)$m[1])->toDateString();
                    } else {
                        $mfgDate = Carbon::parse($rawMfg)->toDateString();
                    }
                } catch (\Throwable $e) {
                    $mfgDate = null;
                }
            }

            $quantity = isset($row[$colQtyIdx]) && is_numeric(trim($row[$colQtyIdx])) ? (float)trim($row[$colQtyIdx]) : 1;
            $unit = trim($row[$colUnitIdx] ?? '') ?: 'viên';
            $price = isset($row[$colPriceIdx]) && is_numeric(trim($row[$colPriceIdx])) ? (float)trim($row[$colPriceIdx]) : null;
            $batch = trim($row[$colBatchIdx] ?? '') ?: null;
            $location = trim($row[$colLocIdx] ?? '') ?: 'Tủ thuốc gia đình';
            $notes = trim($row[$colNotesIdx] ?? '') ?: null;

            // Tìm thuốc tương ứng trong danh mục nếu có
            $matchedMed = $allMedicines->first(function ($med) use ($customName) {
                return mb_stripos($customName, $med->name) !== false || mb_stripos($med->name, $customName) !== false;
            });

            // Tạo bản ghi thuốc vào tủ
            CabinetItem::create([
                'family_id' => $family->id,
                'medicine_id' => $matchedMed?->id,
                'custom_name' => $customName,
                'batch_number' => $batch,
                'manufacturing_date' => $mfgDate,
                'expiry_date' => $expiryDate,
                'quantity' => $quantity,
                'unit' => $unit,
                'storage_location' => $location,
                'price_per_unit' => $price,
                'status' => $this->statusFor($expiryDate, $quantity),
                'notes' => $notes,
            ]);

            $imported++;
        }

        $message = "Đã nhập thành công {$imported} loại thuốc vào tủ thuốc gia đình!";
        if (!empty($errors)) {
            $message .= " (Gặp " . count($errors) . " dòng lỗi: " . implode('; ', array_slice($errors, 0, 3)) . (count($errors) > 3 ? '...' : '') . ")";
            return redirect()->route('cabinet.index')->with('warning', $message);
        }

        return redirect()->route('cabinet.index')->with('success', $message);
    }

    private function validatedData(Request $request): array
    {
        $validator = Validator::make($request->all(), [
            'medicine_id' => ['nullable', 'integer', Rule::exists('medicines', 'id')],
            'custom_name' => ['required', 'string', 'max:255'],
            'batch_number' => ['nullable', 'string', 'max:255'],
            'manufacturing_date' => ['nullable', 'date', 'before_or_equal:today'],
            'expiry_date' => ['required', 'date'],
            'quantity' => ['required', 'numeric', 'min:0', 'max:999999.99'],
            'unit' => ['required', 'string', 'max:30'],
            'storage_location' => ['nullable', 'string', 'max:255'],
            'price_per_unit' => ['nullable', 'numeric', 'min:0', 'max:999999999.99'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ], [
            'manufacturing_date.before_or_equal' => 'Ngày sản xuất không thể ở tương lai.',
        ]);

        $validator->after(function ($validator) use ($request) {
            $manufactured = $request->input('manufacturing_date');
            $expires = $request->input('expiry_date');
            if ($manufactured && $expires && strtotime($expires) < strtotime($manufactured)) {
                $validator->errors()->add('expiry_date', 'Hạn sử dụng phải bằng hoặc sau ngày sản xuất.');
            }
        });

        return $validator->validate();
    }

    private function statusFor(string|Carbon $expiryDate, int|float|string $quantity): string
    {
        $expiry = Carbon::parse($expiryDate)->startOfDay();
        if ($expiry->lt(today())) return 'qua_han';
        if ($expiry->lte(today()->addDays(30))) return 'sap_het_han';
        return (float) $quantity <= 0 ? 'het_hang' : 'con_han';
    }

    private function ensureFamily(Request $request)
    {
        $family = $request->user()->family;
        abort_unless($family, 403, 'Tài khoản chưa thuộc hộ gia đình.');
        return $family;
    }

    private function ensureAdmin(Request $request)
    {
        $family = $this->ensureFamily($request);
        abort_unless($request->user()->isFamilyAdmin(), 403, 'Chỉ chủ hộ mới có thể thay đổi tủ thuốc.');
        return $family;
    }

    private function authorizeItem(Request $request, CabinetItem $item): void
    {
        abort_unless($item->family_id === $this->ensureFamily($request)->id, 404);
    }
}

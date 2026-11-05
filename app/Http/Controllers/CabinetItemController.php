<?php

namespace App\Http\Controllers;

use App\Models\CabinetItem;
use App\Models\Family;
use App\Models\Medicine;
use App\Services\AlertScannerService;
use App\Support\CabinetItemValidator;
use App\Support\CsvSanitizer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class CabinetItemController extends Controller
{
    /** Số dòng dữ liệu tối đa cho mỗi lần nhập file */
    private const IMPORT_MAX_ROWS = 1000;

    public function index(Request $request)
    {
        $family = $request->user()->family;
        abort_unless($family, 403, 'Tài khoản chưa thuộc hộ gia đình.');

        $itemsQuery = $family->cabinetItems()->with('medicine.category');

        if ($search = $request->textQuery('q')) {
            $itemsQuery->where(function ($query) use ($search) {
                $query->where('custom_name', 'like', "%{$search}%")
                    ->orWhere('batch_number', 'like', "%{$search}%")
                    ->orWhere('storage_location', 'like', "%{$search}%")
                    ->orWhereHas('medicine', fn ($medicine) => $medicine->where('name', 'like', "%{$search}%")
                        ->orWhere('active_ingredient', 'like', "%{$search}%"));
            });
        }

        if ($location = $request->textQuery('location')) {
            $itemsQuery->where('storage_location', $location);
        }

        if ($categoryId = $request->query('category_id')) {
            $itemsQuery->whereHas('medicine', fn ($medicine) => $medicine->where('category_id', $categoryId));
        }

        $filter = $request->query('status', $request->query('filter', 'all'));
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
        $this->ensureAdmin($request);
        return view('cabinet.form', ['item' => new CabinetItem(), 'medicines' => Medicine::with('category')->orderBy('name')->get(), 'editing' => false]);
    }

    public function store(Request $request)
    {
        $family = $this->ensureAdmin($request);
        $data = $this->validatedData($request);
        $data['family_id'] = $family->id;
        $data['status'] = CabinetItem::computeStatus($data['expiry_date'], $data['quantity']);
        // Bác sĩ / Quản trị viên tự nhập thuốc vào tủ: chính họ là người phê duyệt
        CabinetItem::create(array_merge($data, [
            'approval_status' => CabinetItem::APPROVAL_APPROVED,
            'approved_by' => $request->user()->id,
            'approved_at' => now(),
        ]));
        $this->refreshAlerts($family);

        return redirect()->route('cabinet.index')->with('success', 'Đã thêm thuốc vào tủ thuốc gia đình.');
    }

    public function edit(Request $request, CabinetItem $cabinetItem)
    {
        $this->ensureAdmin($request);
        $this->authorizeItem($request, $cabinetItem);
        return view('cabinet.form', ['item' => $cabinetItem, 'medicines' => Medicine::with('category')->orderBy('name')->get(), 'editing' => true]);
    }

    public function update(Request $request, CabinetItem $cabinetItem)
    {
        $family = $this->ensureAdmin($request);
        abort_unless($cabinetItem->family_id === $family->id, 404);
        $data = $this->validatedData($request);
        $data['status'] = CabinetItem::computeStatus($data['expiry_date'], $data['quantity']);
        $cabinetItem->update($data);
        $this->refreshAlerts($family);

        return redirect()->route('cabinet.index')->with('success', 'Đã cập nhật thông tin thuốc.');
    }

    public function updateQuantity(Request $request, CabinetItem $cabinetItem)
    {
        $family = $this->ensureAdmin($request);
        abort_unless($cabinetItem->family_id === $family->id, 404);
        $data = $request->validate(['quantity' => ['required', 'numeric', 'min:0', 'max:999999.99']], [
            'quantity.required' => 'Vui lòng nhập số lượng tồn.',
            'quantity.numeric' => 'Số lượng tồn phải là số.',
            'quantity.min' => 'Số lượng tồn không được âm.',
            'quantity.max' => 'Số lượng tồn tối đa là 999.999,99.',
        ]);
        $cabinetItem->update([
            'quantity' => $data['quantity'],
            'status' => CabinetItem::computeStatus($cabinetItem->expiry_date, $data['quantity']),
        ]);
        $this->refreshAlerts($family);

        return back()->with('success', 'Đã cập nhật số lượng tồn.');
    }

    public function destroy(Request $request, CabinetItem $cabinetItem)
    {
        $family = $this->ensureAdmin($request);
        abort_unless($cabinetItem->family_id === $family->id, 404);
        // Cảnh báo gắn với lô được gỡ tự động qua sự kiện deleting của model
        $cabinetItem->delete();
        $this->refreshAlerts($family);

        return redirect()->route('cabinet.index')->with('success', 'Đã xóa lô thuốc khỏi tủ.');
    }

    /**
     * Xuất danh sách tủ thuốc ra định dạng Excel (CSV UTF-8 BOM chuẩn Excel)
     */
    public function exportExcel(Request $request)
    {
        $family = $this->ensureFamily($request);
        $itemsQuery = $family->cabinetItems()->with('medicine.category');

        if ($search = $request->textQuery('q')) {
            $itemsQuery->where(function ($query) use ($search) {
                $query->where('custom_name', 'like', "%{$search}%")
                    ->orWhere('batch_number', 'like', "%{$search}%")
                    ->orWhere('storage_location', 'like', "%{$search}%")
                    ->orWhereHas('medicine', fn ($medicine) => $medicine->where('name', 'like', "%{$search}%")
                        ->orWhere('active_ingredient', 'like', "%{$search}%"));
            });
        }

        if ($location = $request->textQuery('location')) {
            $itemsQuery->where('storage_location', $location);
        }

        if ($categoryId = $request->query('category_id')) {
            $itemsQuery->whereHas('medicine', fn ($medicine) => $medicine->where('category_id', $categoryId));
        }

        $filter = $request->query('status', $request->query('filter', 'all'));
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
            CsvSanitizer::writeRow($handle, [
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

                CsvSanitizer::writeRow($handle, [
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
                    $item->price_per_unit !== null ? (float)$item->price_per_unit : '',
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
            CsvSanitizer::writeRow($handle, [
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
            CsvSanitizer::writeRow($handle, [
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

            CsvSanitizer::writeRow($handle, [
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

            CsvSanitizer::writeRow($handle, [
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
            'excel_file' => ['required', 'file', 'max:5120', 'extensions:csv,txt'], // max 5MB
        ], [
            'excel_file.required' => 'Vui lòng chọn file Excel hoặc CSV để tải lên.',
            'excel_file.file' => 'File tải lên không hợp lệ.',
            'excel_file.max' => 'Dung lượng file tối đa là 5MB.',
            'excel_file.extensions' => 'Hệ thống hỗ trợ file .csv / .txt (file bảng tính Excel lưu dưới định dạng CSV UTF-8). Vui lòng sử dụng file mẫu chuẩn của hệ thống.',
        ]);

        $content = file_get_contents($request->file('excel_file')->getRealPath());
        if ($content === false || trim($content) === '') {
            return back()->with('danger', 'File tải lên trống, vui lòng kiểm tra lại.');
        }

        $content = $this->normalizeEncoding($content);
        if ($content === null) {
            return back()->with('danger', 'File không phải định dạng văn bản CSV (có thể là file .xlsx/.xls nhị phân). Vui lòng mở bằng Excel và "Lưu thành" CSV UTF-8 rồi tải lên lại.');
        }

        $delimiter = $this->detectDelimiter($content);
        $rows = $this->readCsvRows($content, $delimiter);

        if (count($rows) < 2) {
            return back()->with('danger', 'File phải có ít nhất 1 dòng tiêu đề và 1 dòng dữ liệu.');
        }
        if (count($rows) - 1 > self::IMPORT_MAX_ROWS) {
            return back()->with('danger', 'Mỗi lần chỉ nhập tối đa ' . self::IMPORT_MAX_ROWS . ' dòng dữ liệu. Vui lòng chia nhỏ file và thử lại.');
        }

        [$headerLine, $headerCols] = array_shift($rows);
        $headerMap = $this->mapImportHeaders($headerCols);
        if (!isset($headerMap['name'], $headerMap['exp'])) {
            return back()->with('danger', 'Không nhận diện được cột "Tên thuốc" và "Hạn sử dụng" ở dòng tiêu đề. Vui lòng sử dụng file mẫu chuẩn của hệ thống.');
        }

        // Đọc sẵn danh mục thuốc để liên kết chính xác theo tên chuẩn hóa hoặc mã thuốc
        $medicinesByName = [];
        $medicinesByCode = [];
        foreach (Medicine::all(['id', 'name', 'code']) as $med) {
            $medicinesByName[$this->normalizeKey($med->name)] ??= $med->id;
            if ($med->code) {
                $medicinesByCode[$this->normalizeKey($med->code)] ??= $med->id;
            }
        }

        $validRows = [];
        $errors = [];
        $skipped = 0;

        foreach ($rows as [$lineNo, $row]) {
            $cell = fn (string $field) => isset($headerMap[$field])
                ? trim(CsvSanitizer::uncell((string) ($row[$headerMap[$field]] ?? '')))
                : '';

            $customName = $cell('name');
            $rowLabel = "Dòng {$lineNo}" . ($customName !== '' ? " ('" . mb_strimwidth($customName, 0, 60, '...') . "')" : '');

            // Xử lý ngày tháng nghiêm ngặt (không tự "tràn" ngày như 31/02 => 03/03)
            $rawExp = $this->emptyPlaceholder($cell('exp'));
            $expiryDate = $rawExp === '' ? null : $this->parseStrictDate($rawExp);
            if ($rawExp !== '' && $expiryDate === null) {
                $errors[] = "{$rowLabel}: Hạn sử dụng '{$rawExp}' không hợp lệ (định dạng DD/MM/YYYY hoặc YYYY-MM-DD).";
                $skipped++;
                continue;
            }

            $rawMfg = $this->emptyPlaceholder($cell('mfg'));
            $mfgDate = $rawMfg === '' ? null : $this->parseStrictDate($rawMfg);
            if ($rawMfg !== '' && $mfgDate === null) {
                $errors[] = "{$rowLabel}: Ngày sản xuất '{$rawMfg}' không hợp lệ (định dạng DD/MM/YYYY hoặc YYYY-MM-DD).";
                $skipped++;
                continue;
            }

            $data = [
                'custom_name' => $customName,
                'batch_number' => $this->emptyPlaceholder($cell('batch')) ?: null,
                'manufacturing_date' => $mfgDate,
                'expiry_date' => $expiryDate,
                'quantity' => $this->parseNumber($cell('qty')),
                'unit' => $cell('unit') ?: 'viên',
                'storage_location' => $cell('location') ?: 'Tủ thuốc gia đình',
                'price_per_unit' => $this->parseNumber($this->emptyPlaceholder($cell('price'))),
                'notes' => $this->emptyPlaceholder($cell('notes')) ?: null,
            ];

            $validator = $this->makeItemValidator($data, false);
            if ($validator->fails()) {
                $errors[] = "{$rowLabel}: " . implode(' ', $validator->errors()->all());
                $skipped++;
                continue;
            }

            // Liên kết danh mục thuốc chuẩn: khớp CHÍNH XÁC tên (không phân biệt hoa thường/khoảng trắng) hoặc mã thuốc
            $key = $this->normalizeKey($customName);
            $data['medicine_id'] = $medicinesByName[$key] ?? $medicinesByCode[$key] ?? null;
            $data['family_id'] = $family->id;
            $data['status'] = CabinetItem::computeStatus($data['expiry_date'], $data['quantity']);

            $validRows[] = $data;
        }

        // Ghi toàn bộ trong 1 transaction: lỗi bất ngờ sẽ không để lại dữ liệu nhập dở dang
        DB::transaction(function () use ($validRows) {
            foreach ($validRows as $data) {
                CabinetItem::create($data);
            }
        });

        $imported = count($validRows);
        if ($imported > 0) {
            $this->refreshAlerts($family);
        }

        $message = "Đã nhập thành công {$imported} loại thuốc vào tủ thuốc gia đình!";
        if (!empty($errors)) {
            $message .= " Bỏ qua {$skipped} dòng lỗi: " . implode('; ', array_slice($errors, 0, 3)) . (count($errors) > 3 ? '...' : '');

            return redirect()->route('cabinet.index')
                ->with($imported > 0 ? 'warning' : 'danger', $message)
                ->with('import_errors', array_slice($errors, 0, 50));
        }

        return redirect()->route('cabinet.index')->with('success', $message);
    }

    private function validatedData(Request $request): array
    {
        return $this->makeItemValidator($request->all(), true)->validate();
    }

    /**
     * Bộ luật kiểm tra dữ liệu lô thuốc dùng chung cho form và nhập file
     */
    private function makeItemValidator(array $input, bool $withMedicine): \Illuminate\Validation\Validator
    {
        return CabinetItemValidator::make($input, $withMedicine);
    }

    /**
     * Quét lại cảnh báo của gia đình sau khi tủ thuốc thay đổi (gỡ cảnh báo cũ không còn đúng)
     */
    private function refreshAlerts(Family $family): void
    {
        app(AlertScannerService::class)->scanFamily($family);
    }

    /**
     * Chuyển nội dung file về UTF-8 (hỗ trợ UTF-8 BOM, UTF-16 LE/BE BOM, Windows-1258/1252). Trả về null nếu là file nhị phân.
     */
    private function normalizeEncoding(string $content): ?string
    {
        if (str_starts_with($content, "\xFF\xFE")) {
            $content = mb_convert_encoding(substr($content, 2), 'UTF-8', 'UTF-16LE');
        } elseif (str_starts_with($content, "\xFE\xFF")) {
            $content = mb_convert_encoding(substr($content, 2), 'UTF-8', 'UTF-16BE');
        } else {
            $content = preg_replace('/^\xEF\xBB\xBF/', '', $content);

            // File nhị phân (xlsx/xls/zip...) chứa byte NUL
            if (str_contains($content, "\0")) {
                return null;
            }

            if (!mb_check_encoding($content, 'UTF-8')) {
                $converted = function_exists('iconv') ? @iconv('CP1258', 'UTF-8//IGNORE', $content) : false;
                $content = $converted !== false && $converted !== ''
                    ? $converted
                    : mb_convert_encoding($content, 'UTF-8', 'Windows-1252');
            }
        }

        if (str_contains($content, "\0")) {
            return null;
        }

        return $this->composeVietnamese($content);
    }

    /**
     * Chuẩn hóa tổ hợp dấu tiếng Việt (NFD => NFC). Windows-1258 & macOS lưu dấu thanh dạng ký tự tổ hợp rời.
     */
    private function composeVietnamese(string $content): string
    {
        if (class_exists(\Normalizer::class)) {
            return \Normalizer::normalize($content, \Normalizer::FORM_C) ?: $content;
        }

        // Dự phòng khi máy chủ không có extension intl: ghép nguyên âm + dấu thanh (huyền, sắc, ngã, hỏi, nặng)
        static $map = null;
        if ($map === null) {
            $marks = ["\u{0300}", "\u{0301}", "\u{0303}", "\u{0309}", "\u{0323}"];
            $table = [
                'a' => 'àáãảạ', 'ă' => 'ằắẵẳặ', 'â' => 'ầấẫẩậ', 'e' => 'èéẽẻẹ', 'ê' => 'ềếễểệ', 'i' => 'ìíĩỉị',
                'o' => 'òóõỏọ', 'ô' => 'ồốỗổộ', 'ơ' => 'ờớỡởợ', 'u' => 'ùúũủụ', 'ư' => 'ừứữửự', 'y' => 'ỳýỹỷỵ',
                'A' => 'ÀÁÃẢẠ', 'Ă' => 'ẰẮẴẲẶ', 'Â' => 'ẦẤẪẨẬ', 'E' => 'ÈÉẼẺẸ', 'Ê' => 'ỀẾỄỂỆ', 'I' => 'ÌÍĨỈỊ',
                'O' => 'ÒÓÕỎỌ', 'Ô' => 'ỒỐỖỔỘ', 'Ơ' => 'ỜỚỠỞỢ', 'U' => 'ÙÚŨỦỤ', 'Ư' => 'ỪỨỮỬỰ', 'Y' => 'ỲÝỸỶỴ',
            ];
            $map = [];
            foreach ($table as $base => $composed) {
                foreach (mb_str_split($composed) as $i => $char) {
                    $map[$base . $marks[$i]] = $char;
                }
            }
        }

        return strtr($content, $map);
    }

    /**
     * Nhận diện ký tự phân cách (dấu phẩy, chấm phẩy hoặc tab) dựa trên dòng tiêu đề
     */
    private function detectDelimiter(string $content): string
    {
        $firstLine = strtok($content, "\r\n") ?: '';
        $counts = [',' => substr_count($firstLine, ','), ';' => substr_count($firstLine, ';'), "\t" => substr_count($firstLine, "\t")];
        arsort($counts);

        return reset($counts) > 0 ? array_key_first($counts) : ',';
    }

    /**
     * Đọc các dòng CSV (hỗ trợ ô có dấu ngoặc kép chứa xuống dòng), bỏ qua dòng trống hoàn toàn
     *
     * @return array<int, array{0:int, 1:array}> [số dòng trong file, các ô]
     */
    private function readCsvRows(string $content, string $delimiter): array
    {
        $stream = fopen('php://temp', 'r+');
        fwrite($stream, $content);
        rewind($stream);

        $rows = [];
        $lineNo = 0;
        while (($row = fgetcsv($stream, 0, $delimiter, '"', '')) !== false) {
            $lineNo++;
            if ($row === [null] || count(array_filter($row, fn ($v) => trim((string) $v) !== '')) === 0) {
                continue;
            }
            $rows[] = [$lineNo, $row];
            if (count($rows) > self::IMPORT_MAX_ROWS + 1) {
                break; // Đủ để báo vượt giới hạn, không đọc tiếp
            }
        }
        fclose($stream);

        return $rows;
    }

    /**
     * Ánh xạ cột tiêu đề => trường dữ liệu. Cột khớp ĐẦU TIÊN được giữ, các cột thông tin phụ của file xuất bị bỏ qua.
     */
    private function mapImportHeaders(array $headerCols): array
    {
        $map = [];
        foreach ($headerCols as $colIdx => $colName) {
            $name = mb_strtolower(trim(preg_replace('/^\xEF\xBB\xBF/', '', (string) $colName)), 'UTF-8');
            $name = trim(preg_replace('/\s*\(.*?\)\s*/u', ' ', $name)); // bỏ chú thích trong ngoặc
            $name = preg_replace('/\s+/u', ' ', $name);

            $field = $this->headerField($name);
            if ($field !== null && !isset($map[$field])) {
                $map[$field] = $colIdx;
            }
        }

        return $map;
    }

    private function headerField(string $name): ?string
    {
        if ($name === '') {
            return null;
        }

        // Cột chỉ mang tính thông tin trong file xuất => bỏ qua
        foreach (['stt', 'thời gian còn lại', 'tình trạng', 'tổng giá trị', 'thành tiền', 'nhóm dược lý', 'hàm lượng', 'nồng độ', 'trạng thái'] as $ignored) {
            if (str_starts_with($name, $ignored)) {
                return null;
            }
        }

        $words = preg_split('/[\s\/_\-]+/u', $name);

        return match (true) {
            str_starts_with($name, 'tên') || in_array($name, ['custom_name', 'name', 'biệt dược', 'medicine'], true) => 'name',
            str_contains($name, 'hoạt chất') || str_contains($name, 'ingredient') => 'ingredient',
            str_starts_with($name, 'số lô') || in_array('lô', $words, true) || str_contains($name, 'batch') => 'batch',
            str_contains($name, 'ngày sản xuất') || in_array($name, ['nsx', 'mfg', 'manufacturing_date'], true) || str_contains($name, 'manufactur') => 'mfg',
            str_contains($name, 'hạn sử dụng') || str_contains($name, 'hạn dùng') || in_array($name, ['hsd', 'exp', 'expiry', 'expiry_date', 'hết hạn', 'ngày hết hạn'], true) => 'exp',
            str_starts_with($name, 'số lượng') || in_array($name, ['sl', 'qty', 'quantity', 'tồn kho'], true) => 'qty',
            str_starts_with($name, 'đơn vị') || in_array($name, ['đvt', 'unit'], true) => 'unit',
            str_starts_with($name, 'đơn giá') || in_array($name, ['giá', 'price', 'price_per_unit', 'giá bán'], true) => 'price',
            str_starts_with($name, 'vị trí') || in_array($name, ['location', 'storage_location', 'nơi cất'], true) || str_starts_with($name, 'ngăn') => 'location',
            str_starts_with($name, 'ghi chú') || in_array($name, ['note', 'notes', 'lưu ý'], true) => 'notes',
            default => null,
        };
    }

    /**
     * Phân tích ngày nghiêm ngặt: DD/MM/YYYY, DD-MM-YYYY, DD.MM.YYYY, YYYY-MM-DD, YYYY/MM/DD. Trả về Y-m-d hoặc null nếu sai.
     */
    private function parseStrictDate(string $raw): ?string
    {
        $raw = trim($raw);
        if (preg_match('/^(\d{4})[\/\-.](\d{1,2})[\/\-.](\d{1,2})$/', $raw, $m)) {
            [$year, $month, $day] = [(int) $m[1], (int) $m[2], (int) $m[3]];
        } elseif (preg_match('/^(\d{1,2})[\/\-.](\d{1,2})[\/\-.](\d{4})$/', $raw, $m)) {
            [$day, $month, $year] = [(int) $m[1], (int) $m[2], (int) $m[3]];
        } else {
            return null;
        }

        if ($year < 1900 || $year > 2200 || !checkdate($month, $day, $year)) {
            return null;
        }

        return sprintf('%04d-%02d-%02d', $year, $month, $day);
    }

    /**
     * Chuẩn hóa số kiểu Việt Nam/quốc tế: "2.500" => 2500, "2,500" => 2500, "2,5" => 2.5, "1.234,5" => 1234.5.
     * Trả lại chuỗi gốc nếu không phải số để luật "numeric" báo lỗi rõ ràng.
     */
    private function parseNumber(string $raw): string|float|null
    {
        $value = preg_replace('/\s+|(vnđ|vnd|đ)$/iu', '', trim($raw));
        if ($value === '' || $value === null) {
            return null;
        }

        $hasDot = str_contains($value, '.');
        $hasComma = str_contains($value, ',');

        if ($hasDot && $hasComma) {
            // Dấu xuất hiện sau cùng là dấu thập phân
            $value = strrpos($value, ',') > strrpos($value, '.')
                ? str_replace(['.', ','], ['', '.'], $value)
                : str_replace(',', '', $value);
        } elseif ($hasDot) {
            if (preg_match('/^-?\d{1,3}(\.\d{3})+$/', $value)) {
                $value = str_replace('.', '', $value);
            }
        } elseif ($hasComma) {
            $value = preg_match('/^-?\d{1,3}(,\d{3})+$/', $value)
                ? str_replace(',', '', $value)
                : str_replace(',', '.', $value);
        }

        return is_numeric($value) ? (float) $value : $raw;
    }

    /**
     * Các ký hiệu "trống" mà file xuất dùng (—, -, N/A) được coi như ô rỗng
     */
    private function emptyPlaceholder(string $value): string
    {
        return in_array(mb_strtolower($value, 'UTF-8'), ['—', '–', '-', 'n/a', 'không'], true) ? '' : $value;
    }

    private function normalizeKey(string $value): string
    {
        return preg_replace('/\s+/u', ' ', mb_strtolower(trim($value), 'UTF-8'));
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
        abort_unless($request->user()->canManageCabinet(), 403, 'Chỉ Bác sĩ/Người chăm sóc y tế hoặc Quản trị viên hệ thống mới có quyền thay đổi tủ thuốc gia đình.');
        return $family;
    }

    private function authorizeItem(Request $request, CabinetItem $item): void
    {
        abort_unless($item->family_id === $this->ensureFamily($request)->id, 404);
    }
}

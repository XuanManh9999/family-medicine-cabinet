<?php

namespace Tests\Feature;

use App\Models\CabinetItem;
use App\Models\CabinetRequest;
use App\Models\Family;
use App\Models\FamilyMember;
use App\Models\MedicationSchedule;
use App\Models\Medicine;
use App\Models\MedicineCategory;
use App\Models\MedicineExpense;
use App\Models\PrescriptionItem;
use App\Models\RefillRequest;
use App\Models\SmartAlert;
use App\Models\TreatmentCourse;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Quét mọi trang GET của ứng dụng với từng vai trò: không trang nào được lỗi 500
 * (chỉ chấp nhận 200 / chuyển hướng / 403 / 404 tùy quyền)
 */
class RouteSmokeTest extends TestCase
{
    use RefreshDatabase;

    private const SKIP = [
        'cabinet.export-excel', 'cabinet.sample-excel', 'expenses.export-csv', // tải file
        'cabinet.requests.photo', 'cabinet.lots.photo', // ảnh trên ổ riêng tư (đã có test riêng)
        'schedules.calendar-events', // cần tham số start/end (đã có test riêng)
    ];

    public function test_every_get_page_renders_for_every_role(): void
    {
        $this->seed();
        $family = Family::where('code', 'FAM-VN2026')->firstOrFail();
        $familyAdmin = User::where('email', 'an.nguyen@gmail.com')->firstOrFail();

        // Dữ liệu mẫu cho các trang mới: đề nghị nhập thuốc, yêu cầu bổ sung, lô chờ xác nhận
        $cabinetRequest = CabinetRequest::create([
            'family_id' => $family->id, 'requested_by' => $familyAdmin->id, 'custom_name' => 'Vitamin C 500mg',
            'expiry_date' => now()->addYear(), 'quantity' => 1, 'unit' => 'hộp', 'status' => 'pending',
        ]);
        $prescriptionItem = PrescriptionItem::whereHas('treatmentCourse', fn ($q) => $q->where('family_id', $family->id))->firstOrFail();
        $lot = CabinetItem::create([
            'family_id' => $family->id, 'prescription_item_id' => $prescriptionItem->id, 'custom_name' => $prescriptionItem->medicine_name,
            'quantity' => 5, 'purchased_quantity' => 5, 'unit' => 'viên', 'expiry_date' => now()->addYear(),
            'status' => 'con_han', 'approval_status' => CabinetItem::APPROVAL_PENDING, 'purchased_by' => $familyAdmin->id,
        ]);
        $refill = RefillRequest::create([
            'family_id' => $family->id, 'requested_by' => $familyAdmin->id, 'medicine_name' => 'Băng dán Urgo',
            'unit' => 'hộp', 'source' => 'cabinet', 'status' => 'approved', 'approved_quantity' => 1,
        ]);

        $params = [
            'member' => FamilyMember::where('family_id', $family->id)->value('id'),
            'treatment_course' => TreatmentCourse::where('family_id', $family->id)->value('id'),
            'treatmentCourse' => TreatmentCourse::where('family_id', $family->id)->value('id'),
            'schedule' => MedicationSchedule::where('family_id', $family->id)->value('id'),
            'expense' => MedicineExpense::where('family_id', $family->id)->value('id'),
            'medicine' => Medicine::value('id'),
            'cabinetItem' => CabinetItem::where('family_id', $family->id)->value('id'),
            'cabinetRequest' => $cabinetRequest->id,
            'prescriptionItem' => $prescriptionItem->id,
            'refillRequest' => $refill->id,
            'lot' => $lot->id,
            'index' => 0,
            'family' => $family->id,
            'user' => $familyAdmin->id,
            'category' => MedicineCategory::value('id'),
            'alert' => SmartAlert::where('family_id', $family->id)->value('id'),
        ];

        $users = [
            'family_admin' => $familyAdmin,
            'member' => User::where('email', 'hoa.tran@gmail.com')->firstOrFail(),
            'doctor' => User::where('email', 'bs.minh@medcabinet.vn')->firstOrFail(),
            'system_admin' => User::where('email', 'admin@medcabinet.vn')->firstOrFail(),
        ];

        $failures = [];
        $checked = 0;
        foreach (Route::getRoutes() as $route) {
            $name = $route->getName();
            if (!in_array('GET', $route->methods(), true) || !$name || in_array($name, self::SKIP, true)
                || str_starts_with($route->uri(), '_') || str_starts_with($route->uri(), 'storage') || $route->uri() === 'up') {
                continue;
            }

            $routeParams = [];
            foreach ($route->parameterNames() as $param) {
                if (!array_key_exists($param, $params)) {
                    $failures[] = "{$name}: chưa có dữ liệu mẫu cho tham số {{$param}}";
                    continue 2;
                }
                $routeParams[$param] = $params[$param];
            }
            $url = route($name, $routeParams);
            $checked++;

            foreach ($users as $role => $user) {
                $response = $this->actingAs($user)->get($url);
                $status = $response->getStatusCode();
                if ($status >= 500) {
                    $message = $response->exception ? get_class($response->exception) . ': ' . $response->exception->getMessage() : '';
                    $failures[] = "[{$role}] {$name} ({$url}) → {$status} {$message}";
                }
            }
            auth()->logout();
        }

        $this->assertGreaterThanOrEqual(40, $checked, "Chỉ quét được {$checked} trang");
        $this->assertSame([], $failures, implode("\n", $failures));
    }
}

<?php

namespace Tests\Feature;

use App\Models\CabinetItem;
use App\Models\Family;
use App\Models\FamilyMember;
use App\Models\MedicationLog;
use App\Models\MedicationSchedule;
use App\Models\Medicine;
use App\Models\MedicineCategory;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MedicationScheduleManagementTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Family $family;
    protected FamilyMember $grandpa;
    protected FamilyMember $daughter;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->family = Family::where('code', 'FAM-VN2026')->first();
        $this->user = User::where('email', 'bs.minh@medcabinet.vn')->first();
        $this->grandpa = FamilyMember::where('family_id', $this->family->id)->where('name', 'Nguyễn Văn Thịnh')->first();
        $this->daughter = FamilyMember::where('family_id', $this->family->id)->where('name', 'Nguyễn Mai Anh')->first();
    }

    public function test_user_can_view_schedules_timeline_with_kpis(): void
    {
        $response = $this->actingAs($this->user)->get(route('schedules.index'));

        $response->assertStatus(200);
        $response->assertSee('Lịch uống thuốc &amp; Nhật ký tuân thủ', false);
        $response->assertSee('Cần uống trong ngày');
        $response->assertSee('Đã uống đúng giờ');
        $response->assertSee('Tỷ lệ tuân thủ');
        $response->assertSee('Cữ Sáng');
        $response->assertSee('Cữ Tối');
    }

    public function test_user_can_filter_schedules_by_member_and_time_of_day(): void
    {
        $response = $this->actingAs($this->user)->get(route('schedules.index', [
            'member_id' => $this->grandpa->id,
            'time_of_day' => 'sang',
        ]));

        $response->assertStatus(200);
        $response->assertSee('Nguyễn Văn Thịnh');
        $response->assertSee('Cữ Sáng');
    }

    public function test_user_can_view_all_schedules_tab(): void
    {
        $response = $this->actingAs($this->user)->get(route('schedules.index', ['view' => 'all']));

        $response->assertStatus(200);
        $response->assertSee('DANH SÁCH LỊCH UỐNG THUỐC ĐỊNH KỲ');
        $response->assertSee('Amlodipine 5mg');
    }

    public function test_user_can_view_logs_history_tab(): void
    {
        $response = $this->actingAs($this->user)->get(route('schedules.index', ['view' => 'logs']));

        $response->assertStatus(200);
        $response->assertSee('LỊCH SỬ NHẬT KÝ UỐNG THUỐC');
    }

    public function test_user_can_record_taken_log_and_auto_deduct_cabinet_stock(): void
    {
        $category = MedicineCategory::first();
        $medicine = Medicine::firstOrCreate(
            ['name' => 'Amlodipine 5mg'],
            [
                'category_id' => $category->id,
                'code' => 'AML5',
                'active_ingredient' => 'Amlodipine',
                'concentration' => '5mg',
                'dosage_form' => 'Viên',
                'default_unit' => 'viên',
            ]
        );

        $cabinetItem = CabinetItem::firstOrCreate(
            [
                'family_id' => $this->family->id,
                'medicine_id' => $medicine->id,
            ],
            [
                'custom_name' => 'Amlodipine 5mg',
                'quantity' => 20,
                'unit' => 'viên',
                'expiry_date' => Carbon::today()->addYear(),
                'status' => 'con_han',
            ]
        );
        $cabinetItem->update(['quantity' => 20]);

        $schedule = MedicationSchedule::where('family_id', $this->family->id)
            ->where('medicine_name', 'like', '%Amlodipine%')
            ->first();

        $this->assertNotNull($schedule);

        // Dữ liệu mẫu đã có nhật ký "Đã uống" hôm nay; xóa đi để kiểm tra lần ghi nhận đầu tiên trong ngày
        MedicationLog::where('schedule_id', $schedule->id)->whereDate('scheduled_time', Carbon::today())->delete();

        $payload = [
            'schedule_id' => $schedule->id,
            'scheduled_time' => Carbon::today()->setTime(7, 30, 0)->toDateTimeString(),
            'status' => 'da_uong',
            'note' => 'Đã uống cùng nước ấm',
            'deduct_cabinet' => '1',
        ];

        $response = $this->actingAs($this->user)->post(route('schedules.log'), $payload);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        // Check MedicationLog was created
        $log = MedicationLog::where('schedule_id', $schedule->id)
            ->whereDate('scheduled_time', Carbon::today())
            ->first();

        $this->assertNotNull($log);
        $this->assertEquals('da_uong', $log->status);
        $this->assertEquals('Đã uống cùng nước ấm', $log->note);
        $this->assertNotNull($log->actual_taken_time);

        // Check stock was decremented from 20 to 19
        $cabinetItem->refresh();
        $this->assertEquals(19, $cabinetItem->quantity);
    }

    public function test_user_can_record_skipped_or_missed_log(): void
    {
        $schedule = MedicationSchedule::create([
            'family_id' => $this->family->id,
            'family_member_id' => $this->daughter->id,
            'medicine_name' => 'Kẹo ngậm ho thảo dược',
            'dosage' => '1 viên',
            'unit' => 'viên',
            'time_of_day' => 'trua',
            'scheduled_time' => '12:00:00',
            'meal_timing' => 'sau_an',
            'start_date' => Carbon::today(),
            'is_active' => true,
        ]);

        $payload = [
            'schedule_id' => $schedule->id,
            'scheduled_time' => Carbon::today()->setTime(12, 0, 0)->toDateTimeString(),
            'status' => 'quen_uong',
            'note' => 'Đi công tác quên mang theo thuốc',
        ];

        $response = $this->actingAs($this->user)->post(route('schedules.log'), $payload);

        $response->assertRedirect();

        $log = MedicationLog::where('schedule_id', $schedule->id)
            ->whereDate('scheduled_time', Carbon::today())
            ->first();

        $this->assertNotNull($log);
        $this->assertEquals('quen_uong', $log->status);
        $this->assertEquals('Đi công tác quên mang theo thuốc', $log->note);
        $this->assertNull($log->actual_taken_time);
    }

    public function test_user_can_create_custom_medication_schedule(): void
    {
        $payload = [
            'family_member_id' => $this->daughter->id,
            'medicine_name' => 'Kẹo dẻo Vitamin C Gummy',
            'dosage' => '2 viên',
            'unit' => 'viên',
            'time_of_day' => 'trua',
            'scheduled_time' => '12:30',
            'meal_timing' => 'sau_an',
            'start_date' => Carbon::today()->toDateString(),
            'end_date' => Carbon::today()->addMonths(3)->toDateString(),
            'is_active' => '1',
        ];

        $response = $this->actingAs($this->user)->post(route('schedules.store'), $payload);

        $response->assertRedirect(route('schedules.index'));
        $this->assertDatabaseHas('medication_schedules', [
            'family_id' => $this->family->id,
            'family_member_id' => $this->daughter->id,
            'medicine_name' => 'Kẹo dẻo Vitamin C Gummy',
            'scheduled_time' => '12:30:00',
        ]);
    }

    public function test_user_can_update_medication_schedule(): void
    {
        $schedule = MedicationSchedule::create([
            'family_id' => $this->family->id,
            'family_member_id' => $this->daughter->id,
            'medicine_name' => 'Men vi sinh BioGaia',
            'dosage' => '5 giọt',
            'unit' => 'giọt',
            'time_of_day' => 'sang',
            'scheduled_time' => '08:00:00',
            'meal_timing' => 'sau_an',
            'start_date' => Carbon::today(),
            'is_active' => true,
        ]);

        $payload = [
            'family_member_id' => $this->daughter->id,
            'medicine_name' => 'Men vi sinh BioGaia Protectis',
            'dosage' => '6 giọt',
            'unit' => 'giọt',
            'time_of_day' => 'trua',
            'scheduled_time' => '12:00',
            'meal_timing' => 'trong_khi_an',
            'start_date' => Carbon::today()->toDateString(),
            'is_active' => '1',
        ];

        $response = $this->actingAs($this->user)->put(route('schedules.update', $schedule), $payload);

        $response->assertRedirect(route('schedules.index'));
        $schedule->refresh();
        $this->assertEquals('Men vi sinh BioGaia Protectis', $schedule->medicine_name);
        $this->assertEquals('6 giọt', $schedule->dosage);
        $this->assertEquals('trua', $schedule->time_of_day);
    }

    public function test_user_can_toggle_schedule_active_status(): void
    {
        $schedule = MedicationSchedule::create([
            'family_id' => $this->family->id,
            'family_member_id' => $this->daughter->id,
            'medicine_name' => 'Dầu cá Omega 3',
            'dosage' => '1 viên',
            'time_of_day' => 'sang',
            'scheduled_time' => '07:30:00',
            'meal_timing' => 'sau_an',
            'start_date' => Carbon::today(),
            'is_active' => true,
        ]);

        // Toggle to false
        $response = $this->actingAs($this->user)->patch(route('schedules.toggle', $schedule));
        $response->assertRedirect();
        $schedule->refresh();
        $this->assertFalse($schedule->is_active);

        // Toggle back to true
        $this->actingAs($this->user)->patch(route('schedules.toggle', $schedule));
        $schedule->refresh();
        $this->assertTrue($schedule->is_active);
    }

    public function test_user_can_delete_medication_schedule(): void
    {
        $schedule = MedicationSchedule::create([
            'family_id' => $this->family->id,
            'family_member_id' => $this->daughter->id,
            'medicine_name' => 'Lịch cần xóa',
            'dosage' => '1 viên',
            'time_of_day' => 'sang',
            'scheduled_time' => '07:30:00',
            'meal_timing' => 'sau_an',
            'start_date' => Carbon::today(),
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->user)->delete(route('schedules.destroy', $schedule));

        $response->assertRedirect(route('schedules.index'));
        $this->assertDatabaseMissing('medication_schedules', ['id' => $schedule->id]);
    }

    public function test_family_admin_cannot_create_or_change_schedules_but_can_record_logs(): void
    {
        $familyAdmin = User::where('email', 'an.nguyen@gmail.com')->first();
        $schedule = MedicationSchedule::where('family_id', $this->family->id)->first();

        $this->actingAs($familyAdmin)->get(route('schedules.create'))->assertStatus(403);
        $this->actingAs($familyAdmin)->post(route('schedules.store'), [
            'family_member_id' => $this->daughter->id,
            'medicine_name' => 'Lịch chủ hộ tự tạo',
            'dosage' => '1 viên',
            'time_of_day' => 'sang',
            'scheduled_time' => '07:30',
            'meal_timing' => 'sau_an',
            'start_date' => Carbon::today()->toDateString(),
        ])->assertStatus(403);
        $this->actingAs($familyAdmin)->patch(route('schedules.toggle', $schedule))->assertStatus(403);
        $this->actingAs($familyAdmin)->delete(route('schedules.destroy', $schedule))->assertStatus(403);

        // Điểm danh uống thuốc vẫn là việc của mọi người trong gia đình
        $this->actingAs($familyAdmin)->post(route('schedules.log'), [
            'schedule_id' => $schedule->id,
            'scheduled_time' => Carbon::today()->setTimeFromTimeString($schedule->scheduled_time)->toDateTimeString(),
            'status' => 'bo_qua',
        ])->assertRedirect();
        $this->assertDatabaseHas('medication_logs', ['schedule_id' => $schedule->id, 'status' => 'bo_qua']);
    }

    /**
     * Tạo nhanh 1 lịch uống tự thêm + lô thuốc trong tủ cùng tên
     */
    protected function makeScheduleWithStock(string $name, string $dosage = '1 viên', string $scheduleUnit = 'viên', array $cabinetOverrides = []): array
    {
        $schedule = MedicationSchedule::create([
            'family_id' => $this->family->id,
            'family_member_id' => $this->daughter->id,
            'medicine_name' => $name,
            'dosage' => $dosage,
            'unit' => $scheduleUnit,
            'time_of_day' => 'sang',
            'scheduled_time' => '07:30:00',
            'meal_timing' => 'sau_an',
            'start_date' => Carbon::today()->subDays(5),
            'is_active' => true,
        ]);

        $item = CabinetItem::create(array_merge([
            'family_id' => $this->family->id,
            'custom_name' => $name,
            'quantity' => 10,
            'unit' => 'viên',
            'expiry_date' => Carbon::today()->addYear(),
            'status' => 'con_han',
        ], $cabinetOverrides));

        return [$schedule, $item];
    }

    protected function logPayload(MedicationSchedule $schedule, string $status, array $extra = []): array
    {
        return array_merge([
            'schedule_id' => $schedule->id,
            'scheduled_time' => Carbon::today()->setTime(7, 30)->toDateTimeString(),
            'status' => $status,
            'deduct_cabinet' => '1',
        ], $extra);
    }

    public function test_relogging_taken_does_not_double_deduct_and_switching_status_restores_stock(): void
    {
        [$schedule, $item] = $this->makeScheduleWithStock('Thuốc thử trừ kho ABC');

        $this->actingAs($this->user)->postJson(route('schedules.log'), $this->logPayload($schedule, 'da_uong'))
            ->assertOk()
            ->assertJsonPath('deducted_cabinet.deducted_quantity', 1);
        $this->assertEquals(9, (float) $item->fresh()->quantity);

        // Bấm lại "Đã uống" (sửa ghi chú / bấm đúp) không trừ thêm
        $this->actingAs($this->user)->postJson(route('schedules.log'), $this->logPayload($schedule, 'da_uong', ['note' => 'Sửa ghi chú']))
            ->assertOk()
            ->assertJsonPath('deducted_cabinet', null);
        $this->assertEquals(9, (float) $item->fresh()->quantity);

        $log = MedicationLog::where('schedule_id', $schedule->id)->sole();
        $this->assertEquals($item->id, $log->cabinet_item_id);
        $this->assertEquals(1, (float) $log->deducted_quantity);
        $this->assertEquals('Sửa ghi chú', $log->note);

        // Đổi sang "Bỏ qua" thì hoàn trả đúng số lượng đã trừ
        $this->actingAs($this->user)->postJson(route('schedules.log'), $this->logPayload($schedule, 'bo_qua'))
            ->assertOk()
            ->assertJsonPath('restored_cabinet.restored_quantity', 1);
        $this->assertEquals(10, (float) $item->fresh()->quantity);

        $log->refresh();
        $this->assertEquals('bo_qua', $log->status);
        $this->assertNull($log->cabinet_item_id);
        $this->assertNull($log->deducted_quantity);

        // Uống bù lại thì trừ lần nữa
        $this->actingAs($this->user)->postJson(route('schedules.log'), $this->logPayload($schedule, 'da_uong'))->assertOk();
        $this->assertEquals(9, (float) $item->fresh()->quantity);
        $this->assertSame(1, MedicationLog::where('schedule_id', $schedule->id)->count());
    }

    public function test_stock_change_from_log_refreshes_cabinet_alerts(): void
    {
        [$schedule, $item] = $this->makeScheduleWithStock('Thuốc thử cảnh báo tồn', '1 viên', 'viên', ['quantity' => 1]);

        $this->actingAs($this->user)->postJson(route('schedules.log'), $this->logPayload($schedule, 'da_uong'))->assertOk();
        $this->assertEquals(0, (float) $item->fresh()->quantity);
        $this->assertDatabaseHas('smart_alerts', ['cabinet_item_id' => $item->id, 'type' => 'out_of_stock']);

        // Hoàn trả kho thì cảnh báo hết hàng được làm mới (không còn hết hàng)
        $this->actingAs($this->user)->postJson(route('schedules.log'), $this->logPayload($schedule, 'bo_qua'))->assertOk();
        $this->assertDatabaseMissing('smart_alerts', ['cabinet_item_id' => $item->id, 'type' => 'out_of_stock']);
    }

    public function test_scheduled_time_is_normalised_to_schedule_time_one_log_per_day(): void
    {
        [$schedule] = $this->makeScheduleWithStock('Thuốc thử mốc giờ');

        $this->actingAs($this->user)->postJson(route('schedules.log'), $this->logPayload($schedule, 'quen_uong', [
            'scheduled_time' => Carbon::today()->setTime(9, 13)->toDateTimeString(),
        ]))->assertOk();

        // Lịch đổi giờ nhắc sau đó, ghi nhận lại trong cùng ngày vẫn cập nhật đúng nhật ký cũ
        $schedule->update(['scheduled_time' => '08:15:00']);
        $this->actingAs($this->user)->postJson(route('schedules.log'), $this->logPayload($schedule, 'bo_qua', [
            'scheduled_time' => null,
            'date' => Carbon::today()->toDateString(),
        ]))->assertOk();

        $log = MedicationLog::where('schedule_id', $schedule->id)->sole();
        $this->assertEquals('bo_qua', $log->status);
        $this->assertEquals(Carbon::today()->setTime(7, 30)->toDateTimeString(), $log->scheduled_time->toDateTimeString());
    }

    public function test_unchecked_deduct_checkbox_does_not_deduct_stock(): void
    {
        [$schedule, $item] = $this->makeScheduleWithStock('Thuốc thử không trừ kho');

        // Form gửi input ẩn deduct_cabinet=0 khi bỏ chọn
        $this->actingAs($this->user)->post(route('schedules.log'), $this->logPayload($schedule, 'da_uong', ['deduct_cabinet' => '0']))
            ->assertRedirect();
        $this->assertEquals(10, (float) $item->fresh()->quantity);

        // Không gửi trường deduct_cabinet cũng không trừ
        $other = $this->makeScheduleWithStock('Thuốc thử không gửi cờ')[0];
        $payload = $this->logPayload($other, 'da_uong');
        unset($payload['deduct_cabinet']);
        $this->actingAs($this->user)->post(route('schedules.log'), $payload)->assertRedirect();
        $this->assertEquals(10, (float) CabinetItem::where('custom_name', 'Thuốc thử không gửi cờ')->value('quantity'));
    }

    public function test_deduction_picks_non_expired_earliest_expiry_item(): void
    {
        [$schedule, $later] = $this->makeScheduleWithStock('Thuốc thử FEFO', '1 viên', 'viên', [
            'expiry_date' => Carbon::today()->addMonths(6),
        ]);

        $expired = CabinetItem::create([
            'family_id' => $this->family->id,
            'custom_name' => 'Thuốc thử FEFO',
            'quantity' => 10,
            'unit' => 'viên',
            'expiry_date' => Carbon::today()->subDay(),
            'status' => 'qua_han',
        ]);

        $earliest = CabinetItem::create([
            'family_id' => $this->family->id,
            'custom_name' => 'thuốc thử fefo',
            'quantity' => 10,
            'unit' => 'Viên',
            'expiry_date' => Carbon::today()->addMonth(),
            'status' => 'sap_het_han',
        ]);

        $this->actingAs($this->user)->postJson(route('schedules.log'), $this->logPayload($schedule, 'da_uong'))->assertOk();

        $this->assertEquals(10, (float) $expired->fresh()->quantity);
        $this->assertEquals(10, (float) $later->fresh()->quantity);
        $this->assertEquals(9, (float) $earliest->fresh()->quantity);
        $this->assertEquals($earliest->id, MedicationLog::where('schedule_id', $schedule->id)->value('cabinet_item_id'));
    }

    public function test_fractional_dosage_deducts_exact_amount(): void
    {
        [$schedule, $item] = $this->makeScheduleWithStock('Thuốc thử nửa viên', '1/2 viên');

        $this->actingAs($this->user)->postJson(route('schedules.log'), $this->logPayload($schedule, 'da_uong'))->assertOk();
        $this->assertEquals(9.5, (float) $item->fresh()->quantity);

        $this->assertSame(0.5, MedicationSchedule::parseDose('½ viên')['amount']);
        $this->assertSame(0.5, MedicationSchedule::parseDose('0,5 viên')['amount']);
        $this->assertSame(1.5, MedicationSchedule::parseDose('1 1/2 viên')['amount']);
        $this->assertSame(1.5, MedicationSchedule::parseDose('1½ viên')['amount']);
        $this->assertSame(0.5, MedicationSchedule::parseDose('Nửa viên')['amount']);
        $this->assertSame(2.0, MedicationSchedule::parseDose('2 gói')['amount']);
        $this->assertNull(MedicationSchedule::parseDose('Theo chỉ định'));
    }

    public function test_unit_mismatch_or_insufficient_stock_skips_deduction_with_warning(): void
    {
        [$mlSchedule, $bottle] = $this->makeScheduleWithStock('Siro thử đơn vị', '5 ml', 'ml', ['unit' => 'chai', 'quantity' => 2]);

        $this->actingAs($this->user)->postJson(route('schedules.log'), $this->logPayload($mlSchedule, 'da_uong'))
            ->assertOk()
            ->assertJsonPath('deducted_cabinet', null)
            ->assertJson(fn ($json) => $json->where('warning', fn ($w) => is_string($w) && str_contains($w, 'Đơn vị'))->etc());
        $this->assertEquals(2, (float) $bottle->fresh()->quantity);

        [$lowSchedule, $low] = $this->makeScheduleWithStock('Thuốc thử sắp hết', '2 viên', 'viên', ['quantity' => 1]);

        $this->actingAs($this->user)->postJson(route('schedules.log'), $this->logPayload($lowSchedule, 'da_uong'))
            ->assertOk()
            ->assertJsonPath('deducted_cabinet', null)
            ->assertJson(fn ($json) => $json->where('warning', fn ($w) => is_string($w) && str_contains($w, 'không đủ'))->etc());
        $this->assertEquals(1, (float) $low->fresh()->quantity);
        $this->assertEquals('da_uong', MedicationLog::where('schedule_id', $lowSchedule->id)->value('status'));
    }

    public function test_log_for_future_date_or_outside_schedule_range_is_rejected(): void
    {
        [$schedule] = $this->makeScheduleWithStock('Thuốc thử ngày tương lai');

        $this->actingAs($this->user)->postJson(route('schedules.log'), $this->logPayload($schedule, 'da_uong', [
            'scheduled_time' => Carbon::tomorrow()->setTime(7, 30)->toDateTimeString(),
        ]))->assertStatus(422);

        $this->actingAs($this->user)->postJson(route('schedules.log'), $this->logPayload($schedule, 'quen_uong', [
            'scheduled_time' => Carbon::today()->subDays(10)->setTime(7, 30)->toDateTimeString(),
        ]))->assertStatus(422);

        $this->assertSame(0, MedicationLog::where('schedule_id', $schedule->id)->count());

        // Hôm nay vẫn cho phép (kể cả trước giờ nhắc)
        $this->actingAs($this->user)->postJson(route('schedules.log'), $this->logPayload($schedule, 'quen_uong'))->assertOk();
    }

    public function test_invalid_date_query_falls_back_to_today(): void
    {
        $this->actingAs($this->user)->get(route('schedules.index', ['date' => 'khong-phai-ngay']))
            ->assertOk()
            ->assertSee(Carbon::today()->format('d/m/Y'));

        $this->actingAs($this->user)->get(route('schedules.index', ['date' => '2026-02-31']))->assertOk();
    }

    public function test_timeline_keeps_history_of_deactivated_schedule_and_kpi_for_future_day(): void
    {
        $schedule = MedicationSchedule::create([
            'family_id' => $this->family->id,
            'family_member_id' => $this->daughter->id,
            'medicine_name' => 'Thuốc đợt cũ đã kết thúc',
            'dosage' => '1 viên',
            'unit' => 'viên',
            'time_of_day' => 'sang',
            'scheduled_time' => '07:30:00',
            'meal_timing' => 'sau_an',
            'start_date' => Carbon::today()->subDays(5),
            'end_date' => Carbon::today()->addDays(5),
            'is_active' => false,
        ]);

        MedicationLog::create([
            'schedule_id' => $schedule->id,
            'family_member_id' => $this->daughter->id,
            'scheduled_time' => Carbon::today()->subDays(2)->setTime(7, 30),
            'actual_taken_time' => Carbon::today()->subDays(2)->setTime(7, 40),
            'status' => 'da_uong',
        ]);

        $this->actingAs($this->user)->get(route('schedules.index', ['date' => Carbon::today()->subDays(2)->toDateString()]))
            ->assertOk()
            ->assertSee('Thuốc đợt cũ đã kết thúc');

        // Ngày không có nhật ký thì lịch đã ngừng không hiển thị
        $this->actingAs($this->user)->get(route('schedules.index', ['date' => Carbon::today()->subDay()->toDateString()]))
            ->assertOk()
            ->assertDontSee('Thuốc đợt cũ đã kết thúc');

        // Ngày tương lai: chưa tính tỷ lệ tuân thủ
        $future = $this->actingAs($this->user)->get(route('schedules.index', ['date' => Carbon::today()->addDays(3)->toDateString()]));
        $future->assertOk();
        $this->assertNull($future->viewData('adherenceRate'));
    }

    public function test_user_cannot_modify_other_family_schedule(): void
    {
        $otherFamily = Family::create([
            'name' => 'Gia đình khác',
            'code' => 'FAM-SCH-' . uniqid(),
        ]);

        $otherMember = FamilyMember::create([
            'family_id' => $otherFamily->id,
            'name' => 'Người lạ',
            'relationship' => 'ban_than',
        ]);

        $otherSchedule = MedicationSchedule::create([
            'family_id' => $otherFamily->id,
            'family_member_id' => $otherMember->id,
            'medicine_name' => 'Thuốc nhà người khác',
            'dosage' => '1 viên',
            'time_of_day' => 'sang',
            'scheduled_time' => '07:30:00',
            'meal_timing' => 'sau_an',
            'start_date' => Carbon::today(),
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->user)->get(route('schedules.edit', $otherSchedule));
        $response->assertStatus(403);
    }
}

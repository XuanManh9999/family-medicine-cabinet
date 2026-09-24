<?php

namespace Tests\Feature;

use App\Models\Family;
use App\Models\FamilyMember;
use App\Models\MedicineExpense;
use App\Models\TreatmentCourse;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MedicineExpenseManagementTest extends TestCase
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
        $this->user = User::where('email', 'an.nguyen@gmail.com')->first();
        $this->grandpa = FamilyMember::where('family_id', $this->family->id)->where('name', 'Nguyễn Văn Thịnh')->first();
        $this->daughter = FamilyMember::where('family_id', $this->family->id)->where('name', 'Nguyễn Mai Anh')->first();
    }

    public function test_user_can_view_expenses_index_with_kpi_and_charts(): void
    {
        // Tạo một số hóa đơn mẫu
        MedicineExpense::create([
            'family_id' => $this->family->id,
            'family_member_id' => $this->grandpa->id,
            'title' => 'Thuốc điều trị huyết áp ông Thịnh',
            'amount' => 450000,
            'purchase_date' => Carbon::now(),
            'notes' => 'Mua tại nhà thuốc Long Châu',
        ]);

        MedicineExpense::create([
            'family_id' => $this->family->id,
            'family_member_id' => null, // Chi phí chung
            'title' => 'Tủ thuốc sơ cứu gia đình (Bông gòn, cồn, gạc)',
            'amount' => 150000,
            'purchase_date' => Carbon::now(),
        ]);

        $response = $this->actingAs($this->user)->get(route('expenses.index'));

        $response->assertStatus(200);
        $response->assertSee('Thống kê Chi phí thuốc &amp; Viện phí gia đình', false);
        $response->assertSee('Chi tiêu năm ' . Carbon::now()->year);
        $response->assertSee('Chi tiêu tháng này');
        $response->assertSee('Tổng chi toàn thời gian');
        $response->assertSee('Thuốc điều trị huyết áp ông Thịnh');
        $response->assertSee('Tủ thuốc sơ cứu gia đình');
        $response->assertSee('450.000');
        $response->assertSee('150.000');
        $response->assertSee('monthlyExpenseChart');
        $response->assertSee('memberExpenseChart');
    }

    public function test_user_can_filter_expenses_by_year_month_member_and_search(): void
    {
        MedicineExpense::create([
            'family_id' => $this->family->id,
            'family_member_id' => $this->grandpa->id,
            'title' => 'Thuốc Amlodipin định kỳ',
            'amount' => 300000,
            'purchase_date' => Carbon::create(2026, 3, 10),
        ]);

        MedicineExpense::create([
            'family_id' => $this->family->id,
            'family_member_id' => $this->daughter->id,
            'title' => 'Siro ho Prospan cho bé Mai Anh',
            'amount' => 180000,
            'purchase_date' => Carbon::create(2026, 4, 15),
        ]);

        // Lọc theo người thân: ông Thịnh
        $response = $this->actingAs($this->user)->get(route('expenses.index', [
            'member_id' => $this->grandpa->id,
        ]));
        $response->assertStatus(200);
        $response->assertSee('Thuốc Amlodipin định kỳ');
        $response->assertDontSee('Siro ho Prospan');

        // Tìm kiếm từ khóa 'Prospan'
        $searchResponse = $this->actingAs($this->user)->get(route('expenses.index', [
            'q' => 'Prospan',
        ]));
        $searchResponse->assertStatus(200);
        $searchResponse->assertSee('Siro ho Prospan cho bé Mai Anh');
        $searchResponse->assertDontSee('Thuốc Amlodipin định kỳ');
    }

    public function test_user_can_open_create_expense_form(): void
    {
        $response = $this->actingAs($this->user)->get(route('expenses.create'));

        $response->assertStatus(200);
        $response->assertSee('Ghi nhận hóa đơn thuốc &amp; Viện phí', false);
        $response->assertSee('Nội dung chi tiêu / Tên hóa đơn');
        $response->assertSee('Số tiền chi tiêu (VNĐ)');
        $response->assertSee('Ảnh chụp Hóa đơn');
        $response->assertSee('Nguyễn Văn Thịnh');
    }

    public function test_user_can_create_expense_with_receipt_upload(): void
    {
        Storage::fake('public');

        $receiptFile = UploadedFile::fake()->create('hoadon_bachmai.jpg', 150, 'image/jpeg');

        $course = TreatmentCourse::create([
            'family_id' => $this->family->id,
            'family_member_id' => $this->grandpa->id,
            'title' => 'Đợt kiểm tra tim mạch tháng 9',
            'diagnosis' => 'Cao huyết áp độ 2',
            'start_date' => Carbon::today(),
            'status' => 'dang_dieu_tri',
        ]);

        $payload = [
            'title' => 'Đơn thuốc khám ngoại trú BV Bạch Mai',
            'amount' => 1250000,
            'purchase_date' => Carbon::today()->format('Y-m-d'),
            'family_member_id' => $this->grandpa->id,
            'treatment_course_id' => $course->id,
            'receipt_image' => $receiptFile,
            'notes' => 'Bác sĩ kê đơn 30 ngày',
        ];

        $response = $this->actingAs($this->user)->post(route('expenses.store'), $payload);

        $response->assertRedirect(route('expenses.index'));
        $response->assertSessionHas('success');

        $expense = MedicineExpense::where('title', 'Đơn thuốc khám ngoại trú BV Bạch Mai')->first();
        $this->assertNotNull($expense);
        $this->assertEquals(1250000, $expense->amount);
        $this->assertEquals($this->grandpa->id, $expense->family_member_id);
        $this->assertEquals($course->id, $expense->treatment_course_id);
        $this->assertNotNull($expense->receipt_image_path);

        Storage::disk('public')->assertExists($expense->receipt_image_path);
    }

    public function test_user_can_open_edit_expense_form(): void
    {
        $expense = MedicineExpense::create([
            'family_id' => $this->family->id,
            'family_member_id' => $this->grandpa->id,
            'title' => 'Mua thuốc huyết áp',
            'amount' => 250000,
            'purchase_date' => Carbon::today(),
        ]);

        $response = $this->actingAs($this->user)->get(route('expenses.edit', $expense));

        $response->assertStatus(200);
        $response->assertSee('Cập nhật hóa đơn');
        $response->assertSee('Mua thuốc huyết áp');
        $response->assertSee('250000');
    }

    public function test_user_can_update_expense(): void
    {
        $expense = MedicineExpense::create([
            'family_id' => $this->family->id,
            'family_member_id' => $this->grandpa->id,
            'title' => 'Hóa đơn cũ',
            'amount' => 200000,
            'purchase_date' => Carbon::today(),
        ]);

        $response = $this->actingAs($this->user)->put(route('expenses.update', $expense), [
            'title' => 'Hóa đơn mới đã chỉnh sửa',
            'amount' => 350000,
            'purchase_date' => Carbon::today()->format('Y-m-d'),
            'family_member_id' => $this->grandpa->id,
            'notes' => 'Đã thêm tiền mua khẩu trang y tế',
        ]);

        $response->assertRedirect(route('expenses.index'));
        $response->assertSessionHas('success');

        $expense->refresh();
        $this->assertEquals('Hóa đơn mới đã chỉnh sửa', $expense->title);
        $this->assertEquals(350000, $expense->amount);
        $this->assertEquals('Đã thêm tiền mua khẩu trang y tế', $expense->notes);
    }

    public function test_user_can_delete_expense(): void
    {
        Storage::fake('public');
        $filePath = 'receipts/test_del.jpg';
        Storage::disk('public')->put($filePath, 'fake content');

        $expense = MedicineExpense::create([
            'family_id' => $this->family->id,
            'title' => 'Khoản chi sẽ bị xóa',
            'amount' => 100000,
            'purchase_date' => Carbon::today(),
            'receipt_image_path' => $filePath,
        ]);

        $response = $this->actingAs($this->user)->delete(route('expenses.destroy', $expense));

        $response->assertRedirect(route('expenses.index'));
        $this->assertDatabaseMissing('medicine_expenses', ['id' => $expense->id]);
        Storage::disk('public')->assertMissing($filePath);
    }

    public function test_user_can_export_expenses_to_csv(): void
    {
        MedicineExpense::create([
            'family_id' => $this->family->id,
            'title' => 'Thuốc cảm cúm cho mẹ',
            'amount' => 120000,
            'purchase_date' => Carbon::today(),
            'notes' => 'Mua tại FPT Long Châu',
        ]);

        $response = $this->actingAs($this->user)->get(route('expenses.export-csv'));

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');

        // Lấy nội dung CSV xuất ra
        $content = $response->streamedContent();

        // Kiểm tra UTF-8 BOM
        $bom = chr(0xEF) . chr(0xBB) . chr(0xBF);
        $this->assertStringStartsWith($bom, $content);

        // Kiểm tra nội dung tiêu đề và dữ liệu
        $this->assertStringContainsString('Nội dung hóa đơn / Khoản chi', $content);
        $this->assertStringContainsString('Số tiền (VNĐ)', $content);
        $this->assertStringContainsString('Thuốc cảm cúm cho mẹ', $content);
        $this->assertStringContainsString('120000', $content);
        $this->assertStringContainsString('FPT Long Châu', $content);
    }

    public function test_user_cannot_access_or_modify_other_familys_expense(): void
    {
        $otherFamily = Family::create([
            'name' => 'Gia đình khác',
            'code' => 'FAM-OTHER-9999',
            'address' => 'Hà Nội',
        ]);

        $otherExpense = MedicineExpense::create([
            'family_id' => $otherFamily->id,
            'title' => 'Chi phí nhà hàng xóm',
            'amount' => 999000,
            'purchase_date' => Carbon::today(),
        ]);

        // Cố gắng sửa khoản chi của gia đình khác -> 403
        $editResponse = $this->actingAs($this->user)->get(route('expenses.edit', $otherExpense));
        $editResponse->assertStatus(403);

        $updateResponse = $this->actingAs($this->user)->put(route('expenses.update', $otherExpense), [
            'title' => 'Cố tình sửa',
            'amount' => 50000,
            'purchase_date' => Carbon::today()->format('Y-m-d'),
        ]);
        $updateResponse->assertStatus(403);

        $deleteResponse = $this->actingAs($this->user)->delete(route('expenses.destroy', $otherExpense));
        $deleteResponse->assertStatus(403);
    }

    public function test_validation_errors_when_required_fields_are_missing(): void
    {
        $response = $this->actingAs($this->user)->post(route('expenses.store'), [
            'title' => '',
            'amount' => '',
            'purchase_date' => '',
        ]);

        $response->assertSessionHasErrors(['title', 'amount', 'purchase_date']);
    }
}

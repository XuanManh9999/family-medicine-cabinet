<?php

namespace Tests\Feature;

use App\Models\Family;
use App\Models\FamilyMember;
use App\Models\MedicationLog;
use App\Models\MedicationSchedule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ScheduleCalendarTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Family $family;
    protected FamilyMember $member;
    protected MedicationSchedule $schedule;

    protected function setUp(): void
    {
        parent::setUp();

        $this->family = Family::create([
            'name' => 'Gia đình Lê Quang Khải',
            'code' => 'FAMTEST3',
        ]);

        $this->user = User::create([
            'family_id' => $this->family->id,
            'name' => 'Lê Quang Khải',
            'email' => 'khai@example.com',
            'password' => bcrypt('password123'),
            'role' => 'family_admin',
        ]);

        $this->member = FamilyMember::create([
            'family_id' => $this->family->id,
            'user_id' => $this->user->id,
            'name' => 'Lê Quang Khải',
            'relationship' => 'chong',
            'dob' => '1995-03-25',
            'gender' => 'nam',
        ]);

        $this->schedule = MedicationSchedule::create([
            'family_id' => $this->family->id,
            'family_member_id' => $this->member->id,
            'medicine_name' => 'Amlodipine 5mg',
            'dosage' => '1 viên',
            'unit' => 'viên',
            'time_of_day' => 'sang',
            'scheduled_time' => '07:30:00',
            'meal_timing' => 'sau_an',
            'start_date' => now()->startOfMonth()->toDateString(),
            'end_date' => now()->endOfMonth()->toDateString(),
            'is_active' => true,
        ]);
    }

    public function test_user_can_view_calendar_tab(): void
    {
        $response = $this->actingAs($this->user)->get(route('schedules.index', ['view' => 'calendar']));

        $response->assertStatus(200);
        $response->assertSee('Lịch biểu uống thuốc tương tác');
        $response->assertSee('fullCalendarApp');
    }

    public function test_user_can_fetch_calendar_events_json(): void
    {
        $start = now()->startOfMonth()->toDateString();
        $end = now()->endOfMonth()->toDateString();

        $response = $this->actingAs($this->user)->getJson(route('schedules.calendar-events', [
            'start' => $start,
            'end' => $end,
        ]));

        $response->assertStatus(200);
        $events = $response->json();
        $this->assertIsArray($events);
        $this->assertNotEmpty($events);

        $firstEvent = $events[0];
        $this->assertArrayHasKey('id', $firstEvent);
        $this->assertArrayHasKey('title', $firstEvent);
        $this->assertArrayHasKey('start', $firstEvent);
        $this->assertArrayHasKey('extendedProps', $firstEvent);
        $this->assertEquals('Amlodipine 5mg', $firstEvent['extendedProps']['medicine_name']);
        $this->assertEquals('1 viên', $firstEvent['extendedProps']['dosage']);
        $this->assertEquals('sau_an', $firstEvent['extendedProps']['meal_timing']);
    }

    public function test_calendar_range_is_capped_at_65_days(): void
    {
        $this->schedule->update([
            'start_date' => now()->subYear()->toDateString(),
            'end_date' => null,
        ]);

        $start = now()->subDays(100)->startOfDay();
        $response = $this->actingAs($this->user)->getJson(route('schedules.calendar-events', [
            'start' => $start->toDateString(),
            'end' => $start->copy()->addDays(300)->toDateString(),
        ]));

        $response->assertOk();
        $dates = collect($response->json())->pluck('extendedProps.scheduled_date')->unique();
        $this->assertCount(66, $dates);
        $this->assertLessThanOrEqual($start->copy()->addDays(65)->toDateString(), $dates->max());
    }

    public function test_calendar_rejects_invalid_dates(): void
    {
        $this->actingAs($this->user)->getJson(route('schedules.calendar-events', ['start' => 'khong-hop-le']))
            ->assertStatus(422);

        $this->actingAs($this->user)->getJson(route('schedules.calendar-events', [
            'start' => now()->toDateString(),
            'end' => now()->subDays(5)->toDateString(),
        ]))->assertStatus(422);
    }

    public function test_calendar_accepts_fullcalendar_iso_range_and_returns_log_note(): void
    {
        $logDate = now()->startOfMonth();
        MedicationLog::create([
            'schedule_id' => $this->schedule->id,
            'family_member_id' => $this->member->id,
            'scheduled_time' => $logDate->copy()->setTime(7, 30),
            'status' => 'quen_uong',
            'note' => 'Đi công tác quên thuốc',
        ]);

        // Lịch đã ngừng: chỉ còn hiện ngày có nhật ký
        $this->schedule->update(['is_active' => false]);

        $response = $this->actingAs($this->user)->getJson(route('schedules.calendar-events', [
            'start' => now()->startOfMonth()->format('Y-m-d\TH:i:sP'),
            'end' => now()->endOfMonth()->startOfDay()->format('Y-m-d\TH:i:sP'),
        ]));

        $response->assertOk();
        $events = $response->json();
        $this->assertCount(1, $events);
        $this->assertEquals($logDate->toDateString(), $events[0]['extendedProps']['scheduled_date']);
        $this->assertEquals('quen_uong', $events[0]['extendedProps']['status']);
        $this->assertEquals('Đi công tác quên thuốc', $events[0]['extendedProps']['note']);
    }
}

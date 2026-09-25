<?php

namespace Tests\Feature;

use App\Models\Family;
use App\Models\FamilyMember;
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
}

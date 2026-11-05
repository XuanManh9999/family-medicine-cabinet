<?php

namespace App\Services;

use App\Models\MedicationReminder;
use App\Models\MedicationSchedule;
use App\Models\SmartAlert;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Nhắc uống thuốc tự động theo lịch:
 * - "Đến giờ uống": trong vòng DUE_WINDOW phút sau giờ hẹn mà chưa điểm danh → thông báo + email cho người uống
 *   (người già / trẻ nhỏ không có tài khoản thì gửi cho Chủ hộ)
 * - "Quá giờ chưa điểm danh": sau MISSED_AFTER phút vẫn chưa ghi nhận → cảnh báo + email cho Chủ hộ theo dõi từ xa
 */
class MedicationReminderService
{
    public const DUE_WINDOW_MINUTES = 30;
    public const MISSED_AFTER_MINUTES = 60;
    public const MISSED_WINDOW_HOURS = 6;
    public const ALERT_RETENTION_DAYS = 2;

    public function run(?Carbon $now = null): array
    {
        $now = ($now ?? Carbon::now())->copy();
        $stats = ['checked' => 0, 'due_sent' => 0, 'missed_sent' => 0, 'emails' => 0, 'cleaned' => 0];

        // Xét hôm nay và hôm qua (cữ tối muộn có thể bị "quá giờ" sau nửa đêm)
        foreach ([$now->copy()->subDay()->startOfDay(), $now->copy()->startOfDay()] as $day) {
            $schedules = MedicationSchedule::activeOnDate($day)
                ->with(['familyMember.user', 'family.users', 'logs' => fn ($q) => $q->whereDate('scheduled_time', $day->toDateString())])
                ->get();

            foreach ($schedules as $schedule) {
                $stats['checked']++;
                $scheduledFor = $day->copy()->setTimeFromTimeString((string) $schedule->scheduled_time);

                if ($scheduledFor->gt($now) || $schedule->getLogForDate($day)) {
                    continue;
                }
                // Cữ đã trôi qua từ trước khi lịch được tạo (VD: 13h30 mới kê đơn có cữ sáng) → không nhắc / không báo quên
                if (!$schedule->wasDueAfterCreation($scheduledFor)) {
                    continue;
                }

                $minutesLate = $scheduledFor->diffInMinutes($now);

                if ($minutesLate <= self::DUE_WINDOW_MINUTES) {
                    $result = $this->remind($schedule, $scheduledFor, MedicationReminder::STAGE_DUE);
                    $stats['due_sent'] += $result['sent'];
                    $stats['emails'] += $result['emailed'];
                } elseif ($minutesLate >= self::MISSED_AFTER_MINUTES && $minutesLate <= self::MISSED_WINDOW_HOURS * 60) {
                    $result = $this->remind($schedule, $scheduledFor, MedicationReminder::STAGE_MISSED);
                    $stats['missed_sent'] += $result['sent'];
                    $stats['emails'] += $result['emailed'];
                }
            }
        }

        $stats['cleaned'] = $this->cleanup($now);

        return $stats;
    }

    /**
     * Đã điểm danh cữ thuốc → gỡ các thông báo nhắc của cữ đó
     */
    public function clearForDose(MedicationSchedule $schedule, Carbon|string $date): void
    {
        $day = is_string($date) ? Carbon::parse($date) : $date;

        $alertIds = MedicationReminder::where('medication_schedule_id', $schedule->id)
            ->whereDate('scheduled_for', $day->toDateString())
            ->whereNotNull('smart_alert_id')
            ->pluck('smart_alert_id');

        if ($alertIds->isNotEmpty()) {
            SmartAlert::whereIn('id', $alertIds)->delete();
        }
    }

    private function remind(MedicationSchedule $schedule, Carbon $scheduledFor, string $stage): array
    {
        $reminder = MedicationReminder::firstOrCreate([
            'medication_schedule_id' => $schedule->id,
            'scheduled_for' => $scheduledFor,
            'stage' => $stage,
        ]);

        if (!$reminder->wasRecentlyCreated) {
            return ['sent' => 0, 'emailed' => 0];
        }

        $member = $schedule->familyMember;
        $memberName = $member?->name ?? 'Thành viên';
        $time = $scheduledFor->format('H:i');
        $dose = trim("{$schedule->dosage} {$schedule->medicine_name}");
        $meal = $schedule->meal_timing_label;
        $actionUrl = route('schedules.index', ['date' => $scheduledFor->toDateString()], false);

        if ($stage === MedicationReminder::STAGE_DUE) {
            $alert = SmartAlert::create([
                'family_id' => $schedule->family_id,
                'medication_schedule_id' => $schedule->id,
                'type' => 'schedule_reminder',
                'level' => 'info',
                'title' => "Đến giờ uống thuốc: {$memberName} – {$schedule->medicine_name}",
                'content' => "{$time} hôm nay {$memberName} cần uống {$dose} ({$meal}). Sau khi uống, hãy điểm danh \"Đã uống\" để cả nhà cùng theo dõi.",
                'is_read' => false,
                'action_url' => $actionUrl,
            ]);
            $recipients = $member?->user?->email ? collect([$member->user]) : $this->familyAdmins($schedule);
            $subject = "Nhắc uống thuốc {$time}: {$schedule->medicine_name}";
            $body = "Đến giờ uống thuốc ({$time}).\n\nNgười uống: {$memberName}\nThuốc: {$dose}\nCách dùng: {$meal}\n\nSau khi uống, vui lòng mở MedCabinet và điểm danh \"Đã uống\".";
        } else {
            // Quá giờ: gỡ thông báo "đến giờ" của cữ này, báo cho Chủ hộ theo dõi
            $this->clearStageAlert($schedule, $scheduledFor, MedicationReminder::STAGE_DUE);

            $alert = SmartAlert::create([
                'family_id' => $schedule->family_id,
                'medication_schedule_id' => $schedule->id,
                'type' => 'missed_dose',
                'level' => 'warning',
                'title' => "Chưa điểm danh cữ thuốc {$time}: {$memberName}",
                'content' => "{$memberName} chưa được ghi nhận uống {$dose} lúc {$time} (đã quá " . self::MISSED_AFTER_MINUTES . " phút). Hãy liên hệ nhắc người thân hoặc cập nhật điểm danh.",
                'is_read' => false,
                'action_url' => $actionUrl,
            ]);
            $recipients = $this->familyAdmins($schedule);
            $subject = "Chưa uống thuốc {$time}: {$memberName}";
            $body = "{$memberName} chưa được điểm danh uống thuốc.\n\nThuốc: {$dose}\nGiờ hẹn: {$time} ngày {$scheduledFor->format('d/m/Y')}\n\nVui lòng liên hệ nhắc người thân hoặc cập nhật điểm danh trên MedCabinet.";
        }

        $emailed = $this->sendEmail($recipients->pluck('email')->filter()->unique()->values()->all(), $subject, $body);
        $reminder->update([
            'smart_alert_id' => $alert->id,
            'emailed_to' => $emailed ? implode(', ', $emailed) : null,
        ]);

        return ['sent' => 1, 'emailed' => count($emailed)];
    }

    private function clearStageAlert(MedicationSchedule $schedule, Carbon $scheduledFor, string $stage): void
    {
        $alertId = MedicationReminder::where('medication_schedule_id', $schedule->id)
            ->where('scheduled_for', $scheduledFor)
            ->where('stage', $stage)
            ->value('smart_alert_id');

        if ($alertId) {
            SmartAlert::whereKey($alertId)->delete();
        }
    }

    private function familyAdmins(MedicationSchedule $schedule)
    {
        return ($schedule->family?->users ?? collect())
            ->filter(fn (User $user) => $user->isFamilyAdmin());
    }

    /**
     * Gửi email nhắc (lỗi gửi mail không được làm dừng việc nhắc các cữ khác)
     */
    private function sendEmail(array $emails, string $subject, string $body): array
    {
        $sent = [];
        foreach ($emails as $email) {
            try {
                Mail::raw($body . "\n\nTrân trọng,\nMedCabinet - Tủ thuốc gia đình", function ($message) use ($email, $subject) {
                    $message->to($email)->subject($subject . ' - MedCabinet');
                });
                $sent[] = $email;
            } catch (\Throwable $e) {
                Log::warning("Gửi email nhắc uống thuốc tới {$email} thất bại: {$e->getMessage()}");
            }
        }

        return $sent;
    }

    /**
     * Dọn thông báo nhắc cũ và nhật ký nhắc quá hạn
     */
    private function cleanup(Carbon $now): int
    {
        $cleaned = SmartAlert::whereIn('type', ['schedule_reminder', 'missed_dose'])
            ->whereNotNull('medication_schedule_id')
            ->where('created_at', '<', $now->copy()->subDays(self::ALERT_RETENTION_DAYS))
            ->delete();

        MedicationReminder::where('scheduled_for', '<', $now->copy()->subDays(7))->delete();

        return $cleaned;
    }
}

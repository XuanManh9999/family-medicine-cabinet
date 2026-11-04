<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * - Ghi lại lô thuốc & số lượng đã trừ khi điểm danh "Đã uống" để hoàn trả chính xác khi đổi trạng thái
     * - Mỗi lịch uống chỉ có tối đa 1 nhật ký cho mỗi mốc giờ (chống trừ kho 2 lần do bấm đúp / gửi lặp)
     */
    public function up(): void
    {
        Schema::table('medication_logs', function (Blueprint $table) {
            // Không gắn khóa ngoại để chạy được trên cả SQLite lẫn MySQL; lô thuốc bị xóa thì bỏ qua khi hoàn trả
            $table->unsignedBigInteger('cabinet_item_id')->nullable()->after('note');
            $table->decimal('deducted_quantity', 8, 2)->nullable()->after('cabinet_item_id');
            $table->index('cabinet_item_id');
        });

        // Gộp các nhật ký trùng (cùng lịch, cùng mốc giờ) trước khi tạo unique index: giữ bản ghi mới nhất
        $duplicates = DB::table('medication_logs')
            ->select('schedule_id', 'scheduled_time', DB::raw('MAX(id) as keep_id'))
            ->groupBy('schedule_id', 'scheduled_time')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        foreach ($duplicates as $dup) {
            DB::table('medication_logs')
                ->where('schedule_id', $dup->schedule_id)
                ->where('scheduled_time', $dup->scheduled_time)
                ->where('id', '<>', $dup->keep_id)
                ->delete();
        }

        Schema::table('medication_logs', function (Blueprint $table) {
            $table->unique(['schedule_id', 'scheduled_time'], 'medication_logs_schedule_time_unique');
        });
    }

    public function down(): void
    {
        Schema::table('medication_logs', function (Blueprint $table) {
            $table->dropUnique('medication_logs_schedule_time_unique');
            $table->dropIndex(['cabinet_item_id']);
            $table->dropColumn(['cabinet_item_id', 'deducted_quantity']);
        });
    }
};

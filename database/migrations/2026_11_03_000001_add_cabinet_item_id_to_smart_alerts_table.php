<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Gắn cảnh báo với lô thuốc cụ thể để chống trùng lặp & tự động gỡ cảnh báo hết hiệu lực
     */
    public function up(): void
    {
        Schema::table('smart_alerts', function (Blueprint $table) {
            $table->foreignId('cabinet_item_id')
                ->nullable()
                ->after('user_id')
                ->constrained('cabinet_items')
                ->nullOnDelete();

            $table->index(['family_id', 'type', 'cabinet_item_id']);
        });

        // Dọn các cảnh báo tự sinh theo cơ chế cũ (khóa theo tiêu đề, bị nhân bản giữa service & command).
        // Lần quét kế tiếp sẽ sinh lại đúng 1 cảnh báo/lô thuốc gắn cabinet_item_id.
        DB::table('smart_alerts')
            ->whereNull('cabinet_item_id')
            ->where(function ($query) {
                $query->where(fn ($q) => $q->where('type', 'expired')->where(fn ($t) => $t
                        ->where('title', 'like', 'CẢNH BÁO NGUY HIỂM: Thuốc % đã hết hạn!')
                        ->orWhere('title', 'like', "CẢNH BÁO NGUY HIỂM: Thuốc '%' đã quá hạn sử dụng!")))
                    ->orWhere(fn ($q) => $q->where('type', 'near_expiry')->where('title', 'like', 'Cảnh báo thuốc cận date: %'))
                    ->orWhere(fn ($q) => $q->where('type', 'low_stock')->where(fn ($t) => $t
                        ->where('title', 'like', 'Tủ thuốc đã hết: %')
                        ->orWhere('title', 'like', 'Tồn kho sắp hết: %')
                        ->orWhere('title', 'like', "Vật tư sắp hết: '%'")));
            })
            ->delete();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('smart_alerts', function (Blueprint $table) {
            $table->dropIndex(['family_id', 'type', 'cabinet_item_id']);
            $table->dropConstrainedForeignId('cabinet_item_id');
        });
    }
};

<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Paginator::useBootstrapFive();

        // Đọc tham số query dạng chuỗi an toàn: ?q[]=x (mảng) sẽ được coi là rỗng thay vì gây lỗi 500
        Request::macro('textQuery', function (string $key): string {
            /** @var Request $this */
            $value = $this->query($key);

            return is_string($value) ? trim($value) : '';
        });

        // Giới hạn gọi Trợ lý AI (Gemini) để tránh cạn quota và treo tiến trình PHP
        RateLimiter::for('ai', function (Request $request) {
            return Limit::perMinute(15)
                ->by($request->user()?->id ?: $request->ip())
                ->response(function () {
                    return response()->json([
                        'success' => false,
                        'message' => 'Bạn đã gửi quá nhiều yêu cầu tới Trợ lý AI. Vui lòng thử lại sau ít phút.',
                    ], 429);
                });
        });
    }
}

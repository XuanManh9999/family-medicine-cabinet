<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Ứng dụng không dùng tham số query dạng mảng (?q[]=x). Loại bỏ chúng ngay từ đầu để
 * controller / view đọc request('q'), request('status')... luôn nhận chuỗi, không gây lỗi 500.
 */
class DropArrayQueryParameters
{
    public function handle(Request $request, Closure $next): Response
    {
        $query = $request->query->all();
        $scalarOnly = array_filter($query, fn ($value) => !is_array($value));

        if (count($scalarOnly) !== count($query)) {
            $request->query->replace($scalarOnly);
        }

        return $next($request);
    }
}

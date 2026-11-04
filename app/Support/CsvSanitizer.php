<?php

namespace App\Support;

/**
 * Tiện ích ghi CSV an toàn cho Excel: chống chèn công thức (CSV/Formula Injection)
 */
class CsvSanitizer
{
    /** Các ký tự đầu ô khiến Excel/LibreOffice hiểu là công thức */
    private const FORMULA_PREFIXES = ['=', '+', '-', '@', "\t", "\r"];

    /**
     * Thêm dấu nháy đơn trước giá trị chuỗi bắt đầu bằng ký tự công thức (= + - @ tab CR)
     */
    public static function cell(mixed $value): mixed
    {
        if (!is_string($value) || $value === '') {
            return $value;
        }

        return in_array($value[0], self::FORMULA_PREFIXES, true) ? "'" . $value : $value;
    }

    /**
     * Bỏ dấu nháy đơn do cell() thêm vào khi đọc lại file đã xuất
     */
    public static function uncell(string $value): string
    {
        if (strlen($value) >= 2 && $value[0] === "'" && in_array($value[1], self::FORMULA_PREFIXES, true)) {
            return substr($value, 1);
        }

        return $value;
    }

    /**
     * Ghi một dòng CSV đã được làm sạch
     *
     * @param resource $handle
     */
    public static function writeRow($handle, array $row): void
    {
        fputcsv($handle, array_map([self::class, 'cell'], $row), ',', '"', '');
    }
}

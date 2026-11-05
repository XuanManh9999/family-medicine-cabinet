<?php

namespace App\Support;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * Quy tắc kiểm tra dữ liệu lô thuốc dùng chung: form thêm/sửa, nhập Excel, đề nghị & duyệt nhập thuốc
 */
class CabinetItemValidator
{
    public static function make(array $input, bool $withMedicine): \Illuminate\Validation\Validator
    {
        $rules = [
            'custom_name' => ['required', 'string', 'max:255'],
            'batch_number' => ['nullable', 'string', 'max:255'],
            'manufacturing_date' => ['nullable', 'date', 'before_or_equal:today'],
            'expiry_date' => ['required', 'date'],
            'quantity' => ['required', 'numeric', 'min:0', 'max:999999.99'],
            'unit' => ['required', 'string', 'max:30'],
            'storage_location' => ['nullable', 'string', 'max:255'],
            'price_per_unit' => ['nullable', 'numeric', 'min:0', 'max:999999999.99'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
        if ($withMedicine) {
            $rules['medicine_id'] = ['nullable', 'integer', Rule::exists('medicines', 'id')];
        }

        $validator = Validator::make($input, $rules, [
            'custom_name.required' => 'Thiếu Tên thuốc.',
            'custom_name.max' => 'Tên thuốc tối đa 255 ký tự.',
            'batch_number.max' => 'Số lô tối đa 255 ký tự.',
            'manufacturing_date.before_or_equal' => 'Ngày sản xuất không thể ở tương lai.',
            'expiry_date.required' => 'Thiếu hạn sử dụng.',
            'quantity.required' => 'Thiếu số lượng.',
            'quantity.numeric' => 'Số lượng phải là số.',
            'quantity.min' => 'Số lượng không được âm.',
            'quantity.max' => 'Số lượng tối đa là 999.999,99.',
            'unit.max' => 'Đơn vị tính tối đa 30 ký tự.',
            'storage_location.max' => 'Vị trí lưu trữ tối đa 255 ký tự.',
            'price_per_unit.numeric' => 'Đơn giá phải là số.',
            'price_per_unit.min' => 'Đơn giá không được âm.',
            'price_per_unit.max' => 'Đơn giá vượt quá giới hạn cho phép.',
            'notes.max' => 'Ghi chú tối đa 5000 ký tự.',
        ]);

        $validator->after(function ($validator) use ($input) {
            // Thành tiền ghi vào sổ chi tiêu là decimal(12,2): chặn đơn giá gõ nhầm quá lớn gây lỗi CSDL
            $quantity = $input['quantity'] ?? null;
            $price = $input['price_per_unit'] ?? null;
            if (is_numeric($quantity) && is_numeric($price) && (float) $quantity * (float) $price > 9999999999.99) {
                $validator->errors()->add('price_per_unit', 'Thành tiền (số lượng × đơn giá) quá lớn, vui lòng kiểm tra lại đơn giá.');
            }

            $manufactured = $input['manufacturing_date'] ?? null;
            $expires = $input['expiry_date'] ?? null;
            if (is_string($manufactured) && is_string($expires) && $manufactured !== '' && $expires !== ''
                && strtotime($expires) !== false && strtotime($manufactured) !== false
                && strtotime($expires) < strtotime($manufactured)) {
                $validator->errors()->add('expiry_date', 'Hạn sử dụng phải bằng hoặc sau ngày sản xuất.');
            }
        });

        return $validator;
    }
}

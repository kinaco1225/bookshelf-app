<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ReadingPlanUpdateRequest extends FormRequest
{
    /**
     * 認可はルートのミドルウェア（auth）＋コントローラの $this->authorize() で行うため、
     * ここでは許可する。
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * 編集できるのは期日のみ（書籍は変更不可）。
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'target_date' => ['required', 'date', 'after_or_equal:today'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'target_date.required' => '期日を入力してください',
            'target_date.date' => '期日は正しい日付で入力してください',
            'target_date.after_or_equal' => '期日は今日以降の日付を指定してください',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'target_date' => '期日',
        ];
    }
}

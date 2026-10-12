<?php

namespace App\Http\Requests;

use App\Enums\ReadingPlanStatus;
use App\Models\ReadingPlan;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

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

    /**
     * 更新すると計画は「進行中」になるため、同じ書籍に自身以外の進行中の計画があれば更新させない（重複制御）。
     * 期限切れの計画を進行中に戻すときに重複しうる。
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            /** @var ReadingPlan $readingPlan */
            $readingPlan = $this->route('readingPlan');

            $alreadyInProgress = ReadingPlan::query()
                ->where('user_id', $readingPlan->user_id)
                ->where('book_id', $readingPlan->book_id)
                ->where('status', ReadingPlanStatus::InProgress)
                ->whereKeyNot($readingPlan->id)
                ->exists();

            if ($alreadyInProgress) {
                $validator->errors()->add('target_date', 'この書籍にはすでに進行中の読書計画があります');
            }
        });
    }
}

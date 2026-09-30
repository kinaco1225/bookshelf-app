<?php

namespace App\Http\Requests;

use App\Enums\ReadingPlanStatus;
use App\Models\ReadingPlan;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class ReadingPlanStoreRequest extends FormRequest
{
    /**
     * 認可はルートのミドルウェア（auth）で行うため、ここでは許可する。
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'book_id' => ['required', 'integer', 'exists:books,id'],
            'target_date' => ['required', 'date', 'after_or_equal:today'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'book_id.required' => '書籍を選択してください',
            'book_id.exists' => '選択された書籍は存在しません',
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
            'book_id' => '書籍',
            'target_date' => '期日',
        ];
    }

    /**
     * 同じ書籍に対して進行中の計画を重複して作れないようにする（重複制御）。
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $bookId = $this->input('book_id');

            if (! $bookId) {
                return;
            }

            $alreadyInProgress = ReadingPlan::query()
                ->where('user_id', $this->user()->id)
                ->where('book_id', $bookId)
                ->where('status', ReadingPlanStatus::InProgress)
                ->exists();

            if ($alreadyInProgress) {
                $validator->errors()->add('book_id', 'この書籍にはすでに進行中の読書計画があります');
            }
        });
    }
}

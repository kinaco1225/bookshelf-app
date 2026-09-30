<?php

namespace App\Http\Requests;

use App\Models\Review;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class ReviewRequest extends FormRequest
{
    /**
     * 認可はルートのミドルウェア（auth）と Policy 側で行うため、ここでは許可する。
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * レビューの投稿・編集で共通のバリデーションルール。
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'rating' => ['required', 'integer', 'between:1,5'],
            'comment' => ['required', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'rating.required' => '評価を選択してください',
            'rating.integer' => '評価は数値で選択してください',
            'rating.between' => '評価は1〜5で選択してください',
            'comment.required' => 'コメントは必須です',
            'comment.max' => 'コメントは1000文字以内で入力してください',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'rating' => '評価',
            'comment' => 'コメント',
        ];
    }

    /**
     * 同じ書籍への二重投稿を防ぐ（投稿時のみ）。
     */
    public function withValidator(Validator $validator): void
    {
        $book = $this->route('book');

        // 編集時は {book} を持たないためチェック不要。
        if ($book === null) {
            return;
        }

        $validator->after(function (Validator $validator) use ($book): void {
            $alreadyReviewed = Review::query()
                ->where('user_id', $this->user()->id)
                ->where('book_id', $book->id)
                ->exists();

            if ($alreadyReviewed) {
                $validator->errors()->add('rating', 'この書籍にはすでにレビューを投稿しています');
            }
        });
    }
}

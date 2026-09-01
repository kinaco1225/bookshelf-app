<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GenreRequest extends FormRequest
{
    /**
     * 認可はルートのミドルウェア（auth）で行うため、ここでは許可する。
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * ジャンルの登録・編集で共通のバリデーションルール。
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:255',
                // 編集時は自身のレコードを一意性チェックから除外する。
                Rule::unique('genres', 'name')->ignore($this->route('genre')),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'ジャンル名を入力してください。',
            'name.max' => 'ジャンル名は255文字以内で入力してください。',
            'name.unique' => 'そのジャンル名はすでに登録されています。',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => 'ジャンル名',
        ];
    }
}

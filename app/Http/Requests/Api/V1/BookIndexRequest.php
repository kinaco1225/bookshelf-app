<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class BookIndexRequest extends FormRequest
{
    /**
     * 公開APIのため誰でも実行できる。
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * 検索・絞り込み・ページネーションのクエリパラメータを検証する。
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'keyword' => ['nullable', 'string', 'max:255'],
            'genre_id' => ['nullable', 'integer', 'exists:genres,id'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'keyword.string' => 'キーワードは文字列で指定してください',
            'keyword.max' => 'キーワードは255文字以内で指定してください',
            'genre_id.integer' => 'ジャンルIDは整数で指定してください',
            'genre_id.exists' => '指定されたジャンルは存在しません',
            'page.integer' => 'ページ番号は整数で指定してください',
            'page.min' => 'ページ番号は1以上で指定してください',
            'per_page.integer' => '1ページあたりの件数は整数で指定してください',
            'per_page.min' => '1ページあたりの件数は1以上で指定してください',
            'per_page.max' => '1ページあたりの件数は100以下で指定してください',
        ];
    }
}

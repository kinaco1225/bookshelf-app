<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BookRequest extends FormRequest
{
    /**
     * 公開APIのため誰でも実行できる。
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * 書籍の登録・更新で共通のバリデーションルール。
     *
     * Web版の書籍登録と同等のルールに加え、登録者ID（user_id）の妥当性を検証する。
     * 更新時（ルートに {book} がある場合）は ISBN の一意性チェックから自身を除外する。
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'title' => ['required', 'string', 'max:255'],
            'author' => ['required', 'string', 'max:255'],
            'isbn' => [
                'required',
                'string',
                'regex:/\A[0-9]{13}\z/',
                Rule::unique('books', 'isbn')->ignore($this->route('book')),
            ],
            'published_date' => ['required', 'date'],
            'description' => ['nullable', 'string'],
            'image_url' => ['nullable', 'url', 'max:2048'],
            'genres' => ['required', 'array', 'min:1'],
            'genres.*' => ['integer', 'exists:genres,id'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'user_id.required' => '登録者IDを指定してください。',
            'user_id.integer' => '登録者IDは整数で指定してください。',
            'user_id.exists' => '指定された登録者は存在しません。',
            'title.required' => 'タイトルを入力してください。',
            'title.max' => 'タイトルは255文字以内で入力してください。',
            'author.required' => '著者を入力してください。',
            'author.max' => '著者は255文字以内で入力してください。',
            'isbn.required' => 'ISBNを入力してください。',
            'isbn.regex' => 'ISBNは13桁の数字で入力してください。',
            'isbn.unique' => 'このISBNの書籍はすでに登録されています。',
            'published_date.required' => '出版日を入力してください。',
            'published_date.date' => '出版日は正しい日付で入力してください。',
            'image_url.url' => '画像URLは正しいURL形式で入力してください。',
            'image_url.max' => '画像URLは2048文字以内で入力してください。',
            'genres.required' => 'ジャンルを1つ以上指定してください。',
            'genres.array' => 'ジャンルは配列で指定してください。',
            'genres.min' => 'ジャンルを1つ以上指定してください。',
            'genres.*.integer' => 'ジャンルIDは整数で指定してください。',
            'genres.*.exists' => '指定されたジャンルは存在しません。',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'user_id' => '登録者ID',
            'title' => 'タイトル',
            'author' => '著者',
            'isbn' => 'ISBN',
            'published_date' => '出版日',
            'description' => '説明',
            'image_url' => '画像URL',
            'genres' => 'ジャンル',
        ];
    }
}

<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class GoogleBooksService
{
    private const ENDPOINT = 'https://www.googleapis.com/books/v1/volumes';

    /**
     * ISBN から Google Books API で書籍情報を検索する。
     * 該当書籍が無い場合は null を返す。通信・応答異常時は例外を投げる。
     *
     * @return array{title: string, author: ?string, description: ?string, image_url: ?string, published_date: ?string}|null
     */
    public function findByIsbn(string $isbn): ?array
    {
        // API キーが設定されていればキー付きでリクエストする（キーなしは共有の利用枠のため上限に達しやすい）。
        $response = Http::get(self::ENDPOINT, array_filter([
            'q' => "isbn:{$isbn}",
            'key' => config('services.google_books.key'),
        ]));

        if ($response->failed()) {
            throw new \RuntimeException('Google Books API へのリクエストに失敗しました。');
        }

        $items = $response->json('items', []);

        if (empty($items)) {
            return null;
        }

        $volumeInfo = $items[0]['volumeInfo'] ?? [];
        $authors = $volumeInfo['authors'] ?? null;

        return [
            'title' => $volumeInfo['title'] ?? '',
            'author' => $authors ? implode('・', $authors) : null,
            'description' => $volumeInfo['description'] ?? null,
            'image_url' => $volumeInfo['imageLinks']['thumbnail'] ?? null,
            'published_date' => $volumeInfo['publishedDate'] ?? null,
        ];
    }
}

<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BookResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'author' => $this->author,
            'isbn' => $this->isbn,
            // published_date は Carbon キャストのため、日付のみの文字列に整形して返す。
            'published_date' => $this->published_date?->format('Y-m-d'),
            'description' => $this->description,
            'image_url' => $this->image_url,
            'genres' => GenreResource::collection($this->whenLoaded('genres')),
            'average_rating' => $this->reviews_avg_rating !== null
                ? round((float) $this->reviews_avg_rating, 2)
                : null,
            'reviews_count' => $this->whenCounted('reviews'),
            // 一覧では reviews をロードしないため、詳細（show）でのみ含まれる
            'reviews' => ReviewResource::collection($this->whenLoaded('reviews')),
        ];
    }
}

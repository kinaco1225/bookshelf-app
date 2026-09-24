<?php

namespace App\Services;

use App\Models\Review;
use App\Models\User;
use Illuminate\Support\Collection;

class ReadingReportService
{
    /**
     * ログインユーザーのレビューをもとに、マイ読書レポート用の統計を組み立てる。
     *
     * @return array{
     *     summary: array{total_reviews: int, books_read: int, average_rating: float},
     *     rating_distribution: Collection<int, int>,
     *     top_rated_books: Collection<int, array{id: int, title: string, author: string, rating: int}>,
     *     genre_ratings: Collection<int, array{id: int, name: string, count: int, average_rating: float}>,
     * }
     */
    public function build(User $user): array
    {
        $reviews = $user->reviews()->with('book.genres')->orderBy('id')->get();

        return [
            'summary' => $this->buildSummary($reviews),
            'rating_distribution' => $this->buildRatingDistribution($reviews),
            'top_rated_books' => $this->buildTopRatedBooks($reviews),
            'genre_ratings' => $this->buildGenreRatings($reviews),
        ];
    }

    /**
     * @param  Collection<int, Review>  $reviews
     * @return array{total_reviews: int, books_read: int, average_rating: float}
     */
    private function buildSummary(Collection $reviews): array
    {
        return [
            'total_reviews' => $reviews->count(),
            'books_read' => $reviews->pluck('book_id')->unique()->count(),
            'average_rating' => (float) ($reviews->avg('rating') ?? 0),
        ];
    }

    /**
     * @param  Collection<int, Review>  $reviews
     * @return Collection<int, int> 添字0〜4が★1〜5の件数に対応する
     */
    private function buildRatingDistribution(Collection $reviews): Collection
    {
        $counts = $reviews->countBy('rating');

        return collect(range(1, 5))->map(fn (int $rating) => $counts->get($rating, 0));
    }

    /**
     * @param  Collection<int, Review>  $reviews
     * @return Collection<int, array{id: int, title: string, author: string, rating: int}>
     */
    private function buildTopRatedBooks(Collection $reviews): Collection
    {
        return $reviews
            ->filter(fn (Review $review) => $review->rating >= 4)
            ->sortByDesc('rating')
            ->take(5)
            ->map(fn (Review $review) => [
                'id' => $review->book->id,
                'title' => $review->book->title,
                'author' => $review->book->author,
                'rating' => $review->rating,
            ])
            ->values();
    }

    /**
     * @param  Collection<int, Review>  $reviews
     * @return Collection<int, array{id: int, name: string, count: int, average_rating: float}>
     */
    private function buildGenreRatings(Collection $reviews): Collection
    {
        return $reviews
            ->flatMap(fn (Review $review) => $review->book->genres->map(fn ($genre) => [
                'id' => $genre->id,
                'name' => $genre->name,
                'rating' => $review->rating,
            ]))
            ->groupBy('id')
            ->map(fn (Collection $group) => [
                'id' => $group->first()['id'],
                'name' => $group->first()['name'],
                'count' => $group->count(),
                'average_rating' => (float) $group->avg('rating'),
            ])
            ->sortByDesc('average_rating')
            ->take(5)
            ->values();
    }
}

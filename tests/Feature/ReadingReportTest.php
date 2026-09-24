<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\Genre;
use App\Models\Review;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReadingReportTest extends TestCase
{
    use RefreshDatabase;

    private function createBook(array $attributes = []): Book
    {
        return Book::create(array_merge([
            'user_id' => User::factory()->create()->id,
            'title' => 'テスト書籍',
            'author' => 'テスト著者',
            'isbn' => fake()->unique()->numerify('#############'),
            'published_date' => '2020-01-01',
        ], $attributes));
    }

    private function createReview(User $user, Book $book, int $rating): Review
    {
        return Review::create([
            'user_id' => $user->id,
            'book_id' => $book->id,
            'rating' => $rating,
            'comment' => 'コメント',
        ]);
    }

    public function test_ゲストはアクセスできずログインへリダイレクトされる(): void
    {
        $this->get('/reports')->assertRedirect('/login');
    }

    public function test_レビューが無い場合はすべて0件になる(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/reports');

        $response->assertOk();
        $response->assertViewHas('stats', function (array $stats) {
            return $stats['summary']['total_reviews'] === 0
                && $stats['summary']['books_read'] === 0
                && $stats['summary']['average_rating'] === 0.0
                && $stats['rating_distribution']->all() === [0, 0, 0, 0, 0]
                && $stats['top_rated_books']->isEmpty()
                && $stats['genre_ratings']->isEmpty();
        });
    }

    public function test_基本サマリーは自分のレビューだけで集計される(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        $this->createReview($user, $this->createBook(), 5);
        $this->createReview($user, $this->createBook(), 3);
        $this->createReview($other, $this->createBook(), 1);

        $response = $this->actingAs($user)->get('/reports');

        $response->assertViewHas('stats', function (array $stats) {
            return $stats['summary']['total_reviews'] === 2
                && $stats['summary']['books_read'] === 2
                && $stats['summary']['average_rating'] === 4.0;
        });
    }

    public function test_評価分布が星ごとの件数で集計される(): void
    {
        $user = User::factory()->create();

        foreach ([5, 5, 3, 1] as $rating) {
            $this->createReview($user, $this->createBook(), $rating);
        }

        $response = $this->actingAs($user)->get('/reports');

        $response->assertViewHas('stats', function (array $stats) {
            // 添字0=★1, 1=★2, 2=★3, 3=★4, 4=★5
            return $stats['rating_distribution']->all() === [1, 0, 1, 0, 2];
        });
    }

    public function test_高評価書籍top5は4星以上のみ評価が高い順で最大5件(): void
    {
        $user = User::factory()->create();

        foreach ([5, 3, 4, 5, 4, 4, 5] as $rating) {
            $this->createReview($user, $this->createBook(), $rating);
        }

        $response = $this->actingAs($user)->get('/reports');

        $response->assertViewHas('stats', function (array $stats) {
            $topRated = $stats['top_rated_books'];

            return $topRated->count() === 5
                && $topRated->pluck('rating')->all() === [5, 5, 5, 4, 4];
        });
    }

    public function test_ジャンル別評価傾向は平均評価が高い順で最大5件(): void
    {
        $user = User::factory()->create();

        $novelGenre = Genre::create(['name' => '小説']);
        $bizGenre = Genre::create(['name' => 'ビジネス']);

        $novelBook1 = $this->createBook();
        $novelBook1->genres()->sync([$novelGenre->id]);
        $novelBook2 = $this->createBook();
        $novelBook2->genres()->sync([$novelGenre->id]);
        $bizBook = $this->createBook();
        $bizBook->genres()->sync([$bizGenre->id]);

        $this->createReview($user, $novelBook1, 5);
        $this->createReview($user, $novelBook2, 3);
        $this->createReview($user, $bizBook, 5);

        $response = $this->actingAs($user)->get('/reports');

        $response->assertViewHas('stats', function (array $stats) use ($novelGenre, $bizGenre) {
            $genreRatings = $stats['genre_ratings'];

            return $genreRatings->count() === 2
                && $genreRatings->first()['id'] === $bizGenre->id
                && $genreRatings->first()['average_rating'] === 5.0
                && $genreRatings->last()['id'] === $novelGenre->id
                && $genreRatings->last()['count'] === 2
                && $genreRatings->last()['average_rating'] === 4.0;
        });
    }

    public function test_複数ジャンルを持つ書籍のレビューは両方のジャンルに計上される(): void
    {
        $user = User::factory()->create();

        $genreA = Genre::create(['name' => 'ジャンルA']);
        $genreB = Genre::create(['name' => 'ジャンルB']);

        $book = $this->createBook();
        $book->genres()->sync([$genreA->id, $genreB->id]);

        $this->createReview($user, $book, 5);

        $response = $this->actingAs($user)->get('/reports');

        $response->assertViewHas('stats', function (array $stats) {
            $genreRatings = $stats['genre_ratings'];

            return $genreRatings->count() === 2
                && $genreRatings->every(fn (array $genre) => $genre['count'] === 1 && $genre['average_rating'] === 5.0);
        });
    }
}

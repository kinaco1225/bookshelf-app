<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class BookIsbnSearchTest extends TestCase
{
    use RefreshDatabase;

    private function fakeGoogleBooksResponse(array $body, int $status = 200): void
    {
        Http::fake([
            'www.googleapis.com/books/v1/volumes*' => Http::response($body, $status),
        ]);
    }

    public function test_ゲストはアクセスできず401(): void
    {
        $this->getJson('/books/isbn/9784101010014')->assertUnauthorized();
    }

    public function test_isbnが13桁でない場合は422でエラーを返し外部apiは呼ばれない(): void
    {
        Http::fake();

        $this->actingAs(User::factory()->create())
            ->getJson('/books/isbn/12345')
            ->assertStatus(422)
            ->assertJsonStructure(['error']);

        Http::assertNothingSent();
    }

    public function test_isbnに数字以外が含まれる場合は422でエラーを返す(): void
    {
        Http::fake();

        $this->actingAs(User::factory()->create())
            ->getJson('/books/isbn/978410101001a')
            ->assertStatus(422);

        Http::assertNothingSent();
    }

    public function test_該当書籍が見つかった場合は書籍情報を返す(): void
    {
        $this->fakeGoogleBooksResponse([
            'totalItems' => 1,
            'items' => [
                [
                    'volumeInfo' => [
                        'title' => '吾輩は猫である',
                        'authors' => ['夏目漱石'],
                        'publishedDate' => '1905-01-01',
                        'description' => '猫の視点から描いた長編小説。',
                        'imageLinks' => ['thumbnail' => 'https://example.com/cover.jpg'],
                    ],
                ],
            ],
        ]);

        $response = $this->actingAs(User::factory()->create())
            ->getJson('/books/isbn/9784101010014');

        $response->assertOk();
        $response->assertJson([
            'title' => '吾輩は猫である',
            'author' => '夏目漱石',
            'description' => '猫の視点から描いた長編小説。',
            'image_url' => 'https://example.com/cover.jpg',
            'published_date' => '1905-01-01',
        ]);
    }

    public function test_複数著者はく点区切りで結合される(): void
    {
        $this->fakeGoogleBooksResponse([
            'totalItems' => 1,
            'items' => [
                ['volumeInfo' => ['title' => '嫌われる勇気', 'authors' => ['岸見一郎', '古賀史健']]],
            ],
        ]);

        $response = $this->actingAs(User::factory()->create())
            ->getJson('/books/isbn/9784478025819');

        $response->assertOk();
        $response->assertJson(['author' => '岸見一郎・古賀史健']);
    }

    public function test_該当書籍が無い場合は404でエラーを返す(): void
    {
        $this->fakeGoogleBooksResponse(['totalItems' => 0, 'items' => []]);

        $response = $this->actingAs(User::factory()->create())
            ->getJson('/books/isbn/9784101010014');

        $response->assertStatus(404);
        $response->assertJsonStructure(['error']);
    }

    public function test_外部api障害時は502でエラーを返す(): void
    {
        $this->fakeGoogleBooksResponse([], 500);

        $response = $this->actingAs(User::factory()->create())
            ->getJson('/books/isbn/9784101010014');

        $response->assertStatus(502);
        $response->assertJsonStructure(['error']);
    }
}

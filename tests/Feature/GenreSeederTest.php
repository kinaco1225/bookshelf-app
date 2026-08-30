<?php

namespace Tests\Feature;

use App\Models\Genre;
use Database\Seeders\GenreSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GenreSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_ジャンルが10件投入される(): void
    {
        $this->seed(GenreSeeder::class);

        $this->assertSame(10, Genre::count());
        $this->assertDatabaseHas('genres', ['name' => '技術書']);
    }

    public function test_複数回実行しても重複しない(): void
    {
        $this->seed(GenreSeeder::class);
        $this->seed(GenreSeeder::class);

        $this->assertSame(10, Genre::count());
    }
}

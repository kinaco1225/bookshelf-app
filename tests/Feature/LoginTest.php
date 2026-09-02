<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_ログイン画面が表示される(): void
    {
        $this->get('/login')->assertOk();
    }

    public function test_正しい情報でログインできる(): void
    {
        $user = User::factory()->create();

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $response->assertRedirect('/');
        $this->assertAuthenticatedAs($user);
    }

    public function test_パスワードが違うとログインできない(): void
    {
        $user = User::factory()->create();

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_登録されていないメールではログインできない(): void
    {
        $this->post('/login', [
            'email' => 'nobody@example.com',
            'password' => 'password',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_メールとパスワードは必須(): void
    {
        $this->post('/login', [])->assertSessionHasErrors(['email', 'password']);
    }

    public function test_ログアウトできる(): void
    {
        $this->actingAs(User::factory()->create())
            ->post('/logout')
            ->assertRedirect('/');

        $this->assertGuest();
    }

    public function test_ログイン済みでログイン画面を開くとトップへリダイレクト(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/login')
            ->assertRedirect('/');
    }

    public function test_ログイン済みで会員登録画面を開くとトップへリダイレクト(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/register')
            ->assertRedirect('/');
    }
}

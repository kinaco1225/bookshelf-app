<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, string>
     */
    private function validInput(array $overrides = []): array
    {
        return array_merge([
            'name' => '山田太郎',
            'email' => 'taro@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ], $overrides);
    }

    public function test_会員登録できる(): void
    {
        $this->post('/register', $this->validInput());

        $user = User::where('email', 'taro@example.com')->first();
        $this->assertNotNull($user);
        $this->assertSame('山田太郎', $user->name);
        $this->assertTrue(Hash::check('password123', $user->password));
    }

    public function test_名前は必須(): void
    {
        $this->post('/register', $this->validInput(['name' => '']))
            ->assertSessionHasErrors(['name' => 'お名前を入力してください。']);
    }

    public function test_メールアドレスの形式が不正だとエラー(): void
    {
        $this->post('/register', $this->validInput(['email' => 'not-an-email']))
            ->assertSessionHasErrors(['email' => 'メールアドレスの形式が正しくありません。']);
    }

    public function test_メールアドレスは重複できない(): void
    {
        User::factory()->create(['email' => 'taro@example.com']);

        $this->post('/register', $this->validInput())
            ->assertSessionHasErrors(['email' => 'このメールアドレスは既に登録されています。']);
    }

    public function test_パスワードは8文字以上(): void
    {
        $this->post('/register', $this->validInput([
            'password' => 'short',
            'password_confirmation' => 'short',
        ]))->assertSessionHasErrors(['password' => 'パスワードは8文字以上で入力してください。']);
    }

    public function test_パスワードは確認用と一致する必要がある(): void
    {
        $this->post('/register', $this->validInput([
            'password_confirmation' => 'different123',
        ]))->assertSessionHasErrors(['password' => 'パスワード（確認用）が一致しません。']);
    }
}

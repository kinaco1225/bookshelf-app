<?php

namespace App\Actions\Fortify;

trait PasswordValidationRules
{
    /**
     * パスワードのバリデーションルール。
     *
     * 8文字以上・確認用（password_confirmation）と一致すること。
     *
     * @return array<int, string>
     */
    protected function passwordRules(): array
    {
        return ['required', 'string', 'min:8', 'confirmed'];
    }
}

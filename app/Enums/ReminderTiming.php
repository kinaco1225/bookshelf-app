<?php

namespace App\Enums;

enum ReminderTiming: string
{
    case ThreeDaysBefore = 'three_days_before';
    case OnDueDate = 'on_due_date';
    case Expired = 'expired';
    case ThreeDaysAfter = 'three_days_after';

    public function title(): string
    {
        return match ($this) {
            self::ThreeDaysBefore => '読書期限が近づいています',
            self::OnDueDate => '本日が読書期限です',
            self::Expired => '読書期限が過ぎました',
            self::ThreeDaysAfter => '読書期限が3日経過しました',
        };
    }

    public function body(string $bookTitle): string
    {
        return match ($this) {
            self::ThreeDaysBefore => "『{$bookTitle}』の期日まであと3日です。",
            self::OnDueDate => "『{$bookTitle}』の期日は本日です。",
            self::Expired => "『{$bookTitle}』の期日を過ぎたため、計画は「期限切れ」になりました。",
            self::ThreeDaysAfter => "『{$bookTitle}』の期日から3日が経過しました。",
        };
    }
}

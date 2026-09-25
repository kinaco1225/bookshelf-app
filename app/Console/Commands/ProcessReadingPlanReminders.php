<?php

namespace App\Console\Commands;

use App\Enums\ReadingPlanStatus;
use App\Enums\ReminderTiming;
use App\Models\ReadingPlan;
use App\Notifications\ReadingPlanReminder;
use Illuminate\Console\Command;

class ProcessReadingPlanReminders extends Command
{
    protected $signature = 'reading-plans:process';

    protected $description = '読書計画の期限切れ自動化とリマインダー通知を実行する（日次バッチ）';

    public function handle(): int
    {
        $expiredCount = $this->autoExpirePlans();
        $notifiedCount = $this->sendReminders();

        $this->info("自動失効: {$expiredCount}件 / リマインダー通知: {$notifiedCount}件");

        return self::SUCCESS;
    }

    /**
     * 期日を過ぎた「進行中」の計画を一括で「期限切れ」に変更する。
     */
    private function autoExpirePlans(): int
    {
        return ReadingPlan::query()
            ->where('status', ReadingPlanStatus::InProgress->value)
            ->where('target_date', '<', now()->toDateString())
            ->update(['status' => ReadingPlanStatus::Expired->value]);
    }

    /**
     * 3日前・当日（ともに進行中の計画が対象）・3日後（期限切れになった計画が対象の再エンゲージメント）の
     * リマインダー通知を発火する。同一計画・同一タイミングへの重複送信は行わない。
     */
    private function sendReminders(): int
    {
        $targets = collect([
            [
                'timing' => ReminderTiming::ThreeDaysBefore,
                'date' => now()->addDays(3)->toDateString(),
                'status' => ReadingPlanStatus::InProgress->value,
            ],
            [
                'timing' => ReminderTiming::OnDueDate,
                'date' => now()->toDateString(),
                'status' => ReadingPlanStatus::InProgress->value,
            ],
            [
                'timing' => ReminderTiming::ThreeDaysAfter,
                'date' => now()->subDays(3)->toDateString(),
                'status' => ReadingPlanStatus::Expired->value,
            ],
        ]);

        return $targets->sum(function (array $target): int {
            $timing = $target['timing'];

            $plans = ReadingPlan::query()
                ->where('status', $target['status'])
                ->whereDate('target_date', $target['date'])
                ->with(['user', 'book'])
                ->get()
                ->reject(fn (ReadingPlan $plan) => $this->alreadyNotified($plan, $timing));

            $plans->each(fn (ReadingPlan $plan) => $plan->user->notify(new ReadingPlanReminder($plan, $timing)));

            return $plans->count();
        });
    }

    private function alreadyNotified(ReadingPlan $plan, ReminderTiming $timing): bool
    {
        return $plan->user->notifications()
            ->where('data->reading_plan_id', $plan->id)
            ->where('data->timing', $timing->value)
            ->exists();
    }
}

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
     * 期日を過ぎた「進行中」の計画を「期限切れ」に変更する。
     */
    private function autoExpirePlans(): int
    {
        return ReadingPlan::query()
            ->where('status', ReadingPlanStatus::InProgress->value)
            ->where('target_date', '<', now()->toDateString())
            ->update(['status' => ReadingPlanStatus::Expired->value]);
    }

    /**
     * 3日前・当日・3日後（自動失効直後の再エンゲージメント）のリマインダー通知を発火する。
     * 完了済みの計画は対象外。同一計画・同一タイミングへの重複送信は行わない。
     */
    private function sendReminders(): int
    {
        $targetDates = [
            ReminderTiming::ThreeDaysBefore->value => now()->addDays(3)->toDateString(),
            ReminderTiming::OnDueDate->value => now()->toDateString(),
            ReminderTiming::ThreeDaysAfter->value => now()->subDays(3)->toDateString(),
        ];

        $notifiedCount = 0;

        foreach ($targetDates as $timingValue => $targetDate) {
            $timing = ReminderTiming::from($timingValue);

            $plans = ReadingPlan::query()
                ->where('status', '!=', ReadingPlanStatus::Completed->value)
                ->whereDate('target_date', $targetDate)
                ->with(['user', 'book'])
                ->get();

            foreach ($plans as $plan) {
                if ($this->alreadyNotified($plan, $timing)) {
                    continue;
                }

                $plan->user->notify(new ReadingPlanReminder($plan, $timing));
                $notifiedCount++;
            }
        }

        return $notifiedCount;
    }

    private function alreadyNotified(ReadingPlan $plan, ReminderTiming $timing): bool
    {
        return $plan->user->notifications()
            ->where('data->reading_plan_id', $plan->id)
            ->where('data->timing', $timing->value)
            ->exists();
    }
}

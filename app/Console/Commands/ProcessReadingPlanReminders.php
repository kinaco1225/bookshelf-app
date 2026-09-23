<?php

namespace App\Console\Commands;

use App\Enums\ReadingPlanStatus;
use App\Enums\ReminderTiming;
use App\Models\ReadingPlan;
use App\Notifications\ReadingPlanReminder;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;

class ProcessReadingPlanReminders extends Command
{
    protected $signature = 'reading-plans:process';

    protected $description = '読書計画の期限切れ自動化とリマインダー通知を実行する（日次バッチ）';

    public function handle(): int
    {
        $expiredPlans = $this->autoExpirePlans();
        $notifiedCount = $this->notifyExpiredPlans($expiredPlans);
        $notifiedCount += $this->sendReminders();

        $this->info("自動失効: {$expiredPlans->count()}件 / リマインダー通知: {$notifiedCount}件");

        return self::SUCCESS;
    }

    /**
     * 期日を過ぎた「進行中」の計画を「期限切れ」に変更する。
     *
     * @return Collection<int, ReadingPlan> 今回新たに期限切れにした計画（即時通知用）
     */
    private function autoExpirePlans(): Collection
    {
        $plans = ReadingPlan::query()
            ->where('status', ReadingPlanStatus::InProgress->value)
            ->where('target_date', '<', now()->toDateString())
            ->with(['user', 'book'])
            ->get();

        if ($plans->isNotEmpty()) {
            ReadingPlan::query()
                ->whereIn('id', $plans->pluck('id'))
                ->update(['status' => ReadingPlanStatus::Expired->value]);
        }

        return $plans;
    }

    /**
     * 期限切れになった直後の計画に「読書期限が過ぎました」を通知する。
     */
    private function notifyExpiredPlans(Collection $plans): int
    {
        $notifiedCount = 0;

        foreach ($plans as $plan) {
            if ($this->alreadyNotified($plan, ReminderTiming::Expired)) {
                continue;
            }

            $plan->user->notify(new ReadingPlanReminder($plan, ReminderTiming::Expired));
            $notifiedCount++;
        }

        return $notifiedCount;
    }

    /**
     * 3日前・当日・3日後（期限切れから3日経過した再エンゲージメント）のリマインダー通知を発火する。
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

<?php

namespace App\Notifications;

use App\Enums\ReminderTiming;
use App\Models\ReadingPlan;
use Illuminate\Notifications\Notification;

class ReadingPlanReminder extends Notification
{
    public function __construct(
        private readonly ReadingPlan $readingPlan,
        private readonly ReminderTiming $timing,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        $bookTitle = $this->readingPlan->book->title;

        return [
            'reading_plan_id' => $this->readingPlan->id,
            'timing' => $this->timing->value,
            'title' => $this->timing->title(),
            'body' => $this->timing->body($bookTitle),
        ];
    }
}

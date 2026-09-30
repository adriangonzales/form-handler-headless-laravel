<?php

namespace App\Jobs;

use App\Mail\NewFormEntry;
use App\Models\FormEntry;
use App\Models\FormNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Throwable;

class DeliverFormEntryAlert implements ShouldQueue
{
    use Queueable;

    /**
     * Attempts before the failure is recorded on the recipient.
     */
    public int $tries = 3;

    /**
     * Skip the alert if the recipient or entry was permanently deleted before it ran.
     */
    public bool $deleteWhenMissingModels = true;

    public function __construct(public FormNotification $recipient, public FormEntry $entry) {}

    /**
     * Seconds to wait before each retry.
     *
     * @return list<int>
     */
    public function backoff(): array
    {
        return [60, 300];
    }

    /**
     * Email the entry to the recipient, and clear any error left by an earlier failed delivery. A recipient
     * that was disabled or deleted, or an entry that was deleted, since the alert was queued is skipped.
     */
    public function handle(): void
    {
        if (! $this->recipient->enabled || $this->recipient->trashed() || $this->entry->trashed()) {
            return;
        }

        Mail::to($this->recipient->value)->send(new NewFormEntry($this->entry, $this->recipient));

        if ($this->recipient->error !== null) {
            $this->recipient->update(['error' => null]);
        }
    }

    /**
     * Record why delivery failed once every attempt is used up, so the owner can see it on the recipient.
     */
    public function failed(?Throwable $exception): void
    {
        $this->recipient->update([
            'error' => Str::limit($exception?->getMessage() ?: 'Delivery failed.', 255, ''),
        ]);
    }
}

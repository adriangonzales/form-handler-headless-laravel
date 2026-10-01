<?php

namespace App\Listeners;

use App\Events\FormEntrySpamChecked;
use App\Jobs\DeliverFormEntryAlert;
use App\Models\FormNotification;

class SendFormEntryAlerts
{
    /**
     * Queue an alert to each enabled email recipient of the entry's form once its spam check is done.
     * Entries flagged as spam are not alerted. SMS recipients are skipped until an SMS channel exists.
     */
    public function handle(FormEntrySpamChecked $event): void
    {
        $entry = $event->formEntry;

        if ($entry->spam === true) {
            return;
        }

        $entry->form->notifications()
            ->where('enabled', true)
            ->where('type', 'email')
            ->each(fn (FormNotification $recipient) => dispatch(new DeliverFormEntryAlert($recipient, $entry)));
    }
}

<?php

namespace App\Listeners;

use App\Events\FormEntryCreated;
use donatj\UserAgent\UserAgentParser;
use Illuminate\Contracts\Queue\ShouldQueue;

class ParseFormEntryUserAgent implements ShouldQueue
{
    /**
     * Discard the job if the entry was permanently deleted before it ran.
     */
    public bool $deleteWhenMissingModels = true;

    /**
     * Parse the entry's raw user agent into its platform, browser and browser version, stored in
     * `user_agent_display`. Parts the parser cannot identify are null; an entry without a user agent
     * is left alone.
     */
    public function handle(FormEntryCreated $event): void
    {
        $entry = $event->formEntry;

        if ($entry->user_agent === null || $entry->user_agent === '') {
            return;
        }

        $userAgent = (new UserAgentParser)->parse($entry->user_agent);

        $entry->update([
            'user_agent_display' => [
                'platform' => $userAgent->platform(),
                'browser' => $userAgent->browser(),
                'browser_version' => $userAgent->browserVersion(),
            ],
        ]);
    }
}

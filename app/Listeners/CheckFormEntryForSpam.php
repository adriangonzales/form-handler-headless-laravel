<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\FormEntrySpamChecked;
use App\Events\FormEntrySubmitted;
use App\Models\FormEntry;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Laravel\Ai\Classification;
use Laravel\Ai\Classification\Boolean;
use Laravel\Ai\Responses\Data\BooleanAnswer;
use Throwable;

class CheckFormEntryForSpam implements ShouldQueue
{
    /**
     * The probability Jev must give "is spam" before the entry is flagged.
     */
    public const float THRESHOLD = 0.9;

    public const string REASON = 'Jev classified this entry as spam.';

    /**
     * Discard the job if the entry was permanently deleted before it ran.
     */
    public bool $deleteWhenMissingModels = true;

    /**
     * Ask Jev whether an entry not already flagged as spam is spam, store the verdict and its
     * probability with the time it was checked, then announce that the spam check is done. Without a
     * TypeSafe API key, or when the call fails, the entry is left as submitted, with no
     * `spam_checked_at`, so its alerts are still sent.
     */
    public function handle(FormEntrySubmitted $event): void
    {
        $entry = $event->formEntry;

        if ($entry->spam !== true) {
            $this->classify($entry);
        }

        event(new FormEntrySpamChecked($entry));
    }

    private function classify(FormEntry $entry): void
    {
        if (blank(config('ai.providers.typesafe.key'))) {
            return;
        }

        try {
            $answer = Classification::of(array_filter([
                'form_name' => $entry->form?->name,
                'referer' => $entry->referer,
                'submission' => Str::limit((string) json_encode($entry->input, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), 10_000),
            ]))
                ->question('is_spam', new Boolean(
                    'This form submission is spam rather than a genuine message from a person using the form.',
                    [
                        'true' => 'Unsolicited sales or SEO outreach, marketing blasts, scams, phishing, link dropping, gibberish or automated bot filler.',
                        'false' => 'A real person filling in the form for its intended purpose, such as an enquiry, a request, feedback or a sign-up.',
                    ],
                ))
                ->timeout(10)
                ->classify()
                ->answer('is_spam');
        } catch (Throwable $throwable) {
            Log::warning('Could not classify a form entry for spam.', [
                'form_entry_id' => $entry->id,
                'error' => $throwable->getMessage(),
            ]);

            return;
        }

        if (! $answer instanceof BooleanAnswer) {
            return;
        }

        $isSpam = $answer->isTrue(self::THRESHOLD);

        $entry->update([
            'spam' => $isSpam,
            'spam_score' => round($answer->probability, 2),
            'spam_reason' => $isSpam ? self::REASON : null,
            'spam_checked_at' => now(),
        ]);
    }
}

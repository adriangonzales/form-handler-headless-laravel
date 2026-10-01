<?php

declare(strict_types=1);

namespace App\Actions\FormEntries;

use App\Events\FormEntryCreated;
use App\Models\Form;
use App\Models\FormEntry;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CreateFormEntry
{
    /**
     * Store a submission's validated input with the request's metadata and announce it. Passing a
     * spam reason flags the entry as spam, which stops alerts being sent for it. The spam check is
     * recorded as done now unless the entry still awaits one, as clean public submissions do.
     *
     * @param  array<string, mixed>  $input
     */
    public function __invoke(Form $form, array $input, Request $request, ?string $spamReason = null, bool $awaitsSpamCheck = false): FormEntry
    {
        /** @var FormEntry */
        $formEntry = $form->entries()->create([
            'input' => $input,
            'ip' => implode(',', $request->ips()),
            // 'ip_location_display' => null, // TODO: Add parse step
            'referer' => Str::substr((string) $request->header('Referer'), 0, 255) ?: null,
            'user_agent' => $request->userAgent(),
            'spam' => $spamReason !== null, // TODO: Add catpcha service step
            'spam_score' => 0, // TODO: Add catpcha service step
            'spam_reason' => $spamReason,
            'spam_checked_at' => $awaitsSpamCheck ? null : now(),
        ]);

        event(new FormEntryCreated($formEntry));

        return $formEntry;
    }
}

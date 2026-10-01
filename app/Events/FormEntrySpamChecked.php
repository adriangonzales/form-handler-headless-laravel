<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\FormEntry;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * The entry's spam flag is final: it was flagged at submission, classified by Jev, or left as
 * submitted because classification was unavailable.
 */
class FormEntrySpamChecked
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(public FormEntry $formEntry) {}
}

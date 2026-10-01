<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\FormEntry;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * The entry came from a public submission to the form, not the authenticated API, so it is
 * checked for spam, alerted and has its user agent parsed.
 */
class FormEntrySubmitted
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(public FormEntry $formEntry) {}
}

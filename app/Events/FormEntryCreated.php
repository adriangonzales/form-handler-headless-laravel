<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\FormEntry;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class FormEntryCreated
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(public FormEntry $formEntry) {}
}

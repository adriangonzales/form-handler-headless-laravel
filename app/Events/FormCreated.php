<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Form;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class FormCreated
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(public Form $form) {}
}

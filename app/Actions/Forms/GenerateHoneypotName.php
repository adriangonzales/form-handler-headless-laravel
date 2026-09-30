<?php

declare(strict_types=1);

namespace App\Actions\Forms;

use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class GenerateHoneypotName
{
    /**
     * Contact details bots tend to fill in, so the honeypot looks like a real field.
     *
     * @var list<string>
     */
    public const array PREFIXES = ['website', 'homepage', 'url', 'company'];

    /**
     * Generate a realistic honeypot input name with a random suffix, e.g. `website_k3x9qa`, that is not
     * one of the given input names.
     *
     * @param  list<string>  $takenNames
     */
    public function __invoke(array $takenNames = []): string
    {
        do {
            $name = Arr::random(self::PREFIXES).'_'.Str::lower(Str::random(6));
        } while (in_array($name, $takenNames, true));

        return $name;
    }
}

<?php

use App\Actions\Forms\GenerateHoneypotName;
use Illuminate\Support\Str;

afterEach(function (): void {
    Str::createRandomStringsNormally();
});

it('generates a realistic honeypot name with a random suffix', function (): void {
    Str::createRandomStringsUsingSequence(['AbC123']);

    $name = (new GenerateHoneypotName)();

    expect($name)->toMatch('/^(website|homepage|url|company)_abc123$/');
});

it('generates a name that is not already taken', function (): void {
    Str::createRandomStringsUsingSequence(['taken1', 'free22']);
    $takenNames = array_map(fn (string $prefix): string => $prefix.'_taken1', GenerateHoneypotName::PREFIXES);

    $name = (new GenerateHoneypotName)($takenNames);

    expect($name)->toEndWith('_free22');
});

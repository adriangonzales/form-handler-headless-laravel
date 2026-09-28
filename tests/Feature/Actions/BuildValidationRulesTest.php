<?php

use App\Actions\Forms\BuildValidationRules;
use App\Models\Form;

it('produces no validation rules from an empty schema', function (): void {
    $form = Form::factory()->create();

    $result = (new BuildValidationRules)($form);

    expect($result)->toBe([]);
});

it('produces validation rules from a basic schema', function (): void {
    $schema = [
        Str::ulid()->toString() => [
            'label' => 'Name',
            'rules' => ['required'],
        ],
        Str::ulid()->toString() => [
            'label' => 'Email',
            'rules' => ['required', 'email'],
        ],
        Str::ulid()->toString() => [
            'label' => 'Message',
            'rules' => ['required'],
        ],
    ];

    $form = Form::factory()->create(['schema' => $schema]);

    $result = (new BuildValidationRules)($form);

    $schemaFieldIds = array_keys($schema);

    $expectedSchema = [
        $schemaFieldIds[0] => [
            'required',
        ],
        $schemaFieldIds[1] => [
            'required',
            'email',
        ],
        $schemaFieldIds[2] => [
            'required',
        ],
    ];

    expect($result)->toBe($expectedSchema);
});

it('produces validation rules from a comma delimited schema', function (): void {
    $schema = [
        Str::ulid()->toString() => [
            'label' => 'Name',
            'rules' => 'required',
        ],
        Str::ulid()->toString() => [
            'label' => 'Email',
            'rules' => 'required,email',
        ],
        Str::ulid()->toString() => [
            'label' => 'Message',
            'rules' => 'required',
        ],
    ];

    $form = Form::factory()->create(['schema' => $schema]);

    $result = (new BuildValidationRules)($form);

    $schemaFieldIds = array_keys($schema);

    $expectedSchema = [
        $schemaFieldIds[0] => [
            'required',
        ],
        $schemaFieldIds[1] => [
            'required',
            'email',
        ],
        $schemaFieldIds[2] => [
            'required',
        ],
    ];

    expect($result)->toBe($expectedSchema);
});

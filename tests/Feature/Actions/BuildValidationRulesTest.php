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
        [
            'id' => Str::ulid()->toString(),
            'order' => 1,
            'label' => 'Name',
            'rules' => ['required'],
        ],
        [
            'id' => Str::ulid()->toString(),
            'order' => 2,
            'label' => 'Email',
            'rules' => ['required', 'email'],
        ],
        [
            'id' => Str::ulid()->toString(),
            'order' => 3,
            'label' => 'Message',
            'rules' => ['required'],
        ],
    ];

    $form = Form::factory()->create(['schema' => $schema]);

    $result = (new BuildValidationRules)($form);

    $schemaFieldIds = array_column($schema, 'id');

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
        [
            'id' => Str::ulid()->toString(),
            'order' => 1,
            'label' => 'Name',
            'rules' => 'required',
        ],
        [
            'id' => Str::ulid()->toString(),
            'order' => 2,
            'label' => 'Email',
            'rules' => 'required,email',
        ],
        [
            'id' => Str::ulid()->toString(),
            'order' => 3,
            'label' => 'Message',
            'rules' => 'required',
        ],
    ];

    $form = Form::factory()->create(['schema' => $schema]);

    $result = (new BuildValidationRules)($form);

    $schemaFieldIds = array_column($schema, 'id');

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

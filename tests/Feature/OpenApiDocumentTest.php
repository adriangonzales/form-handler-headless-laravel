<?php

namespace Tests\Feature;

use Dedoc\Scramble\Generator;
use Dedoc\Scramble\Scramble;

beforeEach(function (): void {
    $this->document = resolve(Generator::class)(Scramble::getGeneratorConfig(Scramble::DEFAULT_API));
});

it('documents the login access token as a string', function (): void {
    $token = data_get($this->document, 'paths./v1/auth/login.post.responses.200.content.application/json.schema.properties.access_token');

    expect($token['type'])->toBe('string');
});

it('documents the entry spam score as a number', function (): void {
    expect($this->document['components']['schemas']['FormEntryResource']['properties']['spam_score']['type'])
        ->toBe('number');
});

it('documents the export parameters as an object', function (): void {
    $parameters = $this->document['components']['schemas']['FormEntryExportResource']['properties']['parameters'];

    expect($parameters['type'])->toBe('object')
        ->and(array_keys($parameters['properties']))->toBe(['sort', 'filter']);
});

it('requires only the id and order of each schema field in form requests', function (string $schema): void {
    expect($this->document['components']['schemas'][$schema]['properties']['schema']['items']['required'])
        ->toBe(['id', 'order']);
})->with(['FormStoreRequest', 'FormUpdateRequest']);

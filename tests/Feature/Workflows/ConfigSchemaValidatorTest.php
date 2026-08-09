<?php

use App\Services\Workflows\ConfigSchemaValidator;

it('passes a valid config against a node schema', function () {
    $validator = new ConfigSchemaValidator;

    $schema = [
        'type' => 'object',
        'required' => ['prompt'],
        'properties' => [
            'prompt' => ['type' => 'string'],
            'model' => ['type' => 'string'],
        ],
    ];

    expect($validator->validate($schema, ['prompt' => 'hello']))->toBe([]);
});

it('reports a missing required field', function () {
    $validator = new ConfigSchemaValidator;

    $errors = $validator->validate([
        'type' => 'object',
        'required' => ['prompt'],
        'properties' => ['prompt' => ['type' => 'string']],
    ], []);

    expect($errors)->toBe(['config.prompt is required.']);
});

it('reports a wrong-typed field and an invalid enum value', function () {
    $validator = new ConfigSchemaValidator;

    $errors = $validator->validate([
        'type' => 'object',
        'required' => ['method'],
        'properties' => [
            'method' => ['type' => 'string', 'enum' => ['GET', 'POST']],
            'timeout_seconds' => ['type' => 'integer'],
        ],
    ], ['method' => 'DELETE', 'timeout_seconds' => 'soon']);

    expect($errors)->toBe([
        'config.method must be one of: GET, POST.',
        'config.timeout_seconds must be an integer.',
    ]);
});

it('validates nested array items against their item schema', function () {
    $validator = new ConfigSchemaValidator;

    $errors = $validator->validate([
        'type' => 'object',
        'required' => ['operations'],
        'properties' => [
            'operations' => [
                'type' => 'array',
                'items' => [
                    'type' => 'object',
                    'required' => ['op'],
                    'properties' => ['op' => ['type' => 'string']],
                ],
            ],
        ],
    ], ['operations' => [['op' => 'set'], []]]);

    expect($errors)->toBe(['config.operations[1].op is required.']);
});

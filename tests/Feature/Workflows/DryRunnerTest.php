<?php

use App\Services\Workflows\DryRunner;

it('simulates a valid graph and flags unresolved template references', function () {
    $graph = [
        'nodes' => [
            ['key' => 'a', 'type' => 'run_code', 'config' => [
                'operations' => [['op' => 'set', 'output' => 'result', 'value' => '1']],
            ]],
            ['key' => 'b', 'type' => 'run_code', 'config' => [
                'operations' => [
                    ['op' => 'set', 'output' => 'ok', 'value' => '{{ nodes.a.result }}'],
                    ['op' => 'set', 'output' => 'missing', 'value' => '{{ nodes.a.does_not_exist }}'],
                ],
            ]],
        ],
        'edges' => [
            ['from' => 'a', 'to' => 'b', 'condition' => null],
        ],
    ];

    $result = app(DryRunner::class)->run($graph);

    expect($result['issues'])->toBe([]);
    expect($result['steps'])->toHaveCount(2);
    expect($result['steps'][0]['key'])->toBe('a');
    expect($result['steps'][1]['key'])->toBe('b');
    // `run_code` declares its output shape from its own config
    // (`RunCodeNode::outputSchema()`), so node `a`'s sample really does carry a
    // `result` key. Only the misspelled reference is flagged — the whole point
    // of the schema being there.
    expect($result['warnings'])->toHaveCount(1);
    expect($result['warnings'][0])->toContain('nodes.a.does_not_exist');
    expect(collect($result['warnings'])->implode(' '))->not->toContain('nodes.a.result');
    expect($result['ok'])->toBeFalse();
});

it('short-circuits to issues without simulating an invalid graph', function () {
    $graph = [
        'nodes' => [
            ['key' => 'a', 'type' => 'run_code', 'config' => ['operations' => []]],
            ['key' => 'a', 'type' => 'run_code', 'config' => ['operations' => []]],
        ],
        'edges' => [],
    ];

    $result = app(DryRunner::class)->run($graph);

    expect($result['ok'])->toBeFalse();
    expect($result['issues'])->not->toBe([]);
    expect($result['steps'])->toBe([]);
});

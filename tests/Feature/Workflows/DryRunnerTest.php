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
    // `run_code`'s sample output is an empty placeholder (only `router`/`filter`
    // get a modelled `result`), so both templates referencing node `a`'s output
    // are flagged — not just the misspelled one.
    expect($result['warnings'])->toHaveCount(2);
    expect(collect($result['warnings'])->implode(' '))->toContain('nodes.a.does_not_exist');
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

<?php

use App\Models\Runs\Run;
use App\Nodes\FlowLogic\FilterNode;
use App\Nodes\FlowLogic\RouterNode;

it('routes to the first matching condition, falling back to default', function () {
    $node = new RouterNode;
    $run = Run::factory()->create();

    $config = [
        'conditions' => [
            ['path' => 'input.status', 'operator' => 'equals', 'value' => 'open', 'result' => 'is_open'],
            ['path' => 'input.status', 'operator' => 'equals', 'value' => 'closed', 'result' => 'is_closed'],
        ],
    ];

    expect($node->execute($run, $config, ['input' => ['status' => 'closed']]))->toBe(['result' => 'is_closed']);
    expect($node->execute($run, $config, ['input' => ['status' => 'unknown']]))->toBe(['result' => 'default']);
});

it('gates on a single condition as passed/failed', function () {
    $node = new FilterNode;
    $run = Run::factory()->create();

    $config = ['path' => 'input.count', 'operator' => 'greater_than', 'value' => 5];

    expect($node->execute($run, $config, ['input' => ['count' => 10]]))
        ->toBe(['result' => 'passed', 'passed' => true]);

    expect($node->execute($run, $config, ['input' => ['count' => 1]]))
        ->toBe(['result' => 'failed', 'passed' => false]);
});

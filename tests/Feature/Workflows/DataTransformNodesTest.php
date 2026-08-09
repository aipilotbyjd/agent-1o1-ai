<?php

use App\Models\Runs\Run;
use App\Nodes\DataTransform\RunCodeNode;
use App\Nodes\DataTransform\TransformNode;

it('maps output keys to dot paths into the context', function () {
    $node = new TransformNode;
    $run = Run::factory()->create();

    $output = $node->execute($run, [
        'mapping' => ['name' => 'input.user.name'],
    ], [
        'input' => ['user' => ['name' => 'Ada']],
    ]);

    expect($output)->toBe(['name' => 'Ada']);
});

it('runs only whitelisted RunCodeNode operations', function () {
    $node = new RunCodeNode;
    $run = Run::factory()->create();

    $output = $node->execute($run, [
        'operations' => [
            ['op' => 'set', 'output' => 'greeting', 'value' => 'hi'],
            ['op' => 'copy', 'output' => 'name', 'path' => 'input.name'],
            ['op' => 'uppercase', 'output' => 'shout', 'path' => 'input.name'],
            ['op' => 'concat', 'output' => 'full', 'paths' => ['input.first', 'input.last']],
        ],
    ], [
        'input' => ['name' => 'ada', 'first' => 'Ada', 'last' => 'Lovelace'],
    ]);

    expect($output)->toBe([
        'greeting' => 'hi',
        'name' => 'ada',
        'shout' => 'ADA',
        'full' => 'AdaLovelace',
    ]);
});

it('rejects a RunCodeNode operation outside the whitelist', function () {
    $node = new RunCodeNode;
    $run = Run::factory()->create();

    $node->execute($run, [
        'operations' => [['op' => 'eval', 'output' => 'x']],
    ], []);
})->throws(InvalidArgumentException::class);

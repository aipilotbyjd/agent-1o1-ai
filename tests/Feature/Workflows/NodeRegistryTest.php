<?php

use App\Contracts\NodeContract;
use App\Nodes\AiAutomation\AskAiNode;
use App\Nodes\DataTransform\CallApiNode;
use App\Nodes\DataTransform\RunCodeNode;
use App\Nodes\DataTransform\TransformNode;
use App\Nodes\FlowLogic\FilterNode;
use App\Nodes\FlowLogic\RouterNode;
use App\Services\Workflows\NodeRegistry;

it('registers every minimal built-in node under its own type string', function () {
    $registry = app(NodeRegistry::class);

    $expected = [
        'transform' => TransformNode::class,
        'call_api' => CallApiNode::class,
        'run_code' => RunCodeNode::class,
        'router' => RouterNode::class,
        'filter' => FilterNode::class,
        'ask_ai' => AskAiNode::class,
    ];

    foreach ($expected as $type => $class) {
        expect($registry->has($type))->toBeTrue();

        $node = $registry->resolve($type);

        expect($node)->toBeInstanceOf(NodeContract::class)
            ->and($node)->toBeInstanceOf($class)
            ->and($node->type())->toBe($type);
    }
});

it('throws for an unregistered node type', function () {
    app(NodeRegistry::class)->resolve('does-not-exist');
})->throws(InvalidArgumentException::class);

it('throws for a custom node type since the executor is not built yet', function () {
    app(NodeRegistry::class)->resolve('custom:1');
})->throws(InvalidArgumentException::class);

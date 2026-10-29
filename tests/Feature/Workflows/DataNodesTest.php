<?php

use App\Models\Runs\Run;
use App\Nodes\DataTransform\ArrayNode;
use App\Nodes\DataTransform\DateTimeNode;
use App\Nodes\DataTransform\JsonNode;
use App\Nodes\DataTransform\MathNode;
use App\Nodes\DataTransform\SetVariableNode;
use App\Nodes\DataTransform\StringNode;
use App\Services\Workflows\NodeRegistry;

/**
 * @param  array<string, mixed>  $config
 */
function runDataNode(object $node, array $config): mixed
{
    return $node->execute(Run::factory()->create(), $config, []);
}

it('sets variables and keeps their types', function () {
    $output = runDataNode(new SetVariableNode, ['variables' => ['total' => 42, 'tags' => ['a', 'b'], 'name' => 'Ada']]);

    expect($output)->toBe(['total' => 42, 'tags' => ['a', 'b'], 'name' => 'Ada']);
});

it('refuses a variable name later steps could not read', function (string $name) {
    runDataNode(new SetVariableNode, ['variables' => [$name => 'x']]);
})->throws(InvalidArgumentException::class)->with(['has space' => ['my var'], 'starts with digit' => ['1st'], 'dotted' => ['a.b']]);

it('runs text operations', function (array $config, mixed $expected) {
    expect(runDataNode(new StringNode, $config))->toBe(['result' => $expected]);
})->with([
    'trim' => [['operation' => 'trim', 'text' => "  hi \n"], 'hi'],
    'uppercase' => [['operation' => 'uppercase', 'text' => 'ada'], 'ADA'],
    'lowercase' => [['operation' => 'lowercase', 'text' => 'ADA'], 'ada'],
    'title case' => [['operation' => 'title_case', 'text' => 'ada lovelace'], 'Ada Lovelace'],
    'slugify' => [['operation' => 'slugify', 'text' => 'Hello, World! Ünï'], 'hello-world-uni'],
    'length counts characters' => [['operation' => 'length', 'text' => 'héllo'], 5],
    'replace' => [['operation' => 'replace', 'text' => 'a-b-c', 'search' => '-', 'replacement' => '+'], 'a+b+c'],
    'regex replace' => [['operation' => 'regex_replace', 'text' => 'ORD-12 ORD-34', 'pattern' => 'ORD-(\d+)', 'replacement' => '#$1'], '#12 #34'],
    'regex extract group' => [['operation' => 'regex_extract', 'text' => 'Order ORD-1234 shipped', 'pattern' => 'ORD-(\d+)'], '1234'],
    'regex extract whole match' => [['operation' => 'regex_extract', 'text' => 'ORD-1234', 'pattern' => 'ORD-\d+'], 'ORD-1234'],
    'regex extract none' => [['operation' => 'regex_extract', 'text' => 'nothing', 'pattern' => 'ORD-(\d+)'], null],
    'substring' => [['operation' => 'substring', 'text' => 'abcdef', 'start' => 1, 'length' => 3], 'bcd'],
    'substring to the end' => [['operation' => 'substring', 'text' => 'abcdef', 'start' => -2], 'ef'],
    'truncate' => [['operation' => 'truncate', 'text' => 'abcdefghij', 'length' => 5, 'ending' => '...'], 'abcde...'],
    'truncate keeps short text' => [['operation' => 'truncate', 'text' => 'abc', 'length' => 5], 'abc'],
    'split' => [['operation' => 'split', 'text' => 'a,b,c', 'separator' => ','], ['a', 'b', 'c']],
    'a number is text' => [['operation' => 'length', 'text' => 12345], 5],
]);

it('refuses unusable text input', function (array $config) {
    runDataNode(new StringNode, $config);
})->throws(InvalidArgumentException::class)->with([
    'unknown operation' => [['operation' => 'shout', 'text' => 'a']],
    'a list instead of text' => [['operation' => 'trim', 'text' => ['a']]],
    'replace without a search' => [['operation' => 'replace', 'text' => 'a']],
    'split without a separator' => [['operation' => 'split', 'text' => 'a']],
    'broken pattern' => [['operation' => 'regex_extract', 'text' => 'a', 'pattern' => '(']],
    'no pattern' => [['operation' => 'regex_replace', 'text' => 'a']],
]);

it('reads, writes and digs into JSON', function () {
    $node = new JsonNode;

    expect(runDataNode($node, ['operation' => 'parse', 'value' => '{"a":{"b":[1,2]}}']))->toBe(['result' => ['a' => ['b' => [1, 2]]]])
        ->and(runDataNode($node, ['operation' => 'parse', 'value' => ['already' => 'data']]))->toBe(['result' => ['already' => 'data']])
        ->and(runDataNode($node, ['operation' => 'stringify', 'value' => ['a' => 'é/b']]))->toBe(['result' => '{"a":"é/b"}'])
        ->and(runDataNode($node, ['operation' => 'get', 'value' => '{"a":{"b":[1,2]}}', 'path' => 'a.b.1']))->toBe(['result' => 2])
        ->and(runDataNode($node, ['operation' => 'get', 'value' => ['a' => 1], 'path' => 'x.y', 'default' => 'none']))->toBe(['result' => 'none'])
        ->and(runDataNode($node, ['operation' => 'get', 'value' => ['a' => 1], 'path' => 'x']))->toBe(['result' => null]);
});

it('explains bad JSON input', function (array $config) {
    runDataNode(new JsonNode, $config);
})->throws(InvalidArgumentException::class)->with([
    'not json' => [['operation' => 'parse', 'value' => '{nope']],
    'empty' => [['operation' => 'parse', 'value' => '  ']],
    'get without a path' => [['operation' => 'get', 'value' => ['a' => 1]]],
    'get from a number' => [['operation' => 'get', 'value' => 5, 'path' => 'a']],
    'unknown operation' => [['operation' => 'minify', 'value' => '{}']],
]);

it('works on lists', function (array $config, mixed $expected) {
    expect(runDataNode(new ArrayNode, $config))->toBe(['result' => $expected]);
})->with([
    'length' => [['operation' => 'length', 'items' => [1, 2, 3]], 3],
    'first' => [['operation' => 'first', 'items' => ['a', 'b']], 'a'],
    'last' => [['operation' => 'last', 'items' => ['a', 'b']], 'b'],
    'first of nothing' => [['operation' => 'first', 'items' => []], null],
    'join' => [['operation' => 'join', 'items' => ['a', 2, true], 'separator' => '|'], 'a|2|true'],
    'unique' => [['operation' => 'unique', 'items' => [1, '1', 2, 1]], [1, '1', 2]],
    'sort' => [['operation' => 'sort', 'items' => [3, 1, 2]], [1, 2, 3]],
    'sort descending' => [['operation' => 'sort', 'items' => [3, 1, 2], 'direction' => 'desc'], [3, 2, 1]],
    'sort by field' => [['operation' => 'sort', 'items' => [['n' => 2], ['n' => 1]], 'path' => 'n'], [['n' => 1], ['n' => 2]]],
    'reverse' => [['operation' => 'reverse', 'items' => [1, 2, 3]], [3, 2, 1]],
    'slice' => [['operation' => 'slice', 'items' => [1, 2, 3, 4], 'start' => 1, 'length' => 2], [2, 3]],
    'pluck' => [['operation' => 'pluck', 'items' => [['u' => ['e' => 'a']], ['u' => ['e' => 'b']], 'x'], 'path' => 'u.e'], ['a', 'b', null]],
    'flatten one level' => [['operation' => 'flatten', 'items' => [[1, 2], [3], 4]], [1, 2, 3, 4]],
    'a JSON string is a list' => [['operation' => 'length', 'items' => '[1,2]'], 2],
]);

it('refuses something that is not a list', function () {
    runDataNode(new ArrayNode, ['operation' => 'length', 'items' => 'plain text']);
})->throws(InvalidArgumentException::class);

it('calculates', function (array $config, int|float $expected) {
    expect(runDataNode(new MathNode, $config))->toBe(['result' => $expected]);
})->with([
    'add' => [['operation' => 'add', 'a' => '1.5', 'b' => 2], 3.5],
    'add whole numbers' => [['operation' => 'add', 'a' => 2, 'b' => 3], 5],
    'subtract' => [['operation' => 'subtract', 'a' => 1, 'b' => 3], -2],
    'multiply' => [['operation' => 'multiply', 'a' => '4', 'b' => '2.5'], 10],
    'divide' => [['operation' => 'divide', 'a' => 10, 'b' => 4], 2.5],
    'remainder' => [['operation' => 'modulo', 'a' => 10, 'b' => 3], 1],
    'power' => [['operation' => 'power', 'a' => 2, 'b' => 10], 1024],
    'round' => [['operation' => 'round', 'a' => 2.456, 'decimals' => 2], 2.46],
    'floor' => [['operation' => 'floor', 'a' => 2.9], 2],
    'ceil' => [['operation' => 'ceil', 'a' => 2.1], 3],
    'abs' => [['operation' => 'abs', 'a' => -5], 5],
    'sum' => [['operation' => 'sum', 'values' => ['1', 2, '3.5']], 6.5],
    'average' => [['operation' => 'average', 'values' => [2, 4]], 3],
    'min' => [['operation' => 'min', 'values' => [3, 1, 2]], 1],
    'max' => [['operation' => 'max', 'values' => [3, 1, 2]], 3],
]);

it('refuses a calculation it cannot do', function (array $config) {
    runDataNode(new MathNode, $config);
})->throws(InvalidArgumentException::class)->with([
    'divide by zero' => [['operation' => 'divide', 'a' => 1, 'b' => 0]],
    'remainder by zero' => [['operation' => 'modulo', 'a' => 1, 'b' => 0]],
    'not a number' => [['operation' => 'add', 'a' => 'abc', 'b' => 1]],
    'missing number' => [['operation' => 'add', 'a' => 1]],
    'too large' => [['operation' => 'power', 'a' => 10, 'b' => 1000]],
    'empty list' => [['operation' => 'sum', 'values' => []]],
    'non-number in the list' => [['operation' => 'sum', 'values' => [1, 'x']]],
    'unknown operation' => [['operation' => 'log', 'a' => 1]],
]);

it('works with dates', function () {
    $node = new DateTimeNode;

    expect(runDataNode($node, ['operation' => 'format', 'value' => '2026-03-01 14:30:00', 'format' => 'd/m/Y H:i']))
        ->toBe(['result' => '01/03/2026 14:30', 'timestamp' => 1772375400])
        ->and(runDataNode($node, ['operation' => 'add', 'value' => '2026-01-31T00:00:00Z', 'amount' => 2, 'unit' => 'days']))
        ->toBe(['result' => '2026-02-02T00:00:00+00:00', 'timestamp' => 1769990400])
        ->and(runDataNode($node, ['operation' => 'subtract', 'value' => '2026-03-01', 'amount' => 1, 'unit' => 'weeks'])['result'])
        ->toBe('2026-02-22T00:00:00+00:00')
        ->and(runDataNode($node, ['operation' => 'difference', 'value' => '2026-03-01', 'other' => '2026-03-15', 'unit' => 'days']))
        ->toBe(['result' => 14])
        ->and(runDataNode($node, ['operation' => 'difference', 'value' => '2026-03-15', 'other' => '2026-03-01', 'unit' => 'weeks']))
        ->toBe(['result' => -2])
        ->and(runDataNode($node, ['operation' => 'difference', 'value' => '2026-03-01 00:00', 'other' => '2026-03-01 00:45', 'unit' => 'hours']))
        ->toBe(['result' => 0.75]);
});

it('reads dates in the chosen timezone and from timestamps', function () {
    $node = new DateTimeNode;

    expect(runDataNode($node, ['operation' => 'format', 'value' => '2026-03-01 09:00:00', 'timezone' => 'America/New_York', 'format' => 'c'])['result'])
        ->toBe('2026-03-01T09:00:00-05:00')
        ->and(runDataNode($node, ['operation' => 'format', 'value' => '1772375400', 'timezone' => 'UTC', 'format' => 'Y-m-d H:i'])['result'])
        ->toBe('2026-03-01 14:30')
        ->and(runDataNode($node, ['operation' => 'now'])['timestamp'])->toBeInt();
});

it('refuses a date step it cannot do', function (array $config) {
    runDataNode(new DateTimeNode, $config);
})->throws(InvalidArgumentException::class)->with([
    'not a date' => [['operation' => 'format', 'value' => 'next blue moon']],
    'unknown timezone' => [['operation' => 'now', 'timezone' => 'Mars/Olympus']],
    'unknown operation' => [['operation' => 'tomorrow']],
    'no amount' => [['operation' => 'add', 'value' => '2026-03-01']],
    'negative amount' => [['operation' => 'add', 'value' => '2026-03-01', 'amount' => -1, 'unit' => 'days']],
    'unknown unit' => [['operation' => 'add', 'value' => '2026-03-01', 'amount' => 1, 'unit' => 'fortnights']],
    'months cannot be measured' => [['operation' => 'difference', 'value' => '2026-03-01', 'other' => '2026-04-01', 'unit' => 'months']],
    'no second date' => [['operation' => 'difference', 'value' => '2026-03-01', 'unit' => 'days']],
]);

it('lists the data nodes in the editor with a title, widget and output schema', function () {
    $catalog = collect(app(NodeRegistry::class)->catalog())->keyBy('type');

    foreach (['set_variable', 'string', 'json', 'array', 'math', 'date_time'] as $type) {
        expect($catalog)->toHaveKey($type)
            ->and($catalog[$type]['category'])->toBe('data-transform')
            ->and($catalog[$type]['requires_connector'])->toBeFalse()
            ->and($catalog[$type]['node_kind'])->toBe('action');
    }

    expect($catalog['string']['output_schema']['properties'])->toHaveKey('result');
});

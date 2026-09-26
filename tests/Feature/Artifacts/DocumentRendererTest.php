<?php

use App\Services\Artifacts\DocumentRenderer;
use PhpOffice\PhpSpreadsheet\IOFactory as SpreadsheetIOFactory;
use PhpOffice\PhpWord\IOFactory as WordIOFactory;

function readBack(string $bytes, callable $load): mixed
{
    $path = tempnam(sys_get_temp_dir(), 'test');
    file_put_contents($path, $bytes);

    try {
        return $load($path);
    } finally {
        @unlink($path);
    }
}

it('renders Markdown into a PDF', function () {
    $pdf = app(DocumentRenderer::class)->pdf("# Q4 Report\n\n| Owner | Task |\n|---|---|\n| Ravi | Budget |");

    expect($pdf)->toStartWith('%PDF-');
});

it('renders a full HTML document into a PDF without fetching remote resources', function () {
    $pdf = app(DocumentRenderer::class)->pdf('<!doctype html><html><body><h1>Hi</h1><img src="http://127.0.0.1:1/x.png"></body></html>');

    expect($pdf)->toStartWith('%PDF-');
});

it('builds a spreadsheet from CSV', function () {
    $xlsx = app(DocumentRenderer::class)->xlsx("Owner,Task\nRavi,\"Budget, Q4\"");

    $sheet = readBack($xlsx, fn ($path) => SpreadsheetIOFactory::load($path)->getActiveSheet());

    expect($sheet->getCell('A1')->getValue())->toBe('Owner');
    expect($sheet->getCell('B2')->getValue())->toBe('Budget, Q4');
});

it('builds one worksheet per sheet from JSON, including rows given as objects', function () {
    $xlsx = app(DocumentRenderer::class)->xlsx(json_encode(['sheets' => [
        ['name' => 'Tasks', 'rows' => [['Owner', 'Task'], ['Ravi', 'Budget']]],
        ['name' => 'People/Team', 'rows' => [['name' => 'Sara', 'role' => 'Ops']]],
    ]]));

    $book = readBack($xlsx, fn ($path) => SpreadsheetIOFactory::load($path));

    expect($book->getSheetNames())->toBe(['Tasks', 'People Team']);
    expect($book->getSheet(1)->getCell('A1')->getValue())->toBe('name');
    expect($book->getSheet(1)->getCell('B2')->getValue())->toBe('Ops');
});

it('builds a Word document from Markdown and drops raw HTML', function () {
    $docx = app(DocumentRenderer::class)->docx("# Minutes\n\nRavi drafts the **budget**.\n\n<script>alert(1)</script>");

    $text = readBack($docx, function ($path) {
        $text = '';
        foreach (WordIOFactory::load($path)->getSections() as $section) {
            foreach ($section->getElements() as $element) {
                if (method_exists($element, 'getText')) {
                    $text .= is_string($element->getText()) ? $element->getText() : '';
                }
                if (method_exists($element, 'getElements')) {
                    foreach ($element->getElements() as $child) {
                        $text .= method_exists($child, 'getText') && is_string($child->getText()) ? $child->getText() : '';
                    }
                }
            }
        }

        return $text;
    });

    expect($text)->toContain('Minutes')->toContain('budget')->not->toContain('alert');
});

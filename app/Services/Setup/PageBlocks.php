<?php

namespace App\Services\Setup;

use App\Services\Workflows\Pages\PageSchema;

/**
 * Builders for the page blocks used by `database/setup/page-templates.php`, so
 * a template reads as a list of headings, fields and outputs.
 */
final class PageBlocks
{
    public static function photo(string $id): string
    {
        return "https://images.unsplash.com/photo-{$id}?auto=format&fit=crop&w=1600&q=80";
    }

    /**
     * @return array<string, mixed>
     */
    public static function heading(string $text, int $level, string $align = 'left'): array
    {
        return self::block('heading', ['text' => $text, 'level' => $level, 'align' => $align]);
    }

    /**
     * @return array<string, mixed>
     */
    public static function text(string $markdown, string $align = 'left'): array
    {
        return self::block('text', ['markdown' => $markdown, 'align' => $align]);
    }

    /**
     * @return array<string, mixed>
     */
    public static function callout(string $tone, string $title, string $markdown, string $width = 'full'): array
    {
        return self::block('callout', ['tone' => $tone, 'title' => $title, 'markdown' => $markdown], $width);
    }

    /**
     * @return array<string, mixed>
     */
    public static function image(string $url, string $alt, string $height): array
    {
        return self::block('image', ['url' => $url, 'alt' => $alt, 'fit' => 'cover', 'height' => $height]);
    }

    /**
     * An unbound field slot, labelled by the workflow input it gets.
     *
     * @return array<string, mixed>
     */
    public static function field(string $width = 'full'): array
    {
        return self::block('field', [], $width);
    }

    /**
     * @return array<string, mixed>
     */
    public static function submit(string $label, string $runningLabel, string $align): array
    {
        return self::block('submit', ['label' => $label, 'running_label' => $runningLabel, 'align' => $align]);
    }

    /**
     * @return array<string, mixed>
     */
    public static function status(string $idleText): array
    {
        return self::block('status', ['show_progress' => true, 'idle_text' => $idleText]);
    }

    /**
     * An unbound output slot with the look the template gives its result.
     *
     * @return array<string, mixed>
     */
    public static function output(string $label, string $display, string $emptyText, string $width = 'full'): array
    {
        return self::block('output', ['label' => $label, 'display' => $display, 'empty_text' => $emptyText, 'copyable' => true], $width);
    }

    /**
     * @param  array<string, mixed>  $props
     * @return array<string, mixed>
     */
    public static function block(string $type, array $props, string $width = 'full'): array
    {
        return ['id' => PageSchema::newBlockId(), 'type' => $type, 'width' => $width, 'props' => $props];
    }
}

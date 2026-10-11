<?php

namespace App\Services\Workflows\Pages;

use App\Enums\Workflows\PageBlockType;
use App\Models\Workflows\Workflow;
use App\Services\Workflows\WorkflowInterface;
use Illuminate\Support\Str;

/**
 * The shape of a workflow page, and what's wrong with one.
 *
 * `normalize()` turns whatever the builder sent into the canonical content
 * every reader can rely on: known block types only, every prop present with
 * a sane value, unsafe URLs dropped. Every block keeps at least one prop, so
 * `props` never round-trips through PHP as `[]` instead of `{}`.
 *
 * `issues()` checks the content against the workflow it runs — that each
 * field fills in an input the workflow has, that nothing the workflow
 * requires is missing, and that each output block shows a value the
 * workflow returns. Errors block publishing; warnings don't.
 *
 * @phpstan-type Content array{blocks: array<int, array<string, mixed>>, theme: array<string, mixed>}
 * @phpstan-type Issue array{severity: 'error'|'warning', code: string, message: string, block_id: ?string}
 */
class PageSchema
{
    public const int MAX_BLOCKS = 150;

    public const array WIDTHS = ['full', 'two_thirds', 'half', 'third'];

    private const array ALIGN = ['left', 'center', 'right'];

    private const string ID_PATTERN = '/^[A-Za-z0-9_-]{1,64}$/';

    public function __construct(private readonly WorkflowInterface $interface) {}

    /**
     * @param  array<string, mixed>  $content
     * @return Content
     */
    public function normalize(array $content): array
    {
        $blocks = [];
        $seen = [];

        foreach (array_slice(array_values(is_array($content['blocks'] ?? null) ? $content['blocks'] : []), 0, self::MAX_BLOCKS) as $block) {
            $type = is_array($block) ? PageBlockType::tryFrom((string) ($block['type'] ?? '')) : null;

            if ($type === null) {
                continue;
            }

            $id = (string) ($block['id'] ?? '');

            if (preg_match(self::ID_PATTERN, $id) !== 1 || isset($seen[$id])) {
                $id = self::newBlockId();
            }

            $seen[$id] = true;

            $blocks[] = [
                'id' => $id,
                'type' => $type->value,
                'width' => self::oneOf($block['width'] ?? null, self::WIDTHS),
                'props' => $this->props($type, is_array($block['props'] ?? null) ? $block['props'] : []),
            ];
        }

        return [
            'blocks' => $blocks,
            'theme' => $this->theme(is_array($content['theme'] ?? null) ? $content['theme'] : []),
        ];
    }

    /**
     * A starting layout for a new page: a title, one field per input the
     * workflow asks for, a Run button and one block per value it returns.
     *
     * @return Content
     */
    public function starter(Workflow $workflow, string $title): array
    {
        $blocks = [
            self::block(PageBlockType::Heading, ['text' => $title, 'level' => 1]),
        ];

        if (filled($workflow->description)) {
            $blocks[] = self::block(PageBlockType::Text, ['markdown' => $workflow->description]);
        }

        foreach ($this->interface->describe($workflow)['fields'] as $field) {
            $blocks[] = self::block(PageBlockType::Field, ['field_key' => $field['key']]);
        }

        $blocks[] = self::block(PageBlockType::Submit, ['label' => 'Run']);

        $outputs = $this->interface->outputs($workflow);

        if ($outputs !== []) {
            $blocks[] = self::block(PageBlockType::Divider, []);
            $blocks[] = self::block(PageBlockType::Status, []);

            foreach ($outputs as $output) {
                $blocks[] = self::block(PageBlockType::Output, ['output_name' => $output['name']]);
            }
        } else {
            $blocks[] = self::block(PageBlockType::Status, []);
        }

        return $this->normalize(['blocks' => $blocks, 'theme' => []]);
    }

    /**
     * @param  Content  $content
     * @return array<int, Issue>
     */
    public function issues(array $content, ?Workflow $workflow): array
    {
        if ($workflow === null || $workflow->trashed()) {
            return [self::issue('error', 'workflow_missing', 'The workflow this page runs is in the trash. Restore it, or delete this page.')];
        }

        $issues = [];

        if (! $workflow->isPublished()) {
            $issues[] = self::issue('error', 'workflow_unpublished', 'Publish the workflow first. A page can only run its published version.');
        }

        $fields = collect($this->interface->describe($workflow)['fields'])->keyBy('key');
        $outputNames = array_column($this->interface->outputs($workflow), 'name');
        $blocks = $content['blocks'];
        $placed = [];

        foreach ($blocks as $block) {
            if ($block['type'] !== PageBlockType::Field->value) {
                continue;
            }

            $key = $block['props']['field_key'];

            if ($key === '') {
                $issues[] = self::issue('error', 'unbound_field', 'Choose which workflow input this field fills in.', $block['id']);
            } elseif (! $fields->has($key)) {
                $issues[] = self::issue('error', 'unknown_field', "The workflow has no input called \"{$key}\" any more. Pick another or remove this field.", $block['id']);
            } elseif (isset($placed[$key])) {
                $issues[] = self::issue('error', 'duplicate_field', "\"{$this->label($fields[$key])}\" is already on the page. Each input can appear once.", $block['id']);
            } else {
                $placed[$key] = true;
            }
        }

        foreach ($fields as $key => $field) {
            if ($field['required'] && $field['default'] === null && ! isset($placed[$key])) {
                $issues[] = self::issue('error', 'missing_required_field', "The workflow needs \"{$this->label($field)}\", but it isn't on the page. Add a field for it.");
            }
        }

        if (! collect($blocks)->contains('type', PageBlockType::Submit->value)) {
            $issues[] = self::issue('error', 'no_submit', 'Add a Run button so people can start the workflow.');
        }

        $showsResult = false;

        foreach ($blocks as $block) {
            if ($block['type'] === PageBlockType::Status->value) {
                $showsResult = true;
            }

            if ($block['type'] !== PageBlockType::Output->value) {
                continue;
            }

            $showsResult = true;
            $name = $block['props']['output_name'];

            if ($name === '') {
                $issues[] = self::issue('error', 'unbound_output', 'Choose which workflow output this block shows.', $block['id']);
            } elseif ($outputNames === []) {
                $issues[] = self::issue('error', 'unknown_output', 'The workflow has no Output steps. Add one to the workflow to show its result here.', $block['id']);
            } elseif (! in_array($name, $outputNames, true)) {
                $issues[] = self::issue('error', 'unknown_output', "The workflow doesn't return \"{$name}\" any more. Pick another output or remove this block.", $block['id']);
            }
        }

        if (! $showsResult) {
            $issues[] = self::issue('warning', 'no_result', 'Nothing on the page shows the result. Add an Output block so people see what the workflow returns.');
        }

        return $issues;
    }

    /**
     * @param  array<int, Issue>  $issues
     */
    public static function hasErrors(array $issues): bool
    {
        return collect($issues)->contains('severity', 'error');
    }

    /**
     * The output names a page's content shows — the only values of a run's
     * result a visitor ever receives.
     *
     * @param  Content  $content
     * @return array<int, string>
     */
    public static function shownOutputs(array $content): array
    {
        return collect($content['blocks'])
            ->where('type', PageBlockType::Output->value)
            ->pluck('props.output_name')
            ->filter(fn (string $name): bool => $name !== '')
            ->unique()
            ->values()
            ->all();
    }

    public static function newBlockId(): string
    {
        return 'b_'.Str::lower(Str::random(10));
    }

    /**
     * @param  array<string, mixed>  $props
     * @return array<string, mixed>
     */
    private static function block(PageBlockType $type, array $props): array
    {
        return ['id' => self::newBlockId(), 'type' => $type->value, 'width' => 'full', 'props' => $props];
    }

    /**
     * @param  array<string, mixed>  $p
     * @return array<string, mixed>
     */
    private function props(PageBlockType $type, array $p): array
    {
        return match ($type) {
            PageBlockType::Heading => [
                'text' => self::text($p['text'] ?? null, 300) ?? '',
                'level' => self::oneOf((int) ($p['level'] ?? 2), [2, 1, 3]),
                'align' => self::oneOf($p['align'] ?? null, self::ALIGN),
            ],
            PageBlockType::Text => [
                'markdown' => self::text($p['markdown'] ?? null, 20000) ?? '',
                'align' => self::oneOf($p['align'] ?? null, self::ALIGN),
            ],
            PageBlockType::Image => [
                'url' => self::url($p['url'] ?? null),
                'alt' => self::text($p['alt'] ?? null, 300),
                'fit' => self::oneOf($p['fit'] ?? null, ['cover', 'contain']),
                'height' => self::oneOf($p['height'] ?? null, ['md', 'sm', 'lg', 'auto']),
            ],
            PageBlockType::Divider => [
                'style' => self::oneOf($p['style'] ?? null, ['solid', 'dashed']),
            ],
            PageBlockType::Spacer => [
                'size' => self::oneOf($p['size'] ?? null, ['md', 'sm', 'lg', 'xl']),
            ],
            PageBlockType::Callout => [
                'tone' => self::oneOf($p['tone'] ?? null, ['info', 'success', 'warning', 'danger']),
                'title' => self::text($p['title'] ?? null, 200),
                'markdown' => self::text($p['markdown'] ?? null, 5000) ?? '',
            ],
            PageBlockType::Field => [
                'field_key' => self::text($p['field_key'] ?? null, 255) ?? '',
                'label' => self::text($p['label'] ?? null, 200),
                'placeholder' => self::text($p['placeholder'] ?? null, 200),
                'help' => self::text($p['help'] ?? null, 1000),
                'variant' => self::oneOf($p['variant'] ?? null, ['auto', 'input', 'textarea', 'dropdown', 'radio', 'checkbox', 'switch']),
            ],
            PageBlockType::Submit => [
                'label' => self::text($p['label'] ?? null, 80) ?? 'Run',
                'running_label' => self::text($p['running_label'] ?? null, 80) ?? 'Running…',
                'align' => self::oneOf($p['align'] ?? null, ['left', 'center', 'right', 'stretch']),
            ],
            PageBlockType::Output => [
                'output_name' => self::text($p['output_name'] ?? null, 255) ?? '',
                'label' => self::text($p['label'] ?? null, 200),
                'display' => self::oneOf($p['display'] ?? null, ['auto', 'text', 'markdown', 'json', 'table', 'image', 'link']),
                'empty_text' => self::text($p['empty_text'] ?? null, 300),
                'copyable' => (bool) ($p['copyable'] ?? true),
            ],
            PageBlockType::Status => [
                'show_progress' => (bool) ($p['show_progress'] ?? true),
                'idle_text' => self::text($p['idle_text'] ?? null, 300),
            ],
        };
    }

    /**
     * @param  array<string, mixed>  $t
     * @return array<string, mixed>
     */
    private function theme(array $t): array
    {
        $accent = (string) ($t['accent'] ?? '');

        return [
            'accent' => preg_match('/^#[0-9a-fA-F]{6}$/', $accent) === 1 ? strtolower($accent) : '#4f46e5',
            'appearance' => self::oneOf($t['appearance'] ?? null, ['light', 'dark', 'auto']),
            'background' => self::oneOf($t['background'] ?? null, ['soft', 'plain', 'dots', 'gradient']),
            'radius' => self::oneOf($t['radius'] ?? null, ['lg', 'none', 'sm', 'md', 'xl']),
            'font' => self::oneOf($t['font'] ?? null, ['sans', 'serif', 'mono']),
            'width' => self::oneOf($t['width'] ?? null, ['medium', 'narrow', 'wide']),
            'logo_url' => self::url($t['logo_url'] ?? null),
            'cover_url' => self::url($t['cover_url'] ?? null),
            'show_branding' => (bool) ($t['show_branding'] ?? true),
        ];
    }

    /**
     * @param  array<string, mixed>  $field
     */
    private function label(array $field): string
    {
        return (string) ($field['label'] ?: $field['key']);
    }

    /**
     * @return Issue
     */
    private static function issue(string $severity, string $code, string $message, ?string $blockId = null): array
    {
        return ['severity' => $severity, 'code' => $code, 'message' => $message, 'block_id' => $blockId];
    }

    /**
     * The value when it's one of `$allowed`, else the first allowed value.
     *
     * @param  array<int, mixed>  $allowed
     */
    private static function oneOf(mixed $value, array $allowed): mixed
    {
        return in_array($value, $allowed, true) ? $value : $allowed[0];
    }

    private static function text(mixed $value, int $max): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : Str::limit($value, $max, '');
    }

    /**
     * Only absolute http(s) URLs — anything else (`javascript:`, `data:`)
     * could run in a visitor's browser.
     */
    private static function url(mixed $value): ?string
    {
        $value = self::text($value, 2048);

        if ($value === null || filter_var($value, FILTER_VALIDATE_URL) === false) {
            return null;
        }

        return in_array(strtolower((string) parse_url($value, PHP_URL_SCHEME)), ['http', 'https'], true) ? $value : null;
    }
}

<?php

namespace App\Services\Workflows\Pages;

use App\Enums\Workflows\PageAccess;
use App\Enums\Workflows\PageBlockType;
use App\Models\Workflows\WorkflowPage;
use App\Services\Workflows\WorkflowInterface;

/**
 * A page as the person using it receives it: its blocks, with each field
 * carrying the interface definition it fills in (type, choices, default,
 * whether it's required), and nothing about the workflow behind it — no
 * graph, no step names, no ids.
 *
 * A field whose input the workflow no longer has is left out rather than
 * shown as a control that submits to nothing.
 */
class PagePresenter
{
    public function __construct(private readonly WorkflowInterface $interface) {}

    /**
     * @param  array{blocks: array<int, array<string, mixed>>, theme: array<string, mixed>}  $content
     * @return array<string, mixed>
     */
    public function present(WorkflowPage $page, array $content, bool $locked = false): array
    {
        $view = [
            'name' => $page->name,
            'description' => $page->description,
            'workspace_name' => $page->workspace?->name,
            'theme' => $content['theme'],
            'locked' => $locked,
            'requires_password' => $page->access === PageAccess::Password,
            'blocks' => [],
        ];

        if ($locked || $page->workflow === null || $page->workflow->trashed()) {
            return $view;
        }

        $fields = collect($this->interface->describe($page->workflow)['fields'])->keyBy('key');

        foreach ($content['blocks'] as $block) {
            if ($block['type'] === PageBlockType::Field->value) {
                $field = $fields->get($block['props']['field_key']);

                if ($field === null) {
                    continue;
                }

                $block['field'] = $field;
            }

            $view['blocks'][] = $block;
        }

        return $view;
    }
}

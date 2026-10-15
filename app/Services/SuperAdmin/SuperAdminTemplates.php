<?php

namespace App\Services\SuperAdmin;

use App\Models\SuperAdmin\SuperAdmin;
use App\Models\Templates\AgentTemplate;
use App\Models\Templates\TemplateCollection;
use App\Models\Templates\TemplateCollectionItem;
use App\Models\Templates\WorkflowTemplate;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Curates the template gallery: the global (`workspace_id` null) workflow
 * templates, agent templates and collections every workspace can browse.
 *
 * Only gallery rows are ever changed here. A customer's own templates can be
 * copied into the gallery, never edited in place, and a copy starts private
 * so it can be reviewed before anyone sees it. `(workspace_id, slug)` is
 * unique in the schema, but NULLs never collide in a unique index, so
 * gallery slugs are kept unique here.
 */
class SuperAdminTemplates
{
    /**
     * @var array<string, class-string<WorkflowTemplate|AgentTemplate>>
     */
    public const array KINDS = [
        'workflow' => WorkflowTemplate::class,
        'agent' => AgentTemplate::class,
    ];

    /**
     * Morph alias for each kind, as stored on collection items.
     *
     * @var array<string, string>
     */
    public const array MORPH_TYPES = [
        'workflow' => 'workflow_template',
        'agent' => 'agent_template',
    ];

    public function __construct(private readonly SuperAdminAuditLogger $audit) {}

    /**
     * @return class-string<WorkflowTemplate|AgentTemplate>
     */
    public function modelFor(string $kind): string
    {
        return self::KINDS[$kind] ?? throw new NotFoundHttpException;
    }

    public function find(string $kind, string $id): WorkflowTemplate|AgentTemplate
    {
        return $this->modelFor($kind)::query()->findOrFail($id);
    }

    public function findGallery(string $kind, string $id): WorkflowTemplate|AgentTemplate
    {
        return $this->modelFor($kind)::query()->whereNull('workspace_id')->findOrFail($id);
    }

    /**
     * @param  array{name: string, slug?: string|null, description?: string|null, category?: string|null, icon?: string|null, color?: string|null}  $data
     */
    public function update(WorkflowTemplate|AgentTemplate $template, array $data, SuperAdmin $actor): void
    {
        $this->assertGallery($template);

        DB::transaction(function () use ($template, $data, $actor): void {
            $slug = filled($data['slug'] ?? null) ? Str::slug($data['slug']) : $template->slug;

            $template->fill([
                ...collect($data)->only(['name', 'description', 'category', 'icon', 'color'])->all(),
                'slug' => $this->uniqueSlug($template::class, $slug, $template->id),
            ]);

            $changes = $template->getDirty();
            $before = collect($template->getOriginal())->only(array_keys($changes))->all();

            $template->save();

            if ($changes !== []) {
                $this->audit->record($actor, 'template.updated', $template, $before, $changes);
            }
        });
    }

    public function setPublished(WorkflowTemplate|AgentTemplate|TemplateCollection $item, bool $published, SuperAdmin $actor): void
    {
        $this->assertGallery($item);

        $visibility = $published ? 'public' : 'private';

        if ($item->visibility === $visibility) {
            return;
        }

        DB::transaction(function () use ($item, $visibility, $actor): void {
            $before = $item->visibility;
            $item->forceFill(['visibility' => $visibility])->save();

            $this->audit->record($actor, $visibility === 'public' ? 'template.published' : 'template.unpublished', $item, ['visibility' => $before], ['visibility' => $visibility]);
        });
    }

    /**
     * Copies a workspace's template into the gallery as a private draft. The
     * graph or config is copied as it is, so review it before publishing.
     */
    public function copyToGallery(WorkflowTemplate|AgentTemplate $template, SuperAdmin $actor): WorkflowTemplate|AgentTemplate
    {
        if ($template->workspace_id === null) {
            throw new NotFoundHttpException;
        }

        return DB::transaction(function () use ($template, $actor): WorkflowTemplate|AgentTemplate {
            $copy = $template->replicate(['usage_count', 'workspace_id', 'created_by', 'source_workflow_id', 'source_agent_id']);
            $copy->forceFill([
                'workspace_id' => null,
                'created_by' => null,
                'visibility' => 'private',
                'usage_count' => 0,
                'slug' => $this->uniqueSlug($template::class, $template->slug),
            ])->save();

            $this->audit->record($actor, 'template.copied_to_gallery', $copy, null, [
                'source_template_id' => $template->id,
                'source_workspace_id' => $template->workspace_id,
            ]);

            return $copy;
        });
    }

    /**
     * Removes a gallery template and its places in any collection.
     */
    public function delete(WorkflowTemplate|AgentTemplate $template, string $kind, SuperAdmin $actor): void
    {
        $this->assertGallery($template);

        DB::transaction(function () use ($template, $kind, $actor): void {
            TemplateCollectionItem::query()
                ->where('templatable_type', self::MORPH_TYPES[$kind])
                ->where('templatable_id', $template->id)
                ->delete();

            $template->delete();

            $this->audit->record($actor, 'template.deleted', $template, ['name' => $template->name], null);
        });
    }

    /**
     * @param  array{name: string, slug?: string|null, description?: string|null, category?: string|null, icon?: string|null, color?: string|null}  $data
     */
    public function saveCollection(?TemplateCollection $collection, array $data, SuperAdmin $actor): TemplateCollection
    {
        if ($collection !== null) {
            $this->assertGallery($collection);
        }

        return DB::transaction(function () use ($collection, $data, $actor): TemplateCollection {
            $collection ??= new TemplateCollection(['visibility' => 'private']);
            $creating = ! $collection->exists;
            $slug = filled($data['slug'] ?? null) ? Str::slug($data['slug']) : ($collection->slug ?? Str::slug($data['name']));

            $collection->fill([
                ...collect($data)->only(['name', 'description', 'category', 'icon', 'color'])->all(),
                'slug' => $this->uniqueSlug(TemplateCollection::class, $slug, $collection->id),
            ]);
            $collection->workspace_id = null;
            $changes = $collection->getDirty();
            $collection->save();

            $this->audit->record($actor, $creating ? 'template_collection.created' : 'template_collection.updated', $collection, null, $changes);

            return $collection;
        });
    }

    public function deleteCollection(TemplateCollection $collection, SuperAdmin $actor): void
    {
        $this->assertGallery($collection);

        DB::transaction(function () use ($collection, $actor): void {
            $collection->items()->delete();
            $collection->delete();

            $this->audit->record($actor, 'template_collection.deleted', $collection, ['name' => $collection->name], null);
        });
    }

    /**
     * Only gallery templates can join a gallery collection, so a collection
     * never hands out a customer's private template.
     */
    public function addToCollection(TemplateCollection $collection, string $kind, string $templateId, SuperAdmin $actor): void
    {
        $this->assertGallery($collection);
        $template = $this->findGallery($kind, $templateId);

        DB::transaction(function () use ($collection, $kind, $template, $actor): void {
            $exists = $collection->items()
                ->where('templatable_type', self::MORPH_TYPES[$kind])
                ->where('templatable_id', $template->id)
                ->exists();

            if ($exists) {
                return;
            }

            $collection->items()->create([
                'templatable_type' => self::MORPH_TYPES[$kind],
                'templatable_id' => $template->id,
                // Not `items()->max()`: that relation carries an ORDER BY, which Postgres rejects next to an aggregate.
                'position' => (int) TemplateCollectionItem::query()->where('collection_id', $collection->id)->max('position') + 1,
            ]);

            $this->audit->record($actor, 'template_collection.item_added', $collection, null, ['kind' => $kind, 'template_id' => $template->id]);
        });
    }

    public function removeFromCollection(TemplateCollection $collection, TemplateCollectionItem $item, SuperAdmin $actor): void
    {
        $this->assertGallery($collection);

        if ($item->collection_id !== $collection->id) {
            throw new NotFoundHttpException;
        }

        DB::transaction(function () use ($collection, $item, $actor): void {
            $item->delete();

            $this->audit->record($actor, 'template_collection.item_removed', $collection, ['template_id' => $item->templatable_id], null);
        });
    }

    public function moveInCollection(TemplateCollection $collection, TemplateCollectionItem $item, string $direction, SuperAdmin $actor): void
    {
        $this->assertGallery($collection);

        if ($item->collection_id !== $collection->id) {
            throw new NotFoundHttpException;
        }

        DB::transaction(function () use ($collection, $item, $direction, $actor): void {
            $items = $collection->items()->orderBy('position')->get()->values();
            $index = $items->search(fn (TemplateCollectionItem $candidate): bool => $candidate->is($item));
            $swapWith = $direction === 'up' ? $index - 1 : $index + 1;

            if ($index === false || ! isset($items[$swapWith])) {
                return;
            }

            $ordered = $items->all();
            [$ordered[$index], $ordered[$swapWith]] = [$ordered[$swapWith], $ordered[$index]];

            foreach ($ordered as $position => $row) {
                $row->forceFill(['position' => $position])->save();
            }

            $this->audit->record($actor, 'template_collection.reordered', $collection, null, ['item_ids' => array_map(fn ($row) => $row->id, $ordered)]);
        });
    }

    /**
     * @param  class-string<Model>  $model
     */
    private function uniqueSlug(string $model, string $base, ?string $ignoreId = null): string
    {
        $base = Str::slug($base) ?: 'template';
        $slug = $base;
        $suffix = 2;

        while ($model::query()->withTrashed()->whereNull('workspace_id')->where('slug', $slug)->when($ignoreId, fn ($query) => $query->whereKeyNot($ignoreId))->exists()) {
            $slug = "{$base}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }

    private function assertGallery(WorkflowTemplate|AgentTemplate|TemplateCollection $item): void
    {
        if ($item->workspace_id !== null) {
            throw new NotFoundHttpException;
        }
    }
}

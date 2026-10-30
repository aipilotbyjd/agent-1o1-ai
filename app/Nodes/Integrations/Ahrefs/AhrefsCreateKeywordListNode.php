<?php

namespace App\Nodes\Integrations\Ahrefs;

use App\Enums\Agents\ActionEffect;
use App\Nodes\Support\Field;

class AhrefsCreateKeywordListNode extends AbstractAhrefsReportNode
{
    public function type(): string
    {
        return 'ahrefs_create_keyword_list';
    }

    public function name(): string
    {
        return 'Ahrefs: Create Keyword List';
    }

    public function description(): string
    {
        return 'Creates a keyword list.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['name', 'location'],
            'properties' => [
                ...$this->credentialFields(),
                'name' => Field::text('Name', 'The name of the keyword list.'),
                'location' => Field::text('Location', 'The location of the keyword list.'),
                'countries' => Field::advanced(Field::list('Countries', 'Up to 15 country codes to select when location is \'all\'.')),
                'keywords' => Field::list('Keywords', 'Initial keywords to add.'),
                'access' => Field::advanced(Field::select('Access', ['private', 'shared'], 'The access type of the keyword list.', 'private')),
                'owned_by' => Field::advanced(Field::text('Owned by', 'The email of the keyword list owner.')),
                'folder_id' => Field::advanced(Field::integer('Folder id', 'The ID of the folder to assign the keyword list to.')),
            ],
        ];
    }

    protected function endpoint(): array
    {
        return [
            'method' => 'POST',
            'path' => '/management/keyword-lists',
            'items' => 'keyword_list',
            'body' => ['name', 'location', 'countries', 'keywords', 'access', 'owned_by', 'folder_id'],
            'lists' => ['countries', 'keywords'],
            'integers' => ['folder_id'],
        ];
    }
}

<?php

namespace App\Nodes\Integrations\Firecrawl;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;
use InvalidArgumentException;

class FirecrawlParseFileNode extends AbstractFirecrawlNode
{
    public function type(): string
    {
        return 'firecrawl_parse_file';
    }

    public function name(): string
    {
        return 'Firecrawl: Parse File';
    }

    public function description(): string
    {
        return 'Converts a file\'s content (HTML or CSV text, or base64 for PDF, Word and other files) into clean markdown. For a document on the web, use Scrape Page.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['file_name', 'content'],
            'properties' => [
                ...$this->credentialFields(),
                'file_name' => Field::text('File name', 'Its extension says what it is: .html, .pdf, .docx, .xlsx, .pptx, .csv...', 'report.pdf'),
                'content' => Field::textarea('Content', 'The text of the file, or its bytes as base64.'),
                'is_base64' => Field::boolean('Content is base64'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $name = $this->requiredString($config, 'file_name', 'The file name');

        if (preg_match('/^[\w .()-]{1,200}\.[A-Za-z0-9]{1,8}$/u', $name) !== 1) {
            throw new InvalidArgumentException('The file name must be a plain name with an extension.');
        }

        $content = (string) ($config['content'] ?? '');
        $bytes = $this->boolOf($config, 'is_base64') ? base64_decode($content, true) : $content;

        if ($bytes === false || $bytes === '') {
            throw new InvalidArgumentException('The content is empty, or is not valid base64.');
        }

        return $this->shapePage((array) ($this->firecrawlUpload($run, $config, '/parse', $name, $bytes)['data'] ?? []));
    }
}

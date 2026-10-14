<?php

namespace App\Http\Requests\Admin\Concerns;

/**
 * The `q` filter as a LIKE pattern, with `%`, `_` and `\` escaped so a search
 * matches what was typed instead of acting as a wildcard.
 */
trait HasSearchTerm
{
    public function searchPattern(): ?string
    {
        $term = trim((string) $this->validated('q'));

        return $term === '' ? null : '%'.addcslashes($term, '%_\\').'%';
    }
}

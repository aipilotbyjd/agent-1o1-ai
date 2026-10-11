<?php

namespace App\Enums\Workflows;

/**
 * The building blocks of a workflow page. `Field`, `Submit`, `Output` and
 * `Status` are bound to the workflow — a field to one of its interface
 * fields, an output to one of its Output steps; the rest are content.
 */
enum PageBlockType: string
{
    case Heading = 'heading';
    case Text = 'text';
    case Image = 'image';
    case Divider = 'divider';
    case Spacer = 'spacer';
    case Callout = 'callout';
    case Field = 'field';
    case Submit = 'submit';
    case Output = 'output';
    case Status = 'status';
}

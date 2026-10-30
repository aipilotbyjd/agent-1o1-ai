<?php

namespace App\Enums;

/**
 * Who makes an integration. Never a browse section: the Apps panel offers a
 * vendor with two or more apps as a filter, so a suite (Google, Zoho) can be
 * viewed whole while each app still sits under what it is used for.
 */
enum NodeCategoryVendor: string
{
    case Google = 'google';
    case Zoho = 'zoho';
    case Microsoft = 'microsoft';
    case Atlassian = 'atlassian';

    public function label(): string
    {
        return match ($this) {
            self::Google => 'Google',
            self::Zoho => 'Zoho',
            self::Microsoft => 'Microsoft',
            self::Atlassian => 'Atlassian',
        };
    }
}

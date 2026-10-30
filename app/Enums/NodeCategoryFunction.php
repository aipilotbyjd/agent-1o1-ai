<?php

namespace App\Enums;

/**
 * What an integration is used for: the sections the editor's Apps panel
 * browses by. An app lists one to three, the first being its primary, and
 * appears under each. Who makes it is a separate question, see
 * `NodeCategoryVendor`. Declaration order is the display order.
 */
enum NodeCategoryFunction: string
{
    case Email = 'email';
    case Messaging = 'messaging';
    case Marketing = 'marketing';
    case Analytics = 'analytics';
    case Productivity = 'productivity';
    case Storage = 'storage';
    case Forms = 'forms';
    case Media = 'media';
    case CrmSales = 'crm-sales';
    case Support = 'support';
    case FinancePayments = 'finance-payments';
    case ProjectManagement = 'project-management';
    case Developer = 'developer';
    case Commerce = 'commerce';
    case CloudHosting = 'cloud-hosting';
    case CmsWebsite = 'cms-website';

    /** Where an app with no function is shown, so a new integration never goes missing. Seeding one here is a bug. */
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Email => 'Email',
            self::Messaging => 'Messaging',
            self::Marketing => 'Marketing',
            self::Analytics => 'Analytics',
            self::Productivity => 'Productivity',
            self::Storage => 'Storage',
            self::Forms => 'Forms & Surveys',
            self::Media => 'Media',
            self::CrmSales => 'CRM & Sales',
            self::Support => 'Support',
            self::FinancePayments => 'Finance & Payments',
            self::ProjectManagement => 'Project management',
            self::Developer => 'Developer',
            self::Commerce => 'Commerce',
            self::CloudHosting => 'Cloud & Hosting',
            self::CmsWebsite => 'CMS & Website',
            self::Other => 'Other apps',
        };
    }

    public function sortOrder(): int
    {
        return array_search($this, self::cases(), true) + 1;
    }
}

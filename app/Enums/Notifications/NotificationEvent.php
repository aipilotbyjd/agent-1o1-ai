<?php

namespace App\Enums\Notifications;

/**
 * Single source of truth for the toggleable notification-event catalogue —
 * drives both `NotificationPreferenceController::upsert`'s validation and
 * the `/notifications/events` catalogue endpoint the frontend settings
 * screen reads. Ported from the old project's `NotificationEvent`, trimmed
 * to the events that already have a real trigger site in this codebase.
 */
enum NotificationEvent: string
{
    case MemberInvited = 'workspace.member_invited';
    case MemberJoined = 'workspace.member_joined';
    case MemberRemoved = 'workspace.member_removed';
    case MemberRoleChanged = 'workspace.member_role_changed';
    case RunApprovalRequested = 'run.approval_requested';
    case ConnectorCredentialExpired = 'connector.credential_expired';
    case PaymentFailed = 'billing.payment_failed';
    case PaymentRecovered = 'billing.payment_recovered';
    case SubscriptionCanceled = 'billing.subscription_canceled';

    public const DEFAULT_IN_APP = true;

    public const DEFAULT_EMAIL = false;

    public function label(): string
    {
        return match ($this) {
            self::MemberInvited => 'Member invited',
            self::MemberJoined => 'Member joined',
            self::MemberRemoved => 'Member removed',
            self::MemberRoleChanged => 'Member role changed',
            self::RunApprovalRequested => 'Run approval requested',
            self::ConnectorCredentialExpired => 'Connector credential expired',
            self::PaymentFailed => 'Payment failed',
            self::PaymentRecovered => 'Payment recovered',
            self::SubscriptionCanceled => 'Subscription canceled',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::MemberInvited => 'Someone is invited to join the workspace.',
            self::MemberJoined => 'An invited member accepts and joins the workspace.',
            self::MemberRemoved => 'A member is removed from the workspace.',
            self::MemberRoleChanged => "A member's role in the workspace changes.",
            self::RunApprovalRequested => 'A workflow run pauses awaiting human approval.',
            self::ConnectorCredentialExpired => 'A connector credential expires and could not be automatically refreshed.',
            self::PaymentFailed => 'A subscription invoice charge fails.',
            self::PaymentRecovered => 'A previously failed subscription charge succeeds and the plan is restored.',
            self::SubscriptionCanceled => 'A subscription ends, including when Stripe gives up after repeated failed charges.',
        };
    }

    /**
     * @return array{key: string, label: string, description: string, defaults: array{in_app: bool, email: bool}}
     */
    public function toCatalogEntry(): array
    {
        return [
            'key' => $this->value,
            'label' => $this->label(),
            'description' => $this->description(),
            'defaults' => [
                'in_app' => self::DEFAULT_IN_APP,
                'email' => self::DEFAULT_EMAIL,
            ],
        ];
    }

    /**
     * @return array<int, array{key: string, label: string, description: string, defaults: array{in_app: bool, email: bool}}>
     */
    public static function catalog(): array
    {
        return array_map(fn (self $event): array => $event->toCatalogEntry(), self::cases());
    }
}

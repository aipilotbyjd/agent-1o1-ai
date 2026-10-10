<?php

namespace App\Enums\Workspaces;

/**
 * What a workspace audit log entry records. The value is stored, so a case's
 * string must not change once it has been written.
 */
enum AuditAction: string
{
    case WorkspaceUpdated = 'workspace.updated';
    case WorkspaceDeleted = 'workspace.deleted';

    case MemberAdded = 'member.added';
    case MemberRoleChanged = 'member.role_changed';
    case MemberRemoved = 'member.removed';

    case InvitationCreated = 'invitation.created';
    case InvitationRevoked = 'invitation.revoked';

    case ApiKeyCreated = 'api_key.created';
    case ApiKeyRevoked = 'api_key.revoked';

    case SecretCreated = 'secret.created';
    case SecretUpdated = 'secret.updated';
    case SecretDeleted = 'secret.deleted';

    case ConnectorCredentialCreated = 'connector_credential.created';
    case ConnectorCredentialDeleted = 'connector_credential.deleted';
    case ConnectorCredentialUpdated = 'connector_credential.updated';
    case ConnectorCredentialDefaultChanged = 'connector_credential.default_changed';
    case ConnectorCredentialReassigned = 'connector_credential.reassigned';

    case AiProviderCredentialCreated = 'ai_provider_credential.created';
    case AiProviderCredentialUpdated = 'ai_provider_credential.updated';
    case AiProviderCredentialDeleted = 'ai_provider_credential.deleted';
    case AiProviderCredentialDefaultChanged = 'ai_provider_credential.default_changed';
    case AiKeyPolicyUpdated = 'ai_key_policy.updated';

    case NotificationChannelCreated = 'notification_channel.created';
    case NotificationChannelUpdated = 'notification_channel.updated';
    case NotificationChannelDeleted = 'notification_channel.deleted';

    case AgentPolicyUpdated = 'agent_policy.updated';

    case SubscriptionCheckoutStarted = 'billing.checkout_started';
    case SubscriptionCanceled = 'billing.subscription_canceled';
    case SubscriptionResumed = 'billing.subscription_resumed';
}

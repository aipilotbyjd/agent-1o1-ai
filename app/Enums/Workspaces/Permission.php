<?php

namespace App\Enums\Workspaces;

enum Permission: string
{
    case WorkspaceView = 'workspace.view';
    case WorkspaceUpdate = 'workspace.update';
    case WorkspaceDelete = 'workspace.delete';

    case MemberView = 'member.view';
    case MemberInvite = 'member.invite';
    case MemberUpdateRole = 'member.update-role';
    case MemberRemove = 'member.remove';

    case InvitationView = 'invitation.view';

    case WorkflowView = 'workflow.view';
    case WorkflowManage = 'workflow.manage';
    case WorkflowPublish = 'workflow.publish';
    case WorkflowVersion = 'workflow.version';
    case WorkflowTrigger = 'workflow.trigger';
    case WorkflowBuilderUse = 'workflow.builder.use';

    case NodeView = 'node.view';
    case NodeManage = 'node.manage';

    case AgentView = 'agent.view';
    case AgentManage = 'agent.manage';
    case AgentChat = 'agent.chat';
    case AgentSkillManage = 'agent.skill.manage';

    case ConnectorView = 'connector.view';
    case ConnectorManage = 'connector.manage';

    case ArtifactView = 'artifact.view';
    case ArtifactManage = 'artifact.manage';

    case RunView = 'run.view';
    case RunTrigger = 'run.trigger';

    case TriggerView = 'trigger.view';
    case TriggerManage = 'trigger.manage';

    case ApiKeyView = 'api-key.view';
    case ApiKeyManage = 'api-key.manage';

    case BillingView = 'billing.view';
    case BillingManage = 'billing.manage';

    case NotificationChannelView = 'notification-channel.view';
    case NotificationChannelManage = 'notification-channel.manage';

    /**
     * @return array<int, self>
     */
    public static function viewerGrants(): array
    {
        return [
            self::WorkspaceView,
            self::MemberView,
            self::InvitationView,
            self::WorkflowView,
            self::NodeView,
            self::AgentView,
            self::ConnectorView,
            self::ArtifactView,
            self::RunView,
            self::TriggerView,
            self::ApiKeyView,
            self::BillingView,
            self::NotificationChannelView,
        ];
    }

    /**
     * @return array<int, self>
     */
    public static function memberGrants(): array
    {
        return [
            self::AgentChat,
            self::WorkflowTrigger,
            self::RunTrigger,
        ];
    }

    /**
     * @return array<int, self>
     */
    public static function editorGrants(): array
    {
        return [
            self::WorkflowManage,
            self::WorkflowPublish,
            self::WorkflowVersion,
            self::WorkflowBuilderUse,
            self::NodeManage,
            self::AgentManage,
            self::AgentSkillManage,
            self::ArtifactManage,
            self::TriggerManage,
        ];
    }

    /**
     * @return array<int, self>
     */
    public static function adminGrants(): array
    {
        return [
            self::WorkspaceUpdate,
            self::MemberInvite,
            self::MemberUpdateRole,
            self::MemberRemove,
            self::ConnectorManage,
            self::ApiKeyManage,
            self::BillingManage,
            self::NotificationChannelManage,
        ];
    }

    /**
     * @return array<int, self>
     */
    public static function ownerGrants(): array
    {
        return [
            self::WorkspaceDelete,
        ];
    }
}

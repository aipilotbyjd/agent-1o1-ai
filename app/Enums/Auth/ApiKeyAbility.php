<?php

namespace App\Enums\Auth;

enum ApiKeyAbility: string
{
    case All = '*';
    case WorkflowsRead = 'workflows:read';
    case WorkflowsWrite = 'workflows:write';
    case AgentsInvoke = 'agents:invoke';
    case RunsRead = 'runs:read';
    case ConnectorsManage = 'connectors:manage';
}

<?php

namespace App\Enums;

enum RunStatus: string
{
    case Pending = 'pending';
    case Running = 'running';
    case AwaitingApproval = 'awaiting_approval';
    case AwaitingCallback = 'awaiting_callback';
    case Completed = 'completed';
    case Failed = 'failed';
    case Cancelled = 'cancelled';

    public function isTerminal(): bool
    {
        return in_array($this, [self::Completed, self::Failed, self::Cancelled], true);
    }
}

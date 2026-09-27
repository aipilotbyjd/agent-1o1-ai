<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * A builder session refused a change because of its current state — the
 * draft moved on since the caller last read it, a reply is still being
 * generated, or the session is archived. Mapped to a 409 in
 * `bootstrap/app.php`: the request is well-formed, the session's state is
 * what refuses it.
 */
class WorkflowBuilderConflictException extends RuntimeException
{
    public static function staleDraft(): self
    {
        return new self('The draft was changed since you last loaded it. Reload it and try again.');
    }

    public static function replyInProgress(): self
    {
        return new self('The assistant is still replying to your last message.');
    }

    public static function archived(): self
    {
        return new self('This session is archived and can no longer be edited.');
    }
}

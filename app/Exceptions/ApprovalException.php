<?php

namespace App\Exceptions;

use RuntimeException;

/** A business-rule refusal from the approval engine (shown to the user in plain language). */
class ApprovalException extends RuntimeException
{
    public static function notPending(): self
    {
        return new self(__('approvals.error.not_pending'));
    }

    public static function expired(): self
    {
        return new self(__('approvals.error.expired'));
    }

    public static function own(): self
    {
        return new self(__('approvals.error.own'));
    }

    public static function notEligible(): self
    {
        return new self(__('approvals.error.not_eligible'));
    }

    public static function reauthRequired(): self
    {
        return new self(__('approvals.error.reauth'));
    }

    public static function commentRequired(): self
    {
        return new self(__('approvals.error.comment_required'));
    }

    public static function handlerFailed(): self
    {
        return new self(__('approvals.error.handler_failed'));
    }
}

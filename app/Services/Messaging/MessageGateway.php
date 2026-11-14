<?php

namespace App\Services\Messaging;

/**
 * Gateway contract for dispatching outbound messages (SMS, messaging apps).
 *
 * @uc UC-27
 *
 * @fr FR-5.5
 */
interface MessageGateway
{
    /**
     * Dispatch an outbound message to a phone number.
     *
     * @return array{success: bool, message_id?: string|null, error?: string|null}
     */
    public function send(string $recipientPhone, string $message): array;
}

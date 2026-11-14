<?php

namespace App\Services\Messaging;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Log driver implementation of MessageGateway.
 *
 * Logs outbound messages to the application log. Supports simulation of failures
 * for testing and verification.
 *
 * @uc UC-27
 *
 * @fr FR-5.5
 */
class LogMessageGateway implements MessageGateway
{
    /** @var array<int, array{recipient: string, message: string, timestamp: string}> */
    protected static array $sentMessages = [];

    protected static bool $simulateFailure = false;

    protected static ?string $simulatedErrorMessage = null;

    public function send(string $recipientPhone, string $message): array
    {
        $cleanPhone = trim($recipientPhone);

        if (self::$simulateFailure || str_contains($cleanPhone, '999999999')) {
            $error = self::$simulatedErrorMessage ?? 'Carrier delivery failed: unreachable number';
            Log::warning("Refill reminder delivery failed to {$cleanPhone}: {$error}");

            return [
                'success' => false,
                'error' => $error,
            ];
        }

        $messageId = 'MSG-'.strtoupper(Str::random(10));
        self::$sentMessages[] = [
            'recipient' => $cleanPhone,
            'message' => $message,
            'timestamp' => now()->toIso8601String(),
        ];

        Log::info("Refill reminder SMS sent to {$cleanPhone} (ID: {$messageId}): {$message}");

        return [
            'success' => true,
            'message_id' => $messageId,
        ];
    }

    public static function fakeFailure(bool $fail = true, ?string $errorMessage = null): void
    {
        self::$simulateFailure = $fail;
        self::$simulatedErrorMessage = $errorMessage;
    }

    public static function reset(): void
    {
        self::$sentMessages = [];
        self::$simulateFailure = false;
        self::$simulatedErrorMessage = null;
    }

    /**
     * @return array<int, array{recipient: string, message: string, timestamp: string}>
     */
    public static function sentMessages(): array
    {
        return self::$sentMessages;
    }
}

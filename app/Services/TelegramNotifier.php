<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Sends best-effort alerts to the admin team's Telegram chat via the Bot API.
 * Every send is guarded (no-op when the bot token / chat id are unset) and
 * wrapped so a Telegram outage can never break the request that triggered it.
 */
class TelegramNotifier
{
    /**
     * Post a message to the configured admin chat. HTML parse mode is used so
     * callers can pass <b>…</b> and pre-escaped values. Returns true on a
     * confirmed send, false if skipped or failed.
     */
    public function sendMessage(string $text, ?int $topicId = null): bool
    {
        $token = config('services.telegram.bot_token');
        $chatId = config('services.telegram.admin_chat_id');
        $topicId ??= config('services.telegram.admin_topic_id');

        if (! $token || ! $chatId) {
            return false;
        }

        $payload = [
            'chat_id' => $chatId,
            'text' => $text,
            'parse_mode' => 'HTML',
            'disable_web_page_preview' => true,
        ];

        // Post into a specific forum topic when configured (General has no id).
        if ($topicId !== null && $topicId !== '') {
            $payload['message_thread_id'] = (int) $topicId;
        }

        try {
            $response = Http::timeout(5)
                ->post("https://api.telegram.org/bot{$token}/sendMessage", $payload);

            if ($response->successful()) {
                return true;
            }

            // Telegram returns a useful, non-sensitive error such as "chat not
            // found" or "message thread not found". Keep the request path
            // best-effort, but leave an actionable trace for operators.
            Log::warning('Telegram notification was rejected.', [
                'status' => $response->status(),
                'description' => $response->json('description'),
                'error_code' => $response->json('error_code'),
            ]);

            return false;
        } catch (\Throwable $e) {
            report($e);

            return false;
        }
    }

    /**
     * Escape a user-supplied value for safe interpolation into an HTML-parsed
     * Telegram message (only &, <, > are special in Telegram's HTML mode).
     */
    public static function escape(?string $value): string
    {
        return htmlspecialchars((string) $value, ENT_NOQUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

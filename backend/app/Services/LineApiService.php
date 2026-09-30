<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class LineApiService
{
    protected ?string $channelSecret;
    protected ?string $channelAccessToken;

    public function __construct()
    {
        $this->channelSecret = config('services.line.channel_secret');
        $this->channelAccessToken = config('services.line.channel_access_token');
    }

    /**
     * Validate HMAC-SHA256 signature from X-Line-Signature header.
     */
    public function verifySignature(string $payload, ?string $signature): bool
    {
        if (empty($this->channelSecret) || empty($signature)) {
            // In local/development if no secret is set, log warning
            if (app()->environment('local', 'testing') && empty($this->channelSecret)) {
                Log::warning('[LINE] Channel Secret not configured. Skipping strict signature verification in development.');
                return true;
            }
            return false;
        }

        $hash = hash_hmac('sha256', $payload, $this->channelSecret, true);
        $expectedSignature = base64_encode($hash);

        return hash_equals($expectedSignature, $signature);
    }

    /**
     * Download binary file content from LINE Messaging Content API.
     * https://api-data.line.me/v2/bot/message/{messageId}/content
     */
    public function downloadMessageContent(string $messageId): array
    {
        if (empty($this->channelAccessToken)) {
            // Local simulation test content if token not configured
            Log::info("[LINE] Channel access token not set. Generating mock binary file for message {$messageId}");
            return [
                'content' => "Mock file content downloaded for LINE message: {$messageId} at " . now(),
                'mime_type' => 'application/pdf',
                'size' => 64,
            ];
        }

        $url = "https://api-data.line.me/v2/bot/message/{$messageId}/content";

        $response = Http::withToken($this->channelAccessToken)
            ->timeout(60)
            ->get($url);

        if (!$response->successful()) {
            throw new \Exception("Failed to download LINE message content: Status {$response->status()} - {$response->body()}");
        }

        return [
            'content' => $response->body(),
            'mime_type' => $response->header('Content-Type') ?? 'application/octet-stream',
            'size' => strlen($response->body()),
        ];
    }

    /**
     * Fetch user profile in group if available.
     */
    public function getGroupMemberProfile(string $groupId, string $userId): ?array
    {
        if (empty($this->channelAccessToken)) {
            return null;
        }

        try {
            $response = Http::withToken($this->channelAccessToken)
                ->timeout(10)
                ->get("https://api.line.me/v2/bot/group/{$groupId}/member/{$userId}");

            if ($response->successful()) {
                return $response->json();
            }
        } catch (\Exception $e) {
            Log::warning("[LINE] Failed to get member profile: " . $e->getMessage());
        }

        return null;
    }

    /**
     * Fetch group summary (name, pictureUrl).
     */
    public function getGroupSummary(string $groupId): ?array
    {
        if (empty($this->channelAccessToken)) {
            return null;
        }

        try {
            $response = Http::withToken($this->channelAccessToken)
                ->timeout(10)
                ->get("https://api.line.me/v2/bot/group/{$groupId}/summary");

            if ($response->successful()) {
                return $response->json();
            }
        } catch (\Exception $e) {
            Log::warning("[LINE] Failed to get group summary: " . $e->getMessage());
        }

        return null;
    }
}

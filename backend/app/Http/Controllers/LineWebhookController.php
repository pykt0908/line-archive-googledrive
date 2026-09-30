<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessLineFile;
use App\Models\ArchiveFile;
use App\Models\LineGroup;
use App\Models\Teacher;
use App\Models\WebhookEvent;
use App\Services\LineApiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class LineWebhookController extends Controller
{
    /**
     * Handle incoming LINE Webhook events.
     * Silent Archive: Always return 200 OK without sending replies to LINE.
     */
    public function handle(Request $request, LineApiService $lineService): JsonResponse
    {
        $payload = $request->getContent();
        $signature = $request->header('X-Line-Signature');

        // 1. Signature Verification (Spec Section 9 & 26)
        if (!$lineService->verifySignature($payload, $signature)) {
            Log::warning('[LINE Webhook] Invalid signature rejected.');
            return response()->json(['message' => 'Invalid signature'], 400);
        }

        $data = $request->json()->all();
        $events = $data['events'] ?? [];

        Log::info('[LINE Webhook] Received ' . count($events) . ' event(s)');

        foreach ($events as $event) {
            $this->processEvent($event, $lineService);
        }

        // 9. Return HTTP 200 immediately to LINE (Spec Section 9 & 34)
        return response()->json(['status' => 'ok']);
    }

    protected function processEvent(array $event, LineApiService $lineService): void
    {
        $eventType = $event['type'] ?? 'unknown';
        $eventId = $event['webhookEventId'] ?? null;
        $source = $event['source'] ?? [];
        $groupId = $source['groupId'] ?? $source['roomId'] ?? null;
        $userId = $source['userId'] ?? null;

        // Log Webhook Event to database
        $eventLog = WebhookEvent::create([
            'event_id' => $eventId,
            'event_type' => $eventType,
            'line_group_id' => $groupId,
            'payload' => $event,
            'status' => 'received',
        ]);

        // Only process message events with media/files
        if ($eventType !== 'message') {
            $eventLog->update(['status' => 'ignored_non_message', 'processed_at' => now()]);
            return;
        }

        $message = $event['message'] ?? [];
        $messageType = $message['type'] ?? '';
        $messageId = $message['id'] ?? null;

        if (!$messageId) {
            $eventLog->update(['status' => 'missing_message_id', 'processed_at' => now()]);
            return;
        }

        // Supported types: file, image, video, audio (Spec Section 7 & 37)
        $supportedTypes = ['file', 'image', 'video', 'audio'];
        if (!in_array($messageType, $supportedTypes)) {
            $eventLog->update(['status' => 'unsupported_message_type', 'processed_at' => now()]);
            return;
        }

        // Duplicate Prevention (Spec Section 25)
        if (ArchiveFile::where('line_message_id', $messageId)->exists()) {
            Log::info("[LINE Webhook] Duplicate message {$messageId} ignored.");
            $eventLog->update(['status' => 'duplicate_ignored', 'processed_at' => now()]);
            return;
        }

        // Resolve or create Group record
        $groupRecord = null;
        if ($groupId) {
            $groupRecord = LineGroup::firstOrCreate(
                ['line_group_id' => $groupId],
                [
                    'group_name' => "LINE Group " . substr($groupId, 0, 8),
                    'is_active' => true,
                ]
            );

            // Fetch group name from LINE if token available
            if ($groupRecord->group_name === "LINE Group " . substr($groupId, 0, 8)) {
                $groupSummary = $lineService->getGroupSummary($groupId);
                if ($groupSummary && !empty($groupSummary['groupName'])) {
                    $groupRecord->update(['group_name' => $groupSummary['groupName']]);
                }
            }
        }

        // Resolve Sender
        $senderName = 'LINE User';
        $matchedTeacher = null;

        if ($userId) {
            // Check if teacher has matching email/identifier or default
            $matchedTeacher = Teacher::where('is_active', true)->where('email', $userId)->first();
            if ($groupId) {
                $memberProfile = $lineService->getGroupMemberProfile($groupId, $userId);
                if ($memberProfile && !empty($memberProfile['displayName'])) {
                    $senderName = $memberProfile['displayName'];
                }
            }
        }

        // Determine filename and mime type
        $originalFilename = match ($messageType) {
            'file' => $message['fileName'] ?? "file_{$messageId}.dat",
            'image' => "image_{$messageId}.jpg",
            'video' => "video_{$messageId}.mp4",
            'audio' => "audio_{$messageId}.m4a",
            default => "attachment_{$messageId}.dat",
        };

        $ext = strtolower(pathinfo($originalFilename, PATHINFO_EXTENSION));
        $mimeType = match ($messageType) {
            'image' => 'image/jpeg',
            'video' => 'video/mp4',
            'audio' => 'audio/mp4',
            default => match ($ext) {
                'pdf' => 'application/pdf',
                'jpg', 'jpeg' => 'image/jpeg',
                'png' => 'image/png',
                'webp' => 'image/webp',
                'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'doc' => 'application/msword',
                'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'xls' => 'application/vnd.ms-excel',
                'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
                'ppt' => 'application/vnd.ms-powerpoint',
                'txt' => 'text/plain',
                default => 'application/octet-stream',
            },
        };

        // Create Pending File Record
        $fileRecord = ArchiveFile::create([
            'line_message_id' => $messageId,
            'line_group_id' => $groupRecord ? $groupRecord->id : null,
            'teacher_id' => $matchedTeacher ? $matchedTeacher->id : null,
            'sender_line_id' => $userId,
            'sender_name' => $senderName,
            'original_filename' => $originalFilename,
            'stored_filename' => $originalFilename,
            'mime_type' => $mimeType,
            'file_size' => $message['fileSize'] ?? 0,
            'status' => 'pending',
        ]);

        // Dispatch Job for async download and Google Drive upload
        // If queue connection is sync (Shared Hosting / Zero-cron), dispatchAfterResponse sends HTTP 200 to LINE first, then uploads immediately!
        if (config('queue.default') === 'sync' || env('QUEUE_CONNECTION') === 'sync') {
            ProcessLineFile::dispatchAfterResponse($fileRecord->id);
        } else {
            ProcessLineFile::dispatch($fileRecord->id);
        }

        $eventLog->update([
            'status' => 'queued_for_processing',
            'processed_at' => now(),
        ]);

        Log::info("[LINE Webhook] Message {$messageId} successfully queued for Google Drive archive.");
    }
}

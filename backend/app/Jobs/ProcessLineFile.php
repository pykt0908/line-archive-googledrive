<?php

namespace App\Jobs;

use App\Models\ArchiveFile;
use App\Services\GoogleDriveService;
use App\Services\LineApiService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessLineFile implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;
    public array $backoff = [10, 30, 60, 120, 300];

    protected int $archiveFileId;

    /**
     * Create a new job instance.
     */
    public function __construct(int $archiveFileId)
    {
        $this->archiveFileId = $archiveFileId;
        $this->onQueue('line-file-processing');
    }

    /**
     * Execute the job.
     */
    public function handle(LineApiService $lineService, GoogleDriveService $driveService): void
    {
        $fileRecord = ArchiveFile::with('group')->find($this->archiveFileId);

        if (!$fileRecord) {
            Log::error("[Queue] ArchiveFile ID {$this->archiveFileId} not found in database.");
            return;
        }

        $fileRecord->update([
            'status' => 'processing',
            'error_message' => null,
        ]);

        try {
            Log::info("[Queue] Starting download for message {$fileRecord->line_message_id} ({$fileRecord->original_filename})");

            // 1. Download file content from LINE Messaging Content API
            $downloadResult = $lineService->downloadMessageContent($fileRecord->line_message_id);
            $content = $downloadResult['content'];
            $mimeType = $fileRecord->mime_type ?: $downloadResult['mime_type'];
            $fileSize = $downloadResult['size'];

            // 2. Validate file size (MAX_FILE_SIZE_MB)
            $maxMb = (int) env('MAX_FILE_SIZE_MB', 500);
            if ($fileSize > ($maxMb * 1024 * 1024)) {
                throw new \Exception("File exceeds maximum allowed size of {$maxMb}MB ({$fileSize} bytes).");
            }

            // 3. Upload to Google Drive (with Group/Year/Month structure)
            $groupName = $fileRecord->group ? $fileRecord->group->group_name : 'General';
            $customFolderId = $fileRecord->group ? $fileRecord->group->google_drive_folder_id : null;

            $uploadResult = $driveService->uploadFile(
                $content,
                $fileRecord->original_filename,
                $mimeType,
                $groupName,
                $customFolderId
            );

            // 4. Update file record to completed
            $fileRecord->update([
                'stored_filename' => $uploadResult['stored_filename'],
                'mime_type' => $mimeType,
                'file_size' => $fileSize,
                'google_drive_file_id' => $uploadResult['file_id'],
                'google_drive_url' => $uploadResult['web_view_link'],
                'status' => 'completed',
                'error_message' => null,
                'uploaded_at' => now(),
            ]);

            Log::info("[Queue] Successfully processed and archived file ID {$fileRecord->id} to Drive.");
        } catch (\Throwable $e) {
            Log::error("[Queue] Failed processing file ID {$fileRecord->id}: " . $e->getMessage());

            $fileRecord->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);

            // Re-throw so Laravel queue can retry with backoff according to spec
            throw $e;
        }
    }

    /**
     * Handle a job failure after all retries exhausted.
     */
    public function failed(\Throwable $exception): void
    {
        Log::critical("[Queue] Job ProcessLineFile permanently failed for file ID {$this->archiveFileId}: " . $exception->getMessage());

        $fileRecord = ArchiveFile::find($this->archiveFileId);
        if ($fileRecord) {
            $fileRecord->update([
                'status' => 'failed',
                'error_message' => 'Exhausted retry limit: ' . $exception->getMessage(),
            ]);
        }
    }
}

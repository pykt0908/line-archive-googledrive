<?php

namespace App\Http\Controllers;

use App\Models\ArchiveFile;
use App\Models\AuditLog;
use App\Models\LineGroup;
use App\Services\GoogleDriveService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FileController extends Controller
{
    /**
     * Get paginated file list with search and filters.
     */
    public function index(Request $request): JsonResponse
    {
        $query = ArchiveFile::with(['group', 'teacher'])
            ->where('status', '!=', 'deleted');

        // 1. Search Query (Spec Section 16)
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('original_filename', 'like', "%{$search}%")
                  ->orWhere('sender_name', 'like', "%{$search}%")
                  ->orWhereHas('group', function ($gq) use ($search) {
                      $gq->where('group_name', 'like', "%{$search}%");
                  });
            });
        }

        // 2. Filter Group
        if ($groupId = $request->input('group_id')) {
            $query->where('line_group_id', $groupId);
        }

        // 3. Filter File Type
        if ($type = $request->input('type')) {
            $this->applyTypeFilter($query, $type);
        }

        // 4. Filter Year & Month
        if ($year = $request->input('year')) {
            $query->whereYear('created_at', $year);
        }
        if ($month = $request->input('month')) {
            $query->whereMonth('created_at', $month);
        }

        // Sort by latest created
        $files = $query->latest('created_at')->paginate($request->input('per_page', 15));

        return response()->json($files);
    }

    /**
     * Get single file detail.
     */
    public function show(int $id): JsonResponse
    {
        $file = ArchiveFile::with(['group', 'teacher'])->findOrFail($id);

        return response()->json([
            'file' => $file,
        ]);
    }

    /**
     * Upload a new file manually (Admin only).
     */
    public function store(Request $request, GoogleDriveService $driveService): JsonResponse
    {
        $teacher = $request->user();
        if (!$teacher || !$teacher->is_admin) {
            return response()->json(['message' => 'เฉพาะผู้ดูแลระบบ (Admin) เท่านั้นที่สามารถเพิ่มหรืออัปโหลดไฟล์ได้'], 403);
        }

        $request->validate([
            'file' => 'required|file|max:102400', // up to 100MB
            'group_id' => 'nullable|exists:line_groups,id',
            'sender_name' => 'nullable|string|max:255',
        ]);

        $uploadedFile = $request->file('file');
        $originalFilename = $uploadedFile->getClientOriginalName();
        $mimeType = $uploadedFile->getMimeType() ?: 'application/octet-stream';
        $fileSize = $uploadedFile->getSize();
        $content = file_get_contents($uploadedFile->getRealPath());

        $groupId = $request->input('group_id');
        $group = $groupId ? LineGroup::find($groupId) : null;
        $groupName = $group ? $group->group_name : 'General';

        $uploadResult = $driveService->uploadFile(
            $content,
            $originalFilename,
            $mimeType,
            $groupName,
            $group?->google_drive_folder_id
        );

        $file = ArchiveFile::create([
            'line_message_id' => 'manual_' . uniqid(),
            'line_group_id' => $groupId,
            'teacher_id' => $teacher?->id,
            'sender_line_id' => null,
            'sender_name' => $request->input('sender_name') ?: ($teacher ? $teacher->name : 'ครูผู้ดูแล'),
            'original_filename' => $originalFilename,
            'stored_filename' => $uploadResult['stored_filename'],
            'mime_type' => $mimeType,
            'file_size' => $fileSize,
            'google_drive_file_id' => $uploadResult['file_id'],
            'google_drive_url' => $uploadResult['web_view_link'],
            'status' => 'ready',
            'uploaded_at' => now(),
        ]);

        AuditLog::create([
            'teacher_id' => $teacher?->id,
            'action' => 'upload',
            'file_id' => $file->id,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'details' => ['filename' => $originalFilename, 'file_size' => $fileSize],
        ]);

        return response()->json([
            'message' => 'อัปโหลดไฟล์เรียบร้อยแล้ว',
            'file' => $file->load(['group', 'teacher']),
        ], 201);
    }

    /**
     * Update file details (rename, reassign group, sender name).
     * Admin only.
     */
    public function update(int $id, Request $request): JsonResponse
    {
        $teacher = $request->user();
        if (!$teacher || !$teacher->is_admin) {
            return response()->json(['message' => 'เฉพาะผู้ดูแลระบบ (Admin) เท่านั้นที่สามารถแก้ไขข้อมูลไฟล์ได้'], 403);
        }

        $file = ArchiveFile::findOrFail($id);

        $request->validate([
            'original_filename' => 'nullable|string|max:255',
            'group_id' => 'nullable|exists:line_groups,id',
            'sender_name' => 'nullable|string|max:255',
        ]);

        $oldName = $file->original_filename;

        if ($request->filled('original_filename')) {
            $file->original_filename = $request->input('original_filename');
        }
        if ($request->has('group_id')) {
            $file->line_group_id = $request->input('group_id');
        }
        if ($request->filled('sender_name')) {
            $file->sender_name = $request->input('sender_name');
        }

        $file->save();

        AuditLog::create([
            'teacher_id' => $teacher->id,
            'action' => 'update',
            'file_id' => $file->id,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'details' => ['old_filename' => $oldName, 'new_filename' => $file->original_filename],
        ]);

        return response()->json([
            'message' => 'แก้ไขข้อมูลไฟล์เรียบร้อยแล้ว',
            'file' => $file->load(['group', 'teacher']),
        ]);
    }

    /**
     * Delete file.
     * Admin only.
     */
    public function destroy(int $id, Request $request, GoogleDriveService $driveService): JsonResponse
    {
        $teacher = $request->user();
        if (!$teacher || !$teacher->is_admin) {
            return response()->json(['message' => 'เฉพาะผู้ดูแลระบบ (Admin) เท่านั้นที่สามารถลบไฟล์ได้'], 403);
        }

        $file = ArchiveFile::findOrFail($id);

        AuditLog::create([
            'teacher_id' => $teacher?->id,
            'action' => 'delete',
            'file_id' => $file->id,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'details' => ['filename' => $file->original_filename],
        ]);

        if ($file->google_drive_file_id) {
            $driveService->deleteFile($file->google_drive_file_id, $file->stored_filename);
        }

        $file->delete();

        return response()->json([
            'message' => 'ลบไฟล์สำเร็จเรียบร้อยแล้ว',
        ]);
    }

    /**
     * Download file and record Audit Log (Spec Section 18).
     */
    public function download(int $id, Request $request, GoogleDriveService $driveService)
    {
        $teacher = $request->user();
        $file = ArchiveFile::findOrFail($id);

        // Record Audit Log
        AuditLog::create([
            'teacher_id' => $teacher?->id,
            'action' => 'download',
            'file_id' => $file->id,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'details' => [
                'filename' => $file->original_filename,
                'file_size' => $file->file_size,
            ],
        ]);

        // 1. Check local simulation disk
        $localSimPath = 'drive_simulation/' . ($file->group ? $file->group->group_name : 'General');
        $allFiles = Storage::disk('public')->allFiles('drive_simulation');
        foreach ($allFiles as $storageFile) {
            if (basename($storageFile) === $file->stored_filename) {
                return Storage::disk('public')->download($storageFile, $file->original_filename);
            }
        }

        // 2. Fetch from Google Drive API
        if ($file->google_drive_file_id) {
            $content = $driveService->getFileContent($file->google_drive_file_id, $file->stored_filename);
            if ($content !== null) {
                return response($content, 200, [
                    'Content-Type' => $file->mime_type ?: 'application/octet-stream',
                    'Content-Disposition' => 'attachment; filename="' . rawurlencode($file->original_filename) . '"',
                ]);
            }
        }

        // 3. Fallback to Drive URL redirect if direct stream not feasible
        if ($file->google_drive_url) {
            return redirect()->away($file->google_drive_url);
        }

        return response()->json(['message' => 'ไฟล์นี้ยังไม่พร้อมให้ดาวน์โหลด'], 404);
    }

    /**
     * Preview file inline (Spec Section 19).
     */
    public function preview(int $id, Request $request, GoogleDriveService $driveService)
    {
        $teacher = $request->user();
        $file = ArchiveFile::findOrFail($id);

        // Record Audit Log
        AuditLog::create([
            'teacher_id' => $teacher?->id,
            'action' => 'preview',
            'file_id' => $file->id,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'details' => ['filename' => $file->original_filename],
        ]);

        $resolvedMime = $this->resolveMimeType($file);

        // Check local storage simulation
        $allFiles = Storage::disk('public')->allFiles('drive_simulation');
        foreach ($allFiles as $storageFile) {
            if (basename($storageFile) === $file->stored_filename) {
                $fileContent = Storage::disk('public')->get($storageFile);
                return response($fileContent, 200, [
                    'Content-Type' => $resolvedMime,
                    'Content-Disposition' => 'inline; filename="' . rawurlencode($file->original_filename) . '"',
                    'Cache-Control' => 'public, max-age=86400',
                ]);
            }
        }

        // Try Google Drive API content
        if ($file->google_drive_file_id) {
            $content = $driveService->getFileContent($file->google_drive_file_id, $file->stored_filename);
            if ($content !== null) {
                return response($content, 200, [
                    'Content-Type' => $resolvedMime,
                    'Content-Disposition' => 'inline; filename="' . rawurlencode($file->original_filename) . '"',
                    'Cache-Control' => 'public, max-age=86400',
                ]);
            }
        }

        // Drive Thumbnail redirect for images
        if ($file->google_drive_file_id && (str_starts_with($resolvedMime, 'image/') || in_array(strtolower(pathinfo($file->original_filename, PATHINFO_EXTENSION)), ['jpg', 'jpeg', 'png', 'webp', 'gif']))) {
            return redirect()->away("https://drive.google.com/thumbnail?id={$file->google_drive_file_id}&sz=w1200");
        }

        // Drive Preview player for documents and other files (NEVER triggers download)
        if ($file->google_drive_file_id && !str_starts_with($file->google_drive_file_id, 'sim_')) {
            return redirect()->away("https://drive.google.com/file/d/{$file->google_drive_file_id}/preview");
        }

        if ($file->google_drive_url) {
            return redirect()->away($file->google_drive_url);
        }

        return response()->json([
            'message' => 'ไฟล์พรีวิวพร้อมใช้งานผ่าน Google Drive Viewer',
            'url' => $file->google_drive_url,
        ]);
    }

    /**
     * Resolve correct mime type from file extension or record
     */
    protected function resolveMimeType(ArchiveFile $file): string
    {
        $ext = strtolower(pathinfo($file->original_filename, PATHINFO_EXTENSION));
        return match ($ext) {
            'pdf' => 'application/pdf',
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'webp' => 'image/webp',
            'gif' => 'image/gif',
            'svg' => 'image/svg+xml',
            'mp4' => 'video/mp4',
            'mov' => 'video/quicktime',
            'webm' => 'video/webm',
            'mp3' => 'audio/mpeg',
            'm4a' => 'audio/mp4',
            'wav' => 'audio/wav',
            'txt' => 'text/plain; charset=utf-8',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'doc' => 'application/msword',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'xls' => 'application/vnd.ms-excel',
            'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            'ppt' => 'application/vnd.ms-powerpoint',
            'zip' => 'application/zip',
            default => (!empty($file->mime_type) && $file->mime_type !== 'application/octet-stream') ? $file->mime_type : 'application/octet-stream',
        };
    }

    /**
     * List all active LINE Groups for filter options.
     */
    public function groups(): JsonResponse
    {
        $groups = LineGroup::where('is_active', true)
            ->withCount('files')
            ->get();

        return response()->json($groups);
    }

    protected function applyTypeFilter($query, string $type): void
    {
        match ($type) {
            'pdf' => $query->where('mime_type', 'like', '%pdf%')
                           ->orWhere('original_filename', 'like', '%.pdf'),
            'image' => $query->where('mime_type', 'like', 'image/%')
                             ->orWhere(function ($q) {
                                 $q->where('original_filename', 'like', '%.jpg')
                                   ->orWhere('original_filename', 'like', '%.jpeg')
                                   ->orWhere('original_filename', 'like', '%.png')
                                   ->orWhere('original_filename', 'like', '%.webp');
                             }),
            'document' => $query->where(function ($q) {
                $q->where('original_filename', 'like', '%.doc')
                  ->orWhere('original_filename', 'like', '%.docx')
                  ->orWhere('original_filename', 'like', '%.txt');
            }),
            'spreadsheet' => $query->where(function ($q) {
                $q->where('original_filename', 'like', '%.xls')
                  ->orWhere('original_filename', 'like', '%.xlsx')
                  ->orWhere('original_filename', 'like', '%.csv');
            }),
            'presentation' => $query->where(function ($q) {
                $q->where('original_filename', 'like', '%.ppt')
                  ->orWhere('original_filename', 'like', '%.pptx');
            }),
            'archive' => $query->where(function ($q) {
                $q->where('original_filename', 'like', '%.zip')
                  ->orWhere('original_filename', 'like', '%.rar');
            }),
            default => null,
        };
    }
}

<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessLineFile;
use App\Models\ArchiveFile;
use App\Models\AuditLog;
use App\Models\LineGroup;
use App\Models\Teacher;
use App\Services\GoogleDriveService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminController extends Controller
{
    /**
     * Admin Dashboard Statistics (Spec Section 20 & 36).
     */
    public function dashboard(GoogleDriveService $driveService): JsonResponse
    {
        $today = now()->startOfDay();

        $totalFiles = ArchiveFile::where('status', '!=', 'deleted')->count();
        $filesToday = ArchiveFile::where('created_at', '>=', $today)->where('status', '!=', 'deleted')->count();
        $pendingFiles = ArchiveFile::whereIn('status', ['pending', 'processing'])->count();
        $failedFiles = ArchiveFile::where('status', 'failed')->count();

        $totalTeachers = Teacher::count();
        $activeTeachers = Teacher::where('is_active', true)->count();
        $totalGroups = LineGroup::count();
        $totalStorageBytes = ArchiveFile::where('status', 'completed')->sum('file_size');

        $driveHealth = $driveService->checkStatus();

        return response()->json([
            'stats' => [
                'total_files' => $totalFiles,
                'files_today' => $filesToday,
                'files_pending' => $pendingFiles,
                'files_failed' => $failedFiles,
                'total_teachers' => $totalTeachers,
                'active_teachers' => $activeTeachers,
                'total_groups' => $totalGroups,
                'storage_bytes' => $totalStorageBytes,
            ],
            'drive_health' => $driveHealth,
            'recent_failed' => ArchiveFile::where('status', 'failed')->latest()->take(5)->get(),
        ]);
    }

    /**
     * List Teachers (Spec Section 21).
     */
    public function teachers(Request $request): JsonResponse
    {
        $query = Teacher::withCount('files');

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('teacher_code', 'like', "%{$search}%")
                  ->orWhere('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        return response()->json($query->latest()->paginate($request->input('per_page', 20)));
    }

    /**
     * Store new Teacher.
     */
    public function storeTeacher(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'teacher_code' => 'required|string|max:50|unique:teachers,teacher_code',
            'name' => 'required|string|max:255',
            'email' => 'nullable|email|max:255',
            'is_admin' => 'boolean',
            'is_active' => 'boolean',
        ]);

        $teacher = Teacher::create($validated);

        return response()->json([
            'message' => 'เพิ่มข้อมูลครูสำเร็จ',
            'teacher' => $teacher,
        ], 201);
    }

    /**
     * Update Teacher.
     */
    public function updateTeacher(Request $request, int $id): JsonResponse
    {
        $teacher = Teacher::findOrFail($id);

        $validated = $request->validate([
            'teacher_code' => 'required|string|max:50|unique:teachers,teacher_code,' . $teacher->id,
            'name' => 'required|string|max:255',
            'email' => 'nullable|email|max:255',
            'is_admin' => 'boolean',
            'is_active' => 'boolean',
        ]);

        $teacher->update($validated);

        return response()->json([
            'message' => 'อัปเดตข้อมูลครูสำเร็จ',
            'teacher' => $teacher,
        ]);
    }

    /**
     * Toggle Teacher active status.
     */
    public function toggleTeacherStatus(int $id): JsonResponse
    {
        $teacher = Teacher::findOrFail($id);
        $teacher->update(['is_active' => !$teacher->is_active]);

        return response()->json([
            'message' => $teacher->is_active ? 'เปิดใช้งานครูสำเร็จ' : 'ปิดใช้งานครูสำเร็จ',
            'teacher' => $teacher,
        ]);
    }

    /**
     * Delete Teacher.
     */
    public function deleteTeacher(int $id): JsonResponse
    {
        $teacher = Teacher::findOrFail($id);
        $teacher->delete();

        return response()->json(['message' => 'ลบข้อมูลครูสำเร็จ']);
    }

    /**
     * List Groups (Spec Section 22).
     */
    public function groups(Request $request): JsonResponse
    {
        $groups = LineGroup::withCount('files')->latest()->get();
        return response()->json($groups);
    }

    /**
     * Update Group configuration.
     */
    public function updateGroup(Request $request, int $id): JsonResponse
    {
        $group = LineGroup::findOrFail($id);

        $validated = $request->validate([
            'group_name' => 'required|string|max:255',
            'google_drive_folder_id' => 'nullable|string|max:255',
            'is_active' => 'boolean',
        ]);

        $group->update($validated);

        return response()->json([
            'message' => 'บันทึกการตั้งค่ากลุ่มสำเร็จ',
            'group' => $group,
        ]);
    }

    /**
     * Admin files list (including failed and processing).
     */
    public function files(Request $request): JsonResponse
    {
        $query = ArchiveFile::with(['group', 'teacher']);

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('original_filename', 'like', "%{$search}%")
                  ->orWhere('sender_name', 'like', "%{$search}%");
            });
        }

        return response()->json($query->latest()->paginate($request->input('per_page', 20)));
    }

    /**
     * Retry failed file processing (Spec Section 32).
     */
    public function retryFile(int $id): JsonResponse
    {
        $file = ArchiveFile::findOrFail($id);
        $file->update(['status' => 'pending', 'error_message' => null]);

        if (config('queue.default') === 'sync' || env('QUEUE_CONNECTION') === 'sync') {
            ProcessLineFile::dispatchAfterResponse($file->id);
        } else {
            ProcessLineFile::dispatch($file->id);
        }

        return response()->json([
            'message' => 'ส่งไฟล์เข้าประมวลผลใหม่เรียบร้อยแล้ว',
            'file' => $file,
        ]);
    }

    /**
     * Admin Audit Logs (Spec Section 33).
     */
    public function logs(Request $request): JsonResponse
    {
        $query = AuditLog::with(['teacher', 'file']);

        if ($action = $request->input('action')) {
            $query->where('action', $action);
        }

        return response()->json($query->latest()->paginate($request->input('per_page', 25)));
    }
}

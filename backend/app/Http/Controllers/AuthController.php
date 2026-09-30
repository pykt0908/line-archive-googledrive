<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Teacher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

class AuthController extends Controller
{
    /**
     * Teacher login using teacher_code.
     * Implements Rate Limiting and checks is_active.
     */
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'teacher_code' => 'required|string|max:50',
        ]);

        $ip = $request->ip();
        $rateLimitKey = 'login-attempt:' . $ip;

        // Rate limit: 5 attempts per minute (Spec Section 6)
        if (RateLimiter::tooManyAttempts($rateLimitKey, 5)) {
            $seconds = RateLimiter::availableIn($rateLimitKey);
            return response()->json([
                'message' => "คุณลองเข้าสู่ระบบบ่อยเกินไป กรุณารออีก {$seconds} วินาที",
            ], 429);
        }

        $teacherCode = trim($request->input('teacher_code'));

        $teacher = Teacher::where('teacher_code', $teacherCode)->first();

        if (!$teacher) {
            RateLimiter::hit($rateLimitKey, 60);
            return response()->json([
                'message' => 'ไม่พบรหัสประจำตัวครู กรุณาตรวจสอบรหัสและลองใหม่อีกครั้ง',
            ], 401);
        }

        if (!$teacher->is_active) {
            RateLimiter::hit($rateLimitKey, 60);
            return response()->json([
                'message' => 'รหัสประจำตัวครูนี้ถูกปิดการใช้งาน กรุณาติดต่อผู้ดูแลระบบ',
            ], 403);
        }

        // Reset rate limiter on successful authentication
        RateLimiter::clear($rateLimitKey);

        // Create Sanctum Token
        $token = $teacher->createToken('teacher-access-token')->plainTextToken;

        // Record Audit Log (Spec Section 18 & 33)
        AuditLog::create([
            'teacher_id' => $teacher->id,
            'action' => 'login',
            'ip_address' => $ip,
            'user_agent' => $request->userAgent(),
            'details' => ['teacher_code' => $teacher->teacher_code],
        ]);

        return response()->json([
            'message' => 'เข้าสู่ระบบสำเร็จ',
            'token' => $token,
            'teacher' => [
                'id' => $teacher->id,
                'teacher_code' => $teacher->teacher_code,
                'name' => $teacher->name,
                'email' => $teacher->email,
                'is_admin' => (bool) $teacher->is_admin,
            ],
        ]);
    }

    /**
     * Logout and revoke tokens.
     */
    public function logout(Request $request): JsonResponse
    {
        $teacher = $request->user();

        if ($teacher) {
            $teacher->currentAccessToken()->delete();

            AuditLog::create([
                'teacher_id' => $teacher->id,
                'action' => 'logout',
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);
        }

        return response()->json([
            'message' => 'ออกจากระบบเรียบร้อยแล้ว',
        ]);
    }

    /**
     * Get current authenticated teacher.
     */
    public function me(Request $request): JsonResponse
    {
        $teacher = $request->user();

        return response()->json([
            'teacher' => [
                'id' => $teacher->id,
                'teacher_code' => $teacher->teacher_code,
                'name' => $teacher->name,
                'email' => $teacher->email,
                'is_admin' => (bool) $teacher->is_admin,
            ],
        ]);
    }
}

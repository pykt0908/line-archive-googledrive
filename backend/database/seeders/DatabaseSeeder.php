<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Teachers
        $teacher1 = \App\Models\Teacher::create([
            'teacher_code' => 'T001',
            'name' => 'ครูสมชาย ใจดี',
            'email' => 'somchai@school.ac.th',
            'is_admin' => true,
            'is_active' => true,
        ]);

        $teacher2 = \App\Models\Teacher::create([
            'teacher_code' => 'T002',
            'name' => 'ครูสมปอง สุขสันต์',
            'email' => 'sompong@school.ac.th',
            'is_admin' => false,
            'is_active' => true,
        ]);

        $teacher3 = \App\Models\Teacher::create([
            'teacher_code' => 'T003',
            'name' => 'ครูสุรีย์ ศรีสวัสดิ์',
            'email' => 'suree@school.ac.th',
            'is_admin' => false,
            'is_active' => false,
        ]);

        $admin = \App\Models\Teacher::create([
            'teacher_code' => 'ADMIN01',
            'name' => 'ผู้ดูแลระบบคอมพิวเตอร์',
            'email' => 'admin@school.ac.th',
            'is_admin' => true,
            'is_active' => true,
        ]);

        // LINE Groups
        $group1 = \App\Models\LineGroup::create([
            'line_group_id' => 'c10101010101010101010101010101010',
            'group_name' => 'กลุ่มครูคอมพิวเตอร์และสารสนเทศ',
            'google_drive_folder_id' => 'folder_comp_001',
            'is_active' => true,
        ]);

        $group2 = \App\Models\LineGroup::create([
            'line_group_id' => 'c20202020202020202020202020202020',
            'group_name' => 'กลุ่มงานวิชาการและแผนการสอน',
            'google_drive_folder_id' => 'folder_academic_002',
            'is_active' => true,
        ]);

        $group3 = \App\Models\LineGroup::create([
            'line_group_id' => 'c30303030303030303030303030303030',
            'group_name' => 'กลุ่มงานบริหารทั่วไป',
            'google_drive_folder_id' => 'folder_general_003',
            'is_active' => true,
        ]);

        // Sample Files
        $sampleFiles = [
            [
                'line_message_id' => 'msg_001_mock',
                'line_group_id' => $group1->id,
                'teacher_id' => $teacher1->id,
                'sender_name' => 'ครูสมชาย ใจดี',
                'original_filename' => 'ใบงานการเขียนโปรแกรม Python ม.2.pdf',
                'stored_filename' => '20260930_100000_ใบงานการเขียนโปรแกรม_Python_ม2.pdf',
                'mime_type' => 'application/pdf',
                'file_size' => 2450000,
                'google_drive_file_id' => 'mock_drive_file_id_001',
                'google_drive_url' => 'https://drive.google.com/file/d/mock_drive_file_id_001/view',
                'status' => 'completed',
                'uploaded_at' => now()->subHours(2),
            ],
            [
                'line_message_id' => 'msg_002_mock',
                'line_group_id' => $group2->id,
                'teacher_id' => $teacher2->id,
                'sender_name' => 'ครูสมปอง สุขสันต์',
                'original_filename' => 'ตารางสอนและรายวิชา_ภาคเรียนที่_2.xlsx',
                'stored_filename' => '20260930_091500_ตารางสอนและรายวิชา_ภาคเรียนที่_2.xlsx',
                'mime_type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'file_size' => 1120000,
                'google_drive_file_id' => 'mock_drive_file_id_002',
                'google_drive_url' => 'https://drive.google.com/file/d/mock_drive_file_id_002/view',
                'status' => 'completed',
                'uploaded_at' => now()->subHours(5),
            ],
            [
                'line_message_id' => 'msg_003_mock',
                'line_group_id' => $group1->id,
                'teacher_id' => $teacher1->id,
                'sender_name' => 'ครูสมชาย ใจดี',
                'original_filename' => 'ภาพกิจกรรมห้องปฏิบัติการ_AI.jpg',
                'stored_filename' => '20260929_142000_ภาพกิจกรรมห้องปฏิบัติการ_AI.jpg',
                'mime_type' => 'image/jpeg',
                'file_size' => 3800000,
                'google_drive_file_id' => 'mock_drive_file_id_003',
                'google_drive_url' => 'https://drive.google.com/file/d/mock_drive_file_id_003/view',
                'status' => 'completed',
                'uploaded_at' => now()->subDays(1),
            ],
            [
                'line_message_id' => 'msg_004_mock',
                'line_group_id' => $group3->id,
                'teacher_id' => null,
                'sender_name' => 'เจ้าหน้าที่พัสดุ',
                'original_filename' => 'แบบฟอร์มขออนุมัติจัดซื้อจัดจ้าง_ปีงบประมาณ.docx',
                'stored_filename' => '20260928_113000_แบบฟอร์มขออนุมัติจัดซื้อจัดจ้าง.docx',
                'mime_type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'file_size' => 845000,
                'google_drive_file_id' => 'mock_drive_file_id_004',
                'google_drive_url' => 'https://drive.google.com/file/d/mock_drive_file_id_004/view',
                'status' => 'completed',
                'uploaded_at' => now()->subDays(2),
            ],
        ];

        foreach ($sampleFiles as $fileData) {
            \App\Models\ArchiveFile::create($fileData);
        }
    }
}

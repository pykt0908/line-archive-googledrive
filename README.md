# 📁 LINE File Archive to Google Drive

[![Laravel](https://img.shields.io/badge/Laravel-12.x-FF2D20?style=for-the-badge&logo=laravel&logoColor=white)](https://laravel.com)
[![Vue 3](https://img.shields.io/badge/Vue-3.x-4FC08D?style=for-the-badge&logo=vue.js&logoColor=white)](https://vuejs.org/)
[![Vite](https://img.shields.io/badge/Vite-6.x-646CFF?style=for-the-badge&logo=vite&logoColor=white)](https://vitejs.dev/)
[![Vuetify 3](https://img.shields.io/badge/Vuetify-3.x-1867C0?style=for-the-badge&logo=vuetify&logoColor=white)](https://vuetifyjs.com/)
[![Google Drive API](https://img.shields.io/badge/Google%20Drive-API%20v3-4285F4?style=for-the-badge&logo=googledrive&logoColor=white)](https://developers.google.com/drive)
[![LINE Messaging API](https://img.shields.io/badge/LINE-Messaging%20API-00C300?style=for-the-badge&logo=line&logoColor=white)](https://developers.line.biz/)

ระบบจัดเก็บไฟล์และสื่อจากกลุ่ม LINE เข้าสู่ Google Drive แบบอัตโนมัติ (**Silent Archive Bot**) พร้อมเว็บพอร์ทัลที่ออกแบบมาสำหรับคุณครูและสถานศึกษา ค้นหา ดาวน์โหลด พรีวิวเอกสาร และจัดการระบบอย่างสมบูรณ์แบบ

---

## 🌟 ฟีเจอร์เด่น (Key Features)

- 🤖 **Silent Archive LINE Bot**: บอทจะดูดไฟล์เงียบๆ โดยไม่ตอบข้อความกวนใจในกลุ่ม (รองรับไฟล์ PDF, Word, Excel, PowerPoint, รูปภาพ, วิดีโอ, คลิปเสียง)
- 🗂️ **โครงสร้างโฟลเดอร์ Google Drive อัตโนมัติ**: จัดระเบียบไฟล์ตาม `{ชื่อกลุ่ม LINE}/{ปี พ.ศ. หรือ ค.ศ.}/{เดือน}` โดยอัตโนมัติ
- 🛡️ **ความปลอดภัยสูง**:
  - ตรวจสอบลายเซ็น `X-Line-Signature` ด้วย HMAC-SHA256
  - ป้องกันข้อความซ้ำซ้อนด้วย `line_message_id`
  - ตรวจสอบขนาดไฟล์สูงสุด ป้องกันความจุเกิน (`MAX_FILE_SIZE_MB`)
- 🖥️ **เว็บพอร์ทัลคุณครู (Teacher Web Portal)**:
  - เข้าสู่ระบบสะดวกด้วยรหัสประจำตัวครู (`teacher_code`)
  - ค้นหาไฟล์ตามชื่อ, กลุ่ม LINE, ประเภทไฟล์, วันที่
  - แสดงผลได้ทั้งแบบ **Card Grid** และ **Data Table**
  - **พรีวิวเอกสารในตัว**: ดูภาพ, ฟังเสียง, ดูคลิปวิดีโอ, ดู PDF และเอกสาร Office ผ่าน Google Drive Preview
  - ดาวน์โหลดไฟล์ได้อย่างรวดเร็วพร้อมระบบ Token Verification
  - อัปโหลดไฟล์ด้วยตนเอง (Manual Upload), แก้ไขชื่อไฟล์, ลบไฟล์
- ⚙️ **ระบบผู้ดูแลระบบ (Admin Console)**:
  - แดชบอร์ดสรุปสถิติ (ขนาดพื้นที่ที่ใช้, จำนวนไฟล์, จำนวนกลุ่ม, กิจกรรมล่าสุด)
  - จัดการรายชื่อครู (CRUD ครู, กำหนดสิทธิ์ Admin, เปิด/ปิดสถานะ Active)
  - จัดการกลุ่ม LINE (ผูกกลุ่ม LINE เข้ากับโฟลเดอร์ Google Drive เฉพาะ)
  - บันทึกประวัติการใช้งาน (Audit Logs) และประวัติรับ Webhook Events
- 🚀 **รองรับ 2 สภาพแวดล้อม**:
  - **Server / VPS / Local**: ใช้คิว Asynchronous Queue Worker (`php artisan queue:work`)
  - **Shared Hosting / DirectAdmin**: ทำงานแบบ Instant Sync (`QUEUE_CONNECTION=sync`) โดยไม่ต้องมี Cron Job

---

## 🏗️ สถาปัตยกรรมระบบ (Architecture)

```
[ LINE Group ] 
      │ (สมาชิกส่งไฟล์/รูป/วิดีโอ)
      ▼
[ LINE Messaging API Webhook ] ──(HTTPS POST)──▶ [ Laravel 12 Backend ]
                                                       │
                           ┌───────────────────────────┴───────────────────────────┐
                           ▼                                                       ▼
                [ ตรวจสอบ HMAC Signature ]                              [ บันทึก Webhook Log ]
                           │
                           ▼
              [ คิวงาน: ProcessLineFile ]
                           │
        ┌──────────────────┴──────────────────┐
        ▼                                     ▼
[ LINE Content API ]                  [ Google Drive API v3 ]
(ดาวน์โหลด Binary File)               (อัปโหลดแยกตาม โฟลเดอร์กลุ่ม/ปี/เดือน)
                                              │
                                              ▼
                                     [ MySQL / SQLite ]
                                    (บันทึก Metadata & URL)
                                              ▲
                                              │
                                    [ Vue 3 + Vite Frontend ]
                                    (คุณครูค้นหา/พรีวิว/ดาวน์โหลด)
```

---

## 📁 โครงสร้างโปรเจกต์ (Repository Structure)

```text
line-archive-googledrive/
├── backend/                  # Laravel 12 API Backend
│   ├── app/
│   │   ├── Http/Controllers/ # AuthController, FileController, LineWebhookController, AdminController
│   │   ├── Jobs/             # ProcessLineFile (Asynchronous Queue Job)
│   │   ├── Models/           # ArchiveFile, Teacher, LineGroup, WebhookEvent, AuditLog
│   │   └── Services/         # GoogleDriveService, LineApiService
│   ├── config/               # ค่าคอนฟิกูเรชัน (CORS, Sanctum, Database, Queue)
│   ├── database/
│   │   ├── migrations/       # ตารางฐานข้อมูลทั้งหมด
│   │   └── seeders/          # ข้อมูลเริ่มต้น (ครูทดสอบ, กลุ่มตัวอย่าง)
│   ├── routes/
│   │   └── api.php           # REST API Endpoints ทั้งหมด
│   ├── .env.example          # ตัวอย่างไฟล์ตั้งค่า Backend
│   └── composer.json
├── frontend/                 # Vue 3 Single Page Application (SPA)
│   ├── src/
│   │   ├── assets/           # ฟอนต์ LINE Seed Sans TH และไอคอน
│   │   ├── plugins/          # Vuetify 3 (Material Design Theme สี LINE Green)
│   │   ├── router/           # Vue Router และ Auth Guard
│   │   ├── services/         # Axios API Client พร้อม Interceptors
│   │   ├── stores/           # Pinia Auth Store
│   │   └── views/            # FilesView, LoginView และ admin/*
│   ├── package.json
│   ├── vite.config.js
│   └── .env.example
├── database.sql              # สคริปต์ SQL พร้อมข้อมูลเริ่มต้นสำหรับ MySQL / DirectAdmin / phpMyAdmin
├── .gitignore                # จัดการไฟล์ที่ไม่นำขึ้น Git
└── README.md                 # เอกสารแนะนำการใช้งานฉบับนี้
```

---

## ⚡ เริ่มต้นใช้งานอย่างรวดเร็ว (Quick Start)

### ข้อกำหนดของระบบ (Prerequisites)
- **PHP**: เวอร์ชัน 8.2 หรือสูงกว่า (แนะนำ 8.3+) พร้อมส่วนขยาย `curl`, `mbstring`, `openssl`, `pdo_sqlite` หรือ `pdo_mysql`
- **Composer**: เวอร์ชัน 2.x
- **Node.js**: เวอร์ชัน 18.x หรือ 20.x ขึ้นไป และ **npm**
- **ฐานข้อมูล**: SQLite (สำหรับทดสอบในเครื่อง) หรือ MySQL / MariaDB (สำหรับ Production)

---

### 1. ติดตั้ง Backend (Laravel)

```bash
# 1. เข้าสู่โฟลเดอร์ backend
cd backend

# 2. ติดตั้ง PHP Dependencies
composer install

# 3. คัดลอกไฟล์ .env
cp .env.example .env

# 4. สุ่มคีย์ความปลอดภัยของแอปพลิเคชัน
php artisan key:generate

# 5. รัน Migration พร้อม Seed ข้อมูลเริ่มต้น (ใช้ SQLite อัตโนมัติ หรือ MySQL ตามที่ระบุใน .env)
php artisan migrate --seed

# 6. สร้าง Symbolic Link สำหรับไฟล์สาธารณะ
php artisan storage:link

# 7. เริ่มต้นเครื่องเซิร์ฟเวอร์จำลอง
php artisan serve --port=8000
```

> **หมายเหตุ**: เมื่อรันคำสั่ง `php artisan migrate --seed` ระบบจะสร้างบัญชีผู้ใช้เริ่มต้นไว้ให้ทันที ดูข้อมูลได้ในหัวข้อ [บัญชีสำหรับทดสอบ](#-ข้อมูลบัญชีสำหรับทดสอบ-demo-accounts)

---

### 2. รัน Queue Worker (สำหรับประมวลผลไฟล์)

หากตั้งค่า `QUEUE_CONNECTION=database` ให้เปิดอีกหน้าต่าง Terminal เพื่อรัน Queue Worker:

```bash
cd backend
php artisan queue:work --queue=line-file-processing --tries=5
```

*(หากใช้บน Shared Hosting หรือตั้งค่า `QUEUE_CONNECTION=sync` ระบบจะอัปโหลดทันทีหลังรับ Webhook โดยไม่ต้องเปิด Worker)*

---

### 3. ติดตั้ง Frontend (Vue 3 + Vite)

```bash
# 1. เข้าสู่โฟลเดอร์ frontend
cd frontend

# 2. ติดตั้ง Node Dependencies
npm install

# 3. รัน Development Server
npm run dev
```

เปิดเบราว์เซอร์แล้วเข้าไปที่: **`http://localhost:5174/`** (หรือ URL ที่แสดงในหน้าต่าง Terminal)

---

## 🔑 ข้อมูลบัญชีสำหรับทดสอบ (Demo Accounts)

สามารถใช้รหัสประจำตัวครู (Teacher ID) ด้านล่างเข้าสู่ระบบได้ทันที:

| รหัสครู (Teacher ID) | ชื่อ - นามสกุล | บทบาท (Role) | สถานะ (Status) |
| :--- | :--- | :--- | :--- |
| **`ADMIN01`** | ผู้ดูแลระบบคอมพิวเตอร์ | **ผู้ดูแลระบบ (Admin)** | ใช้งานได้ (Active) |
| **`T001`** | ครูสมชาย ใจดี | **ครูผู้สอน / Admin** | ใช้งานได้ (Active) |
| **`T002`** | ครูสมปอง สุขสันต์ | **ครูผู้สอนทั่วไป** | ใช้งานได้ (Active) |
| **`T003`** | ครูสุรีย์ ศรีสวัสดิ์ | ครูผู้สอน | *ปิดการใช้งาน (Inactive)* |

---

## 🤖 การตั้งค่า LINE Official Account & Messaging API

เพื่อให้ Bot สามารถเข้ากลุ่ม LINE และจัดเก็บไฟล์ได้เงียบๆ ให้ทำตามขั้นตอนนี้:

### ขั้นตอนที่ 1: สร้าง LINE Official Account (LINE OA)
1. เข้าไปที่ [manager.line.biz](https://manager.line.biz/) และล็อกอินด้วยบัญชี LINE
2. กดปุ่ม **สร้างบัญชี (Create Account)**:
   - ตั้งชื่อบัญชี เช่น `คลังไฟล์โรงเรียน` หรือ `School Archive Bot`
   - เลือกหมวดหมู่ธุรกิจ (เช่น การศึกษา / โรงเรียน)
   - กดยืนยันการสร้างบัญชี

### ขั้นตอนที่ 2: เปิดใช้งาน Messaging API
1. ในหน้าแผงควบคุม LINE OA Manager ไปที่มุมขวาบนคลิก **ตั้งค่า (Settings)** (ไอคอนรูปฟันเฟือง)
2. เลือกเมนูด้านซ้าย **Messaging API**
3. กดปุ่มสีเขียว **เปิดใช้งาน Messaging API (Enable Messaging API)**
4. เลือกหรือสร้าง **Provider** ใหม่ (เช่น `โรงเรียนพัฒนาวิชาการ`)
5. กดยอมรับเงื่อนไข แล้วเข้าสู่ [LINE Developers Console](https://developers.line.biz/) จะพบบัญชี Channel ปรากฏอยู่

### ขั้นตอนที่ 3: รับค่า Keys & Token
นำค่า 3 ค่านี้ไปใส่ในไฟล์ `backend/.env`:
- **`LINE_CHANNEL_ID`**: ไปที่แท็บ *Basic settings* คัดลอกเลข Channel ID
- **`LINE_CHANNEL_SECRET`**: ในแท็บ *Basic settings* คัดลอก Channel Secret
- **`LINE_CHANNEL_ACCESS_TOKEN`**: ไปที่แท็บ *Messaging API* เลื่อนลงไปที่ *Channel access token (long-lived)* กดปุ่ม **Issue** แล้วคัดลอกค่าทั้งหมด

### ขั้นตอนที่ 4: ตั้งค่า Webhook URL
1. ในแท็บ **Messaging API** เลื่อนไปที่หัวข้อ **Webhook settings**
2. กด **Edit** แล้วระบุ URL ของคุณ:
   ```text
   https://your-domain.com/api/line/webhook
   ```
   *(สำหรับการทดสอบบนเครื่อง Local สามารถใช้ ngrok หรือ Cloudflare Tunnel ชี้มาที่ Port 8000 เช่น `https://xxxx.ngrok-free.app/api/line/webhook`)*
3. เปิดสวิตช์ **Use webhook = Enabled**
4. กดปุ่ม **Verify** เพื่อทดสอบการเชื่อมต่อ (ระบบจะคืนค่า 200 OK)

### ขั้นตอนที่ 5: ตั้งค่า Bot พฤติกรรมเงียบ (Silent Archive) และการเข้ากลุ่ม
1. **อนุญาตให้เข้ากลุ่ม**:
   - ใน LINE OA Manager ไปที่ **ตั้งค่า (Settings)** -> **การตั้งค่าบัญชี (Account settings)**
   - เลื่อนไปที่ **การแชท (Chat settings)** -> หัวข้อ **อนุญาตให้เข้าร่วมกลุ่มหรือการแชทแบบหลายคน** ให้เลือก **เปิดใช้งาน (Enabled)**
2. **ปิดข้อความกวนใจในกลุ่ม (Silent Archive)**:
   - ใน LINE OA Manager ไปที่ **ตั้งค่า (Settings)** -> **การตั้งค่าการตอบกลับ (Response settings)**
   - **โหมดตอบกลับ (Response mode)**: เลือก **บอท (Bot)**
   - **ข้อความทักทายเพื่อนใหม่ (Greeting message)**: **ปิด (Disabled)**
   - **ข้อความตอบกลับอัตโนมัติ (Auto-response)**: **ปิด (Disabled)**
3. สแกน QR Code ของ Bot เพื่อเป็นเพื่อน และกด **เชิญ Bot เข้ากลุ่ม LINE** ที่ต้องการ เมื่อสมาชิกส่งไฟล์หรือรูปภาพในกลุ่ม บอทจะดูดไฟล์เข้า Google Drive อัตโนมัติทันทีโดยไม่พิมพ์ตอบกวนใจในกลุ่ม

---

## ☁️ การตั้งค่า Google Drive API (Service Account)

1. เข้าไปที่ [Google Cloud Console](https://console.cloud.google.com/)
2. สร้างโปรเจกต์ใหม่ (เช่น `Line-Archive-Drive`)
3. ไปที่ **APIs & Services** -> **Library** ค้นหา **Google Drive API** แล้วกด **Enable**
4. ไปที่ **APIs & Services** -> **Credentials** -> กด **Create Credentials** -> เลือก **Service Account**
5. ตั้งชื่อ Service Account แล้วกดบันทึก
6. คลิกเข้าไปที่ Service Account ที่เพิ่งสร้าง -> ไปที่แท็บ **Keys** -> กด **Add Key** -> **Create new key** -> เลือกรูปแบบ **JSON**
7. ระบบจะดาวน์โหลดไฟล์ JSON มายังเครื่อง ให้เปลี่ยนชื่อเป็น `service_account.json` แล้วนำไปวางไว้ที่:
   ```text
   backend/storage/credentials/service_account.json
   ```
8. **แชร์สิทธิ์โฟลเดอร์ Google Drive**:
   - เปิด Google Drive ของคุณ สร้างโฟลเดอร์หลัก เช่น `LINE_ARCHIVE_STORAGE`
   - คลิกขวาที่โฟลเดอร์ -> เลือก **แชร์ (Share)**
   - นำอีเมลของ Service Account (เช่น `xxxx@xxxx.iam.gserviceaccount.com`) มาใส่ และกำหนดสิทธิ์เป็น **Editor (ผู้แก้ไข)**
   - คัดลอก Folder ID จาก URL (เช่น `https://drive.google.com/drive/folders/1A2B3C4D...` รหัสคือ `1A2B3C4D...`) นำไปใส่ใน `GOOGLE_DRIVE_ROOT_FOLDER_ID` ในไฟล์ `.env`

---

## ⚙️ ตัวอย่างการตั้งค่า `.env` (Backend Configuration)

```env
APP_NAME="LINE File Archive"
APP_ENV=production
APP_KEY=base64:...
APP_DEBUG=false
APP_URL=https://your-domain.com

# ฐานข้อมูล (MySQL หรือ SQLite)
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=your_database_name
DB_USERNAME=your_database_user
DB_PASSWORD=your_database_password

# คิวงาน (sync สำหรับ shared hosting / database สำหรับ VPS)
QUEUE_CONNECTION=sync

# การตั้งค่า LINE Messaging API
LINE_CHANNEL_ID=2001234567
LINE_CHANNEL_SECRET=your_channel_secret_here
LINE_CHANNEL_ACCESS_TOKEN=your_channel_access_token_here

# การตั้งค่า Google Drive
GOOGLE_DRIVE_ENABLED=true
GOOGLE_DRIVE_SERVICE_ACCOUNT_JSON=storage/credentials/service_account.json
GOOGLE_DRIVE_SHARED_DRIVE_ID=
GOOGLE_DRIVE_ROOT_FOLDER_ID=your_google_drive_folder_id_here

# ขนาดไฟล์สูงสุดที่อนุญาตให้อัปโหลด (MB)
MAX_FILE_SIZE_MB=500
```

---

## 🗄️ การติดตั้งบน DirectAdmin / Shared Hosting (Production)

หากต้องการนำระบบไปติดตั้งบนโฮสติ้ง cPanel หรือ DirectAdmin:

1. **สร้างฐานข้อมูล MySQL**:
   - สร้าง Database และ User ในหน้า DirectAdmin / cPanel
   - เข้า **phpMyAdmin** เลือกฐานข้อมูล แล้วกดเมนู **Import (นำเข้า)** เลือกไฟล์ `database.sql` จากโปรเจกต์นี้
2. **สร้างโฟลเดอร์บน Hosting**:
   - อัปโหลดไฟล์ Backend ขึ้นไปไว้โฟลเดอร์ระดับนอก `public_html` (เช่น `/home/username/line_archive_backend`)
   - รันคำสั่ง `npm run build` ในโฟลเดอร์ `frontend/` จะได้ไฟล์ใน `frontend/dist/` ให้นำเนื้อหาข้างในทั้งหมดไปวางไว้ใน `public_html/` ของโดเมน
   - ใน `backend/.env` ให้ตั้ง `QUEUE_CONNECTION=sync` เพื่อให้ไฟล์อัปโหลดลง Drive ทันทีที่รับ Webhook

---

## 🛠️ รายการคำสั่งที่มีประโยชน์ (Useful Commands)

```bash
# รัน Migration ฐานข้อมูลใหม่ทั้งหมดพร้อมข้อมูลเริ่มต้น
php artisan migrate:fresh --seed

# ล้างแคชการตั้งค่าทั้งหมด
php artisan optimize:clear

# สร้างคีย์ใหม่
php artisan key:generate

# บิลด์ Frontend สำหรับนำไปใช้งานจริง (Production Build)
cd frontend
npm run build
```

---

## 📄 ใบอนุญาต (License)

โปรเจกต์นี้เผยแพร่ภายใต้ใบอนุญาต **MIT License** สามารถนำไปประยุกต์ใช้งานและพัฒนาต่อยอดได้อย่างอิสระ

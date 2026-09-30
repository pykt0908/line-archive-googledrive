# คู่มือการตั้งค่า LINE Messaging API & การใช้งานจริง (LINE Setup Guide)

เอกสารนี้รวบรวมขั้นตอนการตั้งค่า **LINE Official Account (LINE OA)** และ **LINE Developers Console** อย่างละเอียดทีละขั้นตอน เพื่อให้ Bot สามารถเข้ากลุ่ม LINE และดูดไฟล์เข้าสู่ระบบอัตโนมัติ (Silent Archive)

---

## 📑 สารบัญ
1. [การสร้าง LINE Official Account & LINE Developers](#1-การสร้าง-line-developers--messaging-api-channel)
2. [การขอค่า Keys และ Token สำหรับเชื่อมต่อ (.env)](#2-การขอค่า-keys-และ-token-สำหรับเชื่อมต่อ)
3. [การตั้งค่า Webhook URL](#3-การตั้งค่า-webhook-url)
4. [การตั้งค่าพฤติกรรมของ Bot (สำคัญมาก: Silent Archive & การเข้ากลุ่ม)](#4-การตั้งค่าพฤติกรรมของ-bot-สำคัญมาก)
5. [การเชิญ Bot เข้ากลุ่ม LINE และเริ่มใช้งาน](#5-การเชิญ-bot-เข้ากลุ่ม-line-และเริ่มใช้งาน)
6. [การตรวจสอบและแก้ปัญหาที่พบบ่อย (Troubleshooting)](#6-การตรวจสอบและแก้ปัญหาที่พบบ่อย-troubleshooting)

---

## 1. การสร้าง LINE Official Account & เปิดใช้งาน Messaging API

> **หมายเหตุสำคัญจาก LINE (การเปลี่ยนแปลงใหม่ล่าสุด):**  
> ปัจจุบัน LINE ไม่อนุญาตให้กดสร้าง Messaging API Channel โดยตรงจากหน้า LINE Developers Console แล้ว  
> **วิธีที่ถูกต้องคือ**: ต้องสร้าง **LINE Official Account (LINE OA)** ก่อน แล้วจึงกด "เปิดใช้งาน Messaging API" เพื่อเชื่อมโยงมายัง LINE Developers

### ขั้นตอนการสร้าง:

1. **กดปุ่ม "Create a LINE Official Account"** (หรือเข้าไปที่ [manager.line.biz](https://manager.line.biz/))
2. **สร้างบัญชี LINE Official Account**:
   - เข้าสู่ระบบด้วย LINE ส่วนตัว
   - กรอกข้อมูลพื้นฐาน:
     - **ชื่อบัญชี (Account name)**: เช่น `คลังไฟล์โรงเรียน` หรือ `School File Archive`
     - **อีเมล (Email address)**: อีเมลของคุณ
     - **ประเภทธุรกิจ (Category)**: เลือก `การศึกษา` / `โรงเรียน` (หรืออื่นๆ ที่ต้องการ)
   - กด **ต่อไป (Next)** แล้วกดยืนยันการสร้างบัญชี
3. **เปิดใช้งาน Messaging API**:
   - เมื่อเข้าหน้าแดชบอร์ดของ LINE Official Account Manager แล้ว ให้มองหาเมนูมุมขวาบน กดที่ **"ตั้งค่า" (Settings)** (ไอคอนรูปฟันเฟือง)
   - ในแถบเมนูด้านซ้าย ให้คลิกที่หัวข้อ **"Messaging API"**
   - กดปุ่มสีเขียว **"เปิดใช้งาน Messaging API" (Enable Messaging API)**
   - ระบบจะให้เลือก **Provider**:
     - หากเคยสร้าง Provider ไว้แล้ว ให้เลือก Provider เดิม
     - หากยังไม่มี ให้กดสร้างใหม่ เช่น ตั้งชื่อว่า `โรงเรียนของเรา` หรือ `School Dev`
   - ใส่ Privacy Policy / Terms of Use (เว้นว่างไว้ได้) แล้วกด **ตกลง (OK)**
4. **เปิดกลับไปที่ LINE Developers Console**:
   - เข้าเว็บไซต์ [LINE Developers Console](https://developers.line.biz/)
   - คุณจะเห็น Channel ของ LINE OA ที่เพิ่งสร้าง ปรากฏอยู่ใต้ Provider เรียบร้อยแล้ว พร้อมนำไปตั้งค่า Keys/Tokens ต่อในขั้นตอนถัดไป!

---

## 2. การขอค่า Keys และ Token สำหรับเชื่อมต่อ

หลังจากสร้าง Channel สำเร็จ จะต้องนำค่า 3 ตัวนี้ไปใส่ในไฟล์ `.env` ของระบบ:

```text
LINE_CHANNEL_ID=...
LINE_CHANNEL_SECRET=...
LINE_CHANNEL_ACCESS_TOKEN=...
```

### 2.1 ขอ Channel ID และ Channel Secret
1. ใน LINE Developers Console ให้คลิกเข้าไปที่ Channel ของคุณ
2. ไปที่แท็บ **Basic settings**
3. เลื่อนลงมาจะพบ:
   - **Channel ID**: (ตัวเลข เช่น `2006789012`) -> นำไปใส่ใน `LINE_CHANNEL_ID`
   - **Channel secret**: (รหัสตัวอักษรและตัวเลข) -> นำไปใส่ใน `LINE_CHANNEL_SECRET`

### 2.2 ขอ Channel Access Token (Long-lived)
1. สลับไปที่แท็บ **Messaging API**
2. เลื่อนลงมาที่หัวข้อ **Channel access token (long-lived)**
3. กดปุ่ม **Issue**
4. จะได้ข้อความ Token ขนาดยาวมาก -> กด Copy แล้วนำไปใส่ใน `LINE_CHANNEL_ACCESS_TOKEN`

---

## 3. การตั้งค่า Webhook URL

1. ในแท็บ **Messaging API** เลื่อนไปที่หัวข้อ **Webhook settings**
2. กดปุ่ม **Edit** ที่หัวข้อ **Webhook URL**
3. ระบุ URL ของระบบคุณ (ต้องขึ้นต้นด้วย `https://` เท่านั้น):
   ```text
   https://your-domain.com/api/line/webhook
   ```
   *(ตัวอย่างเช่น `https://archive.school.ac.th/api/line/webhook`)*
4. กด **Update**
5. เลื่อนสวิตช์หัวข้อ **Use webhook** ให้เป็น **Enabled (สีเขียว)**
6. กดปุ่ม **Verify**
   - หากระบบบนเซิร์ฟเวอร์เปิดใช้งานอยู่ จะขึ้นข้อความตัวเขียวว่า `Success`

---

## 4. การตั้งค่าพฤติกรรมของ Bot (สำคัญมาก)

เพื่อให้ Bot ทำงานแบบ **Silent Archive** (ดูดไฟล์เงียบๆ ไม่ตอบข้อความรบกวนในกลุ่ม) และสามารถดึงเข้ากลุ่มได้:

### 4.1 อนุญาตให้ Bot เข้าร่วมกลุ่ม LINE
1. ในหน้า [LINE Developers](https://developers.line.biz/) แท็บ **Messaging API**
2. เลื่อนลงไปที่หัวข้อ **LINE Official Account features**
3. ดูที่หัวข้อ **Allow bot to join group chats**
4. หากขึ้นว่า *Disabled* ให้กดคลิก **Edit** (ระบบจะพาไปที่ LINE Official Account Manager)
5. ในหน้าตั้งค่าบัญชี -> หัวข้อ **การแชท (Chat Settings)**:
   - เลือก **อนุญาตให้เข้าร่วมกลุ่มหรือการแชทแบบหลายคน (Allow bot to join group chats)** -> ติ๊กเลือก **เปิดใช้งาน (Enabled)**

### 4.2 ปิดข้อความตอบกลับอัตโนมัติ (Silent Archive)
ในหน้า [LINE Official Account Manager](https://manager.line.biz/):
1. ไปที่เมนู **ตั้งค่า (Settings)** มุมบนขวา -> เลือก **การตั้งค่าการตอบกลับ (Response Settings)**
2. ตั้งค่าดังนี้:
   - **โหมดการตอบกลับ (Response mode)**: เลือก **บอท (Bot)**
   - **ข้อความทักทายเพื่อนใหม่ (Greeting message)**: แนะนำให้ **ปิด (Disabled)** หรือพิมพ์แจ้งสั้นๆ ว่าเป็นบอทจัดเก็บไฟล์
   - **ข้อความตอบกลับอัตโนมัติ (Auto-response messages)**: **ปิด (Disabled)**
   - **AI Webhook**: หากมี ให้เลือกส่งผ่าน Webhook ปกติ

> **ผลลัพธ์**: เมื่อสมาชิกในกลุ่มส่งไฟล์หรือรูปภาพ Bot จะไม่ตอบข้อความ "สวัสดี" หรือข้อความกวนใจใดๆ ในกลุ่มทั้งสิ้น ระบบจะรับข้อมูลเบื้องหลังและเก็บลง Google Drive ทันที

---

## 5. การเชิญ Bot เข้ากลุ่ม LINE และเริ่มใช้งาน

1. ไปที่แท็บ **Messaging API** ใน LINE Developers Console จะเห็น **QR Code** หรือ **LINE ID** ของ Bot
2. ใช้มือถือของแอดมินสแกน QR Code เพื่อ **เพิ่มเพื่อน (Add Friend)** กับ Bot นั้น
3. เข้าไปยังกลุ่ม LINE ของโรงเรียนหรือกลุ่มคุณครูที่ต้องการจัดเก็บไฟล์
4. กดเมนูสมาชิกกลุ่ม -> กด **เชิญ (Invite)** -> เลือก Bot ของเราเข้ากลุ่ม
5. **ทดสอบส่งไฟล์**:
   - ลองส่งไฟล์ PDF, ไฟล์ Word/Excel หรือรูปภาพเข้ากลุ่ม
   - เข้าหน้าเว็บพอร์ทัล `https://your-domain.com/files`
   - ล็อกอินด้วยรหัสครู จะพบไฟล์ที่ส่งในกลุ่มปรากฏบนหน้าเว็บทันที พร้อมสถานะจัดเก็บลง Google Drive เรียบร้อย

---

## 6. การตรวจสอบและแก้ปัญหาที่พบบ่อย (Troubleshooting)

### ❓ กด Verify Webhook แล้วขึ้นว่า Error (404 Not Found / 400 Bad Request)
- **สาเหตุ 1**: โดเมนยังไม่มีใบรับรองความปลอดภัย HTTPS (LINE ปฏิเสธ HTTP ธรรมดา)
- **สาเหตุ 2**: พิมพ์ URL ผิด ต้องลงท้ายด้วย `/api/line/webhook`
- **สาเหตุ 3**: บน Shared Hosting ไฟล์ `.htaccess` ไม่ได้ส่งต่อ URL ไปยัง `index.php`

### ❓ Bot อยู่ในกลุ่ม แต่ส่งไฟล์แล้วไม่มีอะไรเข้าเว็บ
- **สาเหตุ 1**: ตรวจสอบว่าใน LINE Developers ได้เปิดสวิตช์ **Use webhook = Enabled** แล้วหรือยัง
- **สาเหตุ 2**: ตรวจสอบว่า Service Account ของ Google Drive ได้ถูก **แชร์สิทธิ์ (Share as Editor)** ไปยังโฟลเดอร์หลักแล้วหรือไม่
- **สาเหตุ 3 (สำหรับ Shared Hosting)**: ใน `.env` ให้ตั้งเป็น `QUEUE_CONNECTION=sync` เพื่อให้ระบบอัปโหลดไฟล์ทันทีผ่าน `dispatchAfterResponse` โดยไม่ต้องใช้ Cron Job
- **วิธีตรวจสอบ Log**: เข้าหลังบ้านผ่านหน้าเว็บ `/admin/logs` เพื่อดูสถานะการทำงาน หรือดูในตาราง `webhook_events` ว่ามีข้อความส่งมาถึงเซิร์ฟเวอร์หรือไม่

---

## 7. การตั้งค่าฐานข้อมูลบน DirectAdmin (Database Setup)

ผมได้สร้างไฟล์ **`database.sql`** ไว้ในโฟลเดอร์โปรเจกต์ให้เรียบร้อยแล้ว โดยมีตารางครบถ้วนพร้อมข้อมูลคุณครูเริ่มต้นสำหรับทดสอบล็อกอิน

### ขั้นตอนการสร้างและนำเข้าฐานข้อมูลผ่าน DirectAdmin:
1. เข้า **DirectAdmin** -> ไปที่เมนู **MySQL Management**
2. กด **Create new Database**
   - **Database Name**: ตั้งชื่อ เช่น `archive` (ระบบจะนำหน้าด้วย username ของคุณ เช่น `user_archive`)
   - **Database User**: เช่น `user_db`
   - **Database Password**: ตั้งรหัสผ่านที่ปลอดภัย (จดเก็บไว้)
3. เข้า **phpMyAdmin**:
   - คลิกที่ชื่อฐานข้อมูลที่คุณเพิ่งสร้างทางแถบซ้ายมือ
   - คลิกที่แท็บ **Import (นำเข้า)** ด้านบน
   - กดเลือกไฟล์ **[database.sql](file:///c:/Users/pykt/Documents/line-achrive-drive/database.sql)** จากเครื่องของคุณ
   - เลื่อนลงมากดปุ่ม **Import / Go (ดำเนินการ)** ด้านล่างสุด
4. นำข้อมูลไปใส่ในไฟล์ `.env` ที่อยู่ใน `line_archive_core/.env`:
   ```env
   DB_CONNECTION=mysql
   DB_HOST=127.0.0.1
   DB_PORT=3306
   DB_DATABASE=user_archive
   DB_USERNAME=user_db
   DB_PASSWORD=รหัสผ่านที่คุณตั้ง
   ```

---

## 📌 ตัวอย่างการกรอก `.env` สรุปภาพรวม

```env
APP_NAME="LINE File Archive"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://your-domain.com

# ฐานข้อมูล MySQL บนโฮสติ้ง
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=user_archive
DB_USERNAME=user_db
DB_PASSWORD=your_password

# ตั้งค่า LINE
LINE_CHANNEL_ID=2001234567
LINE_CHANNEL_SECRET=a1b2c3d4e5f6g7h8i9j0
LINE_CHANNEL_ACCESS_TOKEN="eyJhbGciOiJIUzI1NiJ9.abcdefghijklmnopqrstuvwxyz..."

# ตั้งค่า Google Drive
GOOGLE_DRIVE_ENABLED=true
GOOGLE_DRIVE_SERVICE_ACCOUNT_JSON=storage/credentials/service_account.json
GOOGLE_DRIVE_SHARED_DRIVE_ID=
GOOGLE_DRIVE_ROOT_FOLDER_ID=1A2B3C4D5E6F7G8H9I0J

# คิวงานบน Shared Hosting (แบบไม่ต้องใช้ Cron Job เลย)
QUEUE_CONNECTION=sync
MAX_FILE_SIZE_MB=500
```


# เอกสารอธิบายขั้นตอนการพัฒนาและ Deploy เว็บแอปจัดการสินค้า (Northwind)

**สมาชิกกลุ่ม:** (ใส่ชื่อ-รหัสนักศึกษา)
**Live URL:** (ใส่ลิงก์ Railway)
**Source Code (Google Drive):** (ใส่ลิงก์)
**GitHub:** (ใส่ลิงก์ ถ้ามี)

> ทุกหัวข้อให้แทรกภาพหน้าจอจากที่ทำจริงตรงจุดที่มี [แคปหน้าจอ]

## 1. ภาพรวมระบบ
- เทคโนโลยี: PHP 8.2 + Apache, MySQL, PDO, HTML/CSS/JavaScript (fetch API)
- สถาปัตยกรรม: หน้าเว็บ (index.php) เรียก REST API (api/products.php) ซึ่งเชื่อมต่อฐานข้อมูล MySQL
- Deploy: Railway (Dockerfile) + MySQL service บน Railway

| Method | Endpoint | หน้าที่ |
|---|---|---|
| GET | /api/products.php?q=คำค้น | ค้นหา / แสดงรายการสินค้า |
| POST | /api/products.php | เพิ่มสินค้า |
| PUT | /api/products.php | แก้ไขสินค้า |
| DELETE | /api/products.php?id=รหัส | ลบสินค้า |

## 2. โครงสร้างโปรเจกต์
- Dockerfile, start.sh – สร้าง image และกำหนดพอร์ตตาม Railway
- config.php – เชื่อมต่อ DB จาก Environment Variables
- public/index.php – หน้าเว็บ
- public/api/products.php – API (CRUD + Validation)

## 3. การทดสอบในเครื่อง (ถ้าทำ)
1. เปิด XAMPP แล้วนำเข้า dbNorthwind.sql ผ่าน phpMyAdmin [แคปหน้าจอ]
2. ตั้ง environment: DB_HOST, DB_USER, DB_PASS, DB_NAME
3. รัน `php -S localhost:8000 -t public` แล้วเปิดเว็บ [แคปหน้าจอ]

## 4. ขั้นตอน Deploy บน Railway
1. สมัคร/ล็อกอิน railway.com ด้วย GitHub [แคปหน้าจอ]
2. push โค้ดขึ้น GitHub repository [แคปหน้าจอ]
3. New Project → **Provision MySQL** [แคปหน้าจอ]
4. New → **GitHub Repo** เลือก repo ของโปรเจกต์ (Railway ตรวจพบ Dockerfile และ build ให้) [แคปหน้าจอ]
5. ที่ service เว็บ → Variables → เพิ่มตัวแปรโดยอ้างอิงจาก MySQL service:
   - MYSQLHOST = ${{MySQL.MYSQLHOST}}
   - MYSQLPORT = ${{MySQL.MYSQLPORT}}
   - MYSQLUSER = ${{MySQL.MYSQLUSER}}
   - MYSQLPASSWORD = ${{MySQL.MYSQLPASSWORD}}
   - MYSQLDATABASE = ${{MySQL.MYSQL_DATABASE}}
   [แคปหน้าจอ]
6. Import dbNorthwind.sql เข้า MySQL บน Railway:
   - เปิด MySQL service → Connect → คัดลอกค่า Public Network (host, port, password)
   - รันคำสั่ง: `mysql -h <HOST> -P <PORT> -u root -p<PASSWORD> railway --default-character-set=utf8mb4 < dbNorthwind.sql`
   - หรือใช้ DBeaver / MySQL Workbench เชื่อมต่อแล้ว Run SQL Script [แคปหน้าจอ]
7. Settings → Networking → **Generate Domain** ได้ URL [แคปหน้าจอ]
8. รอ deploy สถานะ Success [แคปหน้าจอ]

## 5. ผลการทดสอบบน URL จริง
- ค้นหาสินค้า [แคปหน้าจอ]
- เพิ่มสินค้า + ข้อความสำเร็จ [แคปหน้าจอ]
- Validation (ชื่อว่าง/ราคาไม่ใช่ตัวเลข) [แคปหน้าจอ]
- แก้ไขสินค้า [แคปหน้าจอ]
- ลบสินค้า [แคปหน้าจอ]

## 6. ปัญหาที่พบและวิธีแก้
(ใส่ปัญหาที่เจอจริง เช่น ภาษาไทยเพี้ยน แก้โดย --default-character-set=utf8mb4)

## 7. การแบ่งหน้าที่สมาชิก
(ใส่ชื่อ – งานที่รับผิดชอบ)

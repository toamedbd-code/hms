Attendance Device Integration
=============================

সংক্ষিপ্ত বর্ণনা
----------------
এই ডকুমেন্টটি ফিঙ্গারপ্রিন্ট/ফেস ডিটেকশন ডিভাইসকে রেজিস্টার করে ও ডিভাইসের webhook ইভেন্ট গ্রহণ করে অ্যাটেনডেন্স রেকর্ড হিসেবে সংরক্ষণ করার পদ্ধতি ব্যাখ্যা করে।

কী ফাইল যোগ করা হয়েছে
----------------------
- `app/Models/AttendanceDevice.php` — ডিভাইস রেজিস্ট্রি রক্ষায় মডেল
- `database/migrations/*create_attendance_devices_table.php` — ডিভাইস টেবিল
- `app/Services/AttendanceDeviceService.php` — ডিভাইস যাচাই ও ইভেন্ট প্রসেসিং
- `app/Http/Controllers/Backend/AttendanceDeviceController.php` — রেজিস্টার + webhook endpoints
- `routes/api.php` — webhook ও register রুট
- `app/Models/Attendance.php` + migration — অ্যাটেনডেন্স রেকর্ড
- attendance কনফিগ: `config/attendance.php`

সেটআপ
------
1. মাইগ্রেশন রান করুন:

```bash
php artisan migrate
```

2. `.env` এ কনফিগারেশন (দৃষ্টান্ত):

See `docs/attendance.env.example` (below) — কিম্বা প্রোজেক্ট `.env` এ নিচের ভেলু যোগ করুন।

ডিভাইস রেজিস্ট্রেশন (Admin)
---------------------------
Admin API route ব্যবহার করে ডিভাইস রেজিস্টার করুন:

POST /api/attendance/device/register
Content-Type: application/json
Authorization: Bearer <admin-token>

Body:

```json
{
  "name": "FP Device 1",
  "identifier": "192.168.1.50", 
  "type": "fingerprint",
  "secret": "your-shared-secret"
}
```

Webhook (ডিভাইস থেকে POST)
---------------------------
ডিভাইসগুলো নিচের endpoint-টি POST করবে:

POST /api/attendance/device/webhook

Optional header: `X-Device-Secret: your-shared-secret`

Payload উদাহরণ:

```json
{
  "device_id": "192.168.1.50",
  "employee_code": "EMP001",
  "type": "in",
  "timestamp": "2026-03-11T09:02:00"
}
```

`type` হতে পারে `in` অথবা `out`।

ওভারটাইম / লেট ক্যালকুলেশন
-------------------------
- `in` ইভেন্টে একটি রেকর্ড তৈরি হবে।
- `out` ইভেন্ট এলে সার্ভিস একই দিনের সর্বশেষ `in` রেকর্ড খুঁজে বের করে `recorded_out` সেট করবে এবং
  - `duration_minutes`, `late_minutes`, `overtime_minutes` গণনা করবে
  - যদি লেট >= `ATTENDANCE_LATE_THRESHOLD_MINUTES` (ডিফল্ট 60), প্রতি পুরা ঘণ্টার জন্য `ATTENDANCE_LATE_DEDUCTION_PER_HOUR` বিয়োগ হবে
  - যদি ওভারটাইম >= `ATTENDANCE_OVERTIME_THRESHOLD_MINUTES` (ডিফল্ট 60), প্রতি পুরা ঘণ্টার জন্য `ATTENDANCE_OVERTIME_RATE_PER_HOUR` যোগ হবে

টেস্টিং (tinker)
-----------------
রেকর্ড তৈরি করে দেখতে পারেন:

```bash
php artisan tinker
$s = app(\App\Services\AttendanceDeviceService::class);
$s->processAttendanceEvent(["device_id"=>"192.168.1.50","employee_code"=>"EMP001","type"=>"in","timestamp"=>now()->toDateTimeString()]);
$s->processAttendanceEvent(["device_id"=>"192.168.1.50","employee_code"=>"EMP001","type"=>"out","timestamp"=>now()->addHours(9)->toDateTimeString()]);
\n+// তারপর দেখুন DB: select * from attendances where employee_code='EMP001';
```

Security
--------
- ডিভাইস রেজিস্টার করার সময় `secret` সেট করলে ডিভাইসকে সেই secret হেডারে পাঠাতে হবে (`X-Device-Secret`) — সার্ভিস সেটি মিলিয়ে নেবে।

HMAC Signature (Recommended)
----------------------------
ডিভাইসে `secret` সেট থাকলে webhook-এ `X-Device-Signature: sha256=<signature>` হেডার পাঠানো প্রয়োজন। সার্ভার `raw body` ব্যবহার করে `hash_hmac('sha256', rawBody, secret)` গণনা করে `signature` যাচাই করে। এটি নিরাপত্তা বাড়ায় এবং payload বৈধতা নিশ্চিত করে।

Signed Webhook Smoke Test (Recommended)
--------------------------------------
রিয়েল ডিভাইস যুক্ত করার আগে একবার signed webhook smoke test চালান:

```bash
php scripts/test_signed_webhook.php http://hms.test YOUR_DEVICE_IDENTIFIER YOUR_DEVICE_SECRET EMP001 in
```

Expected response:

```json
{"ok":true}
```

এটি পাস করলে নিচের বিষয়গুলো একসাথে ভেরিফাই হয়:
- webhook endpoint reachable
- signature validation working
- device শনাক্তকরণ working
- attendance event processing working

Per-employee Shift Overrides
---------------------------
আপনি প্রতিটি কর্মীর জন্য শিফট ওভাররাইড সেট করতে পারবেন। নতুন API endpoints (admin-only):

- `GET /api/attendance/shifts` — তালিকা
- `POST /api/attendance/shifts` — তৈরি
- `PUT /api/attendance/shifts/{id}` — আপডেট
- `DELETE /api/attendance/shifts/{id}` — মোছা

Field গুলো: `employee_code`, `start_time` (HH:MM), `end_time` (HH:MM), `effective_from`, `effective_to`.


Notes
-----
- ইমপ্লিমেন্টেশন অল্প-সোজা রাখা হয়েছে: ভবিষ্যতে per-employee scheduled shift, গ্রেস টাইম, মিনিট-ভিত্তিক প্রিশন ইত্যাদি যোগ করা যাবে।

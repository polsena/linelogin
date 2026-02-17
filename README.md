# LINE Login + Gmail Login Integration for WordPress

ปลั๊กอินนี้เพิ่มการเข้าสู่ระบบด้วย **LINE** และ **Google/Gmail** ให้กับหน้าเข้าสู่ระบบของ WordPress

## ความสามารถหลัก
- ปุ่ม Login with LINE และ Login with Google (Gmail)
- รองรับ OAuth callback แยกสำหรับ LINE และ Google
- สร้างผู้ใช้ WordPress อัตโนมัติเมื่อเข้าสู่ระบบครั้งแรก
- จับคู่บัญชีเดิมจาก Google ด้วย `sub` และอีเมล
- บันทึกค่า OAuth state แบบ transient เพื่อลดความเสี่ยง CSRF

## ติดตั้ง
1. คัดลอกไฟล์ `line-login.php` ไปที่ `wp-content/plugins/`
2. เปิดใช้งานปลั๊กอิน **LINE + Gmail Login Integration** ใน wp-admin
3. ไปที่เมนู **Settings → LINE + Gmail Login**
4. ใส่ค่า LINE:
   - LINE Channel ID
   - LINE Channel Secret
5. ใส่ค่า Google:
   - Google Client ID
   - Google Client Secret

## Callback URLs
ตั้งค่า Redirect/Callback URL ในแต่ละ provider ดังนี้:

- LINE: `https://your-site.example/?line-login-callback=1`
- Google: `https://your-site.example/?google-login-callback=1`

## หมายเหตุ
- Google login ใช้บัญชี Gmail ได้โดยตรง
- หากต้องการส่งกลับไป URL เดิมหลัง login สามารถแนบพารามิเตอร์ `redirect_to` ได้

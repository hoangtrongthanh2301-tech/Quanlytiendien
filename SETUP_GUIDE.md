# 🚀 HƯỚNG DẪN CÀI ĐẶT & KIỂM TRA HỆ THỐNG

## 📋 YÊU CẦU

- XAMPP đã cài đặt (Apache + MySQL)
- PHP 7.4+ 
- MySQL 5.7+
- Web browser

---

## 🔧 CÀI ĐẶT BƯỚC-BƯỚC

### **Step 1: Khởi động XAMPP**

```
1. Mở XAMPP Control Panel
2. Click "Start" cho Apache
3. Click "Start" cho MySQL
4. Đợi cả 2 chuyển sang green
```

### **Step 2: Import Database**

```
1. Mở trình duyệt, vào: http://localhost/phpmyadmin
2. Click "Import" ở thanh menu trên
3. Chọn file: `sql/schema.sql` từ thư mục `C:\xampp\htdocs\quanlytiendien`
4. Scroll xuống, click nút "Import" (màu xanh)
5. Chờ thông báo "Import thành công"
```

#### ✅ Kiểm tra:
- Trong sidebar bên trái, nên thấy database "qltiendien"
- Click vào, nên thấy 6 bảng: taikhoan, giadien, chisodien, hoadon, thanhtoan, thongketiendien

---

### **Step 3: Tạo Dữ Liệu Mẫu**

Vào phpMyAdmin, chọn database `qltiendien`, tab "SQL", rồi import file `sql/normalize-demo-data.sql`.
File này chuẩn hóa role cũ và tạo tài khoản demo:

```sql
-- Tài khoản demo sau khi import:
-- Admin: ADMIN001 / Admin@123
-- Khách hàng: KHDEMO01 / Pass@123
```

---

## 🧪 KIỂM TRA HỆ THỐNG

### **Test 1: Kiểm tra Kết Nối Database**

**URL:** `http://localhost:8081/`

**Kỳ vọng:** 
- Được chuyển thẳng đến trang đăng nhập
- Không có lỗi PHP
- CSS hiển thị bình thường

---

### **Test 2: Đăng Nhập**

**URL:** `http://localhost:8081/login.html`

**Thực hiện:**
1. Chọn tab "Tài khoản"
2. Nhập:
   - Mã khách hàng: `KHDEMO01`
   - Mật khẩu: `Pass@123`
3. Click "Đăng nhập"

**Kỳ vọng:** 
- ✅ Chuyển hướng sang trang customer.php
- ✅ Không báo lỗi

**Nếu lỗi:**
- [ ] Kiểm tra lại dữ liệu trong bảng taikhoan
- [ ] Kiểm tra xem mCLS Password có đúng không

---

### **Test 3: Tra Cứu Hóa Đơn**

**Sau khi đăng nhập, vào:** `http://localhost/QLtiendien/invoice-search.html`

**Thực hiện:**
1. Nhập:
   - Mã khách hàng: `KH000001`
   - Tháng: `5`
   - Năm: `2026`
2. Click "Tra cứu"

**Kỳ vọng:**
- ✅ Hiển thị thông tin hóa đơn
- ✅ Hiển thị chi tiết tiền theo bậc
- ✅ Hiển thị tổng tiền

**Nếu lỗi "Không tìm thấy hóa đơn":**
- [ ] Kiểm tra xem có dữ liệu chisodien cho tháng 5 năm 2026 không
- [ ] Chạy script rebuild_thongke.php

---

### **Test 4: Thanh Toán Hóa Đơn**

**URL:** `http://localhost/QLtiendien/payment-list.html`

**Thực hiện:**
1. Nhập mã khách hàng: `KH000001`
2. Click "Tìm"

**Kỳ vọng:**
- ✅ Hiển thị các hóa đơn chưa thanh toán
- ✅ Hiển thị chi tiết thanh toán

---

### **Test 5: Lịch Sử Thanh Toán**

**URL:** `http://localhost/QLtiendien/payment-history.html`

**Thực hiện:**
1. Nhập mã khách hàng: `KH000001`
2. Click "Xem lịch sử"

**Kỳ vọng:**
- ✅ Hiển thị danh sách các lần thanh toán trước

---

## ⚠️ TROUBLESHOOTING

### ❌ Lỗi: "Kết nối thất bại"

**Nguyên nhân:** MySQL chưa chạy hoặc cấu hình config.php sai

**Giải pháp:**
```
1. Mở XAMPP Control Panel
2. Kiểm tra MySQL "Start" và chuyển sang green
3. Nếu vẫn lỗi, kiểm tra config.php:
   - host = "localhost"
   - user = "root"
   - password = "" (trống)
   - database = "quanlytiendien"
```

---

### ❌ Lỗi: "Tên đăng nhập hoặc mật khẩu không đúng"

**Nguyên nhân:** Dữ liệu không chính xác

**Giải pháp:**
```
1. Vào phpmyadmin
2. Chọn bảng taikhoan
3. Kiểm tra:
   - Có KH000001 không?
   - Mật khẩu là gì?
   - trangthai = 'hoatdong' chưa?
4. Nếu không, insert lại:

INSERT INTO taikhoan (maKH, hovaten, email, sodienthoai, matkhau, quyen, trangthai)
VALUES ('KH000001', 'Test User', 'test@test.com', '0123456789', '123456', 'nguoidung', 'hoatdong');
```

---

### ❌ Lỗi: "Không tìm thấy hóa đơn"

**Nguyên nhân:** Chưa có dữ liệu chisodien cho tháng/năm đó

**Giải pháp:**
```
1. Vào phpmyadmin
2. Chạy lệnh SQL:

INSERT INTO chisodien (maKH, chisocu, chisomoi, thang, nam)
VALUES ('KH000001', 1000, 1150, 5, 2026);

3. Vào URL: http://localhost/QLtiendien/scripts/rebuild_thongke.php
   (để trigger tự động tạo dữ liệu)
```

---

### ❌ Lỗi: "Chưa đăng nhập"

**Nguyên nhân:** Session hết hạn hoặc truy cập trực tiếp API

**Giải pháp:**
```
1. Quay lại login.html
2. Đăng nhập lại
3. Nếu vẫn lỗi, xóa cookies:
   - F12 -> Application -> Cookies -> Xóa localhost
```

---

## 📊 CẤU TRÚC DỮ LIỆU

### Bảng taikhoan
```
maKH        | hovaten          | email          | sodienthoai | matkhau | quyen    | trangthai
KH000001    | Nguyễn Văn A     | a@test.com     | 0912345678  | 123456  | nguoidung| hoatdong
KH000002    | Trần Thị B       | b@test.com     | 0987654321  | 123456  | admin    | hoatdong
```

### Bảng giadien (Giá điện - đã có sẵn)
```
bac | sanluong | dongia  | ngayapdung
1   | 50       | 1984    | 2026-01-01
2   | 50       | 2050    | 2026-01-01
3   | 100      | 2380    | 2026-01-01
...
```

---

## 📱 URLs CHÍNH

| Trang | URL |
|-------|-----|
| Trang chủ | http://localhost/QLtiendien/ |
| Đăng nhập | http://localhost/QLtiendien/login.html |
| Trang khách hàng | http://localhost/QLtiendien/customer.html |
| Tra cứu HĐ | http://localhost/QLtiendien/invoice-search.html |
| Thanh toán | http://localhost/QLtiendien/payment-list.html |
| Lịch sử | http://localhost/QLtiendien/payment-history.html |
| Admin | http://localhost/QLtiendien/admin.html |
| phpMyAdmin | http://localhost/phpmyadmin |

---

## 🔒 BẢO MẬT - CẦN LÀM NGAY

⚠️ **CẢNH BÁO:** Hệ thống chưa sử dụng những biện pháp bảo mật tốt nhất!

### Ngay lập tức:
1. [ ] Mã hóa mật khẩu (`password_hash()` + `password_verify()`)
2. [ ] Thêm CSRF token vào form
3. [ ] Validate email format
4. [ ] Giới hạn độ dài mật khẩu

### Sớm:
5. [ ] Thêm rate limiting
6. [ ] Thêm account lockout sau 3 lần sai
7. [ ] Ghi log mọi thay đổi
8. [ ] HTTPS trên production

---

## ✅ CHECKLIST SETUP

- [ ] XAMPP đang chạy (Apache + MySQL green)
- [ ] Database "quanlytiendien" đã được tạo
- [ ] Tất cả 6 bảng đã được tạo
- [ ] Giá điện (giadien) đã được insert
- [ ] Tài khoản mẫu đã được tạo
- [ ] Chỉ số điện mẫu đã được nhập
- [ ] Script rebuild_thongke.php đã chạy
- [ ] Có thể truy cập http://localhost/QLtiendien/
- [ ] Có thể đăng nhập thành công
- [ ] Có thể tra cứu hóa đơn

---

**Tài liệu này được cập nhật lần cuối: 28/05/2026**

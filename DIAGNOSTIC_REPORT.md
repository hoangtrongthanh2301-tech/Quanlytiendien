# 🔍 BÁO CÁO CHẨN ĐOÁN HỆ THỐNG QUẢN LÝ TIỀN ĐIỆN

**Ngày kiểm tra:** 28/05/2026  
**Trạng thái chung:** ⚠️ CÓ LỖI CẦN SỬA

---

## 📋 TÓNG TẮT

Hệ thống có **4 vấn đề chính** cần sửa trước khi chạy được:

1. ❌ **Lỗi lập trình trong invoice-search.php** (Duplicate SQL Statement)
2. ⚠️ **Chưa có session check trong 1 số file**
3. ✅ **Kết nối Database OK** (Cấu hình đúng)
4. ✅ **Assets và file cấu trúc OK**

---

## 🔴 VẤN ĐỀ CHÍNH

### 1. **LỖI TRONG [invoice-search.php](invoice-search.php)**

#### Vị trí: Dòng 42-51

**Vấn đề:** Câu lệnh SQL prepare được gọi 2 lần với cùng một query

```php
// ❌ LỖI: Prepare được gọi 2 lần
$stmt = $conn->prepare(
  "SELECT bacthang AS bac, dongia, sanluong AS kwh, thanhtien AS amount " .
  "FROM thongketiendien " .
  "WHERE maCSD = ? AND maKH = ? " .
  "ORDER BY bacthang ASC"
);
$stmt = $conn->prepare(
  "SELECT bacthang AS bac, dongia, sanluong AS kwh, thanhtien AS amount " .
  "FROM thongketiendien " .
  "WHERE maCSD = ? AND maKH = ? " .
  "ORDER BY bacthang ASC"
);
```

**Nguyên nhân:** Copy-paste nhầm lần thứ 2

**Giải pháp:** Xóa đi lần prepare thứ 2

---

### 2. **CHƯA CÓ SESSION CHECK**

#### File: [login.php](login.php), [invoice-search.php](invoice-search.php), [payment-list.php](payment-list.php), [payment-history.php](payment-history.php)

**Vấn đề:** Các file PHP không kiểm tra xem user đã đăng nhập hay chưa. Ai cũng có thể truy cập trực tiếp vào các API này mà không cần login.

**Giải pháp:** Thêm kiểm tra session vào đầu mỗi file

```php
<?php
session_start();
require 'config.php';

// ✅ THÊM KIỂM TRA NÀY
if (!isset($_SESSION['user'])) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'error' => 'Chưa đăng nhập']);
    http_response_code(401);
    exit;
}

// Phần còn lại của code...
?>
```

---

## ✅ NHỮNG GÌ ĐÃ OK

### 1. **Kết nối Database** - ✅ HOÀN HẢO

**File:** [config.php](config.php)

```php
$host = "localhost";
$user = "root";
$password = "";
$database = "quanlytiendien";
$conn = mysqli_connect($host, $user, $password, $database);
```

✅ Cấu hình đúng cho XAMPP  
✅ Charset utf8mb4 được set  
✅ Kiểm tra lỗi kết nối OK

---

### 2. **Database Schema** - ✅ HỎP LỆ

**File:** [data.sql](data.sql)

✅ Có 6 bảng chính:
- `taikhoan` - Tài khoản khách hàng
- `giadien` - Bảng giá điện
- `chisodien` - Chỉ số điện
- `hoadon` - Hóa đơn
- `thanhtoan` - Thanh toán
- `thongketiendien` - Thống kê tiền điện

✅ Có 4 Trigger tự động:
- `trg_tao_hoadon` - Tạo hóa đơn khi nhập chỉ số
- `trg_thongke_tiendien` - Tính toán chi tiết tiền theo bậc
- `trg_tao_thanhtoan` - Tạo phiếu thanh toán
- `trg_capnhat_thanhtoan` - Cập nhật timestamp

✅ Có Function tính tiền điện theo bậc  
✅ Có View truy vấn hóa đơn + thanh toán

---

### 3. **Cấu trúc File** - ✅ ĐỀ

```
✅ config.php - Cấu hình kết nối
✅ login.html - Giao diện đăng nhập
✅ login.php - Xử lý login
✅ customer.html - Trang chủ khách hàng
✅ invoice-search.html + .php - Tra cứu hóa đơn
✅ payment-list.html + .php - Thanh toán
✅ payment-history.html + .php - Lịch sử
✅ admin.html - Trang admin
✅ assets/css/style.css - CSS đầy đủ
✅ assets/img/ - Logo và hình ảnh
✅ scripts/rebuild_thongke.php - Script rebuild dữ liệu
```

---

### 4. **Logic Ứng Dụng** - ✅ CHÍNH XÁC

✅ Login có hỗ trợ 2 kiểu: Mã khách hàng + Số điện thoại  
✅ Có kiểm tra quyền admin/user  
✅ Có kiểm tra trạng thái tài khoản (khoá/hoạt động)  
✅ Payment search hỗ trợ tìm theo mã HĐ hoặc mã KH  
✅ Có prepared statement (bảo vệ SQL Injection)

---

## 📌 HƯỚNG DẪN CÀI ĐẶT & CHẠY

### Step 1: Chuẩn bị Database

```bash
# Đảm bảo MySQL đang chạy trong XAMPP
# Mở http://localhost/phpmyadmin

# Import file data.sql:
# - Vào phpmyadmin
# - Chọn "Import"
# - Chọn file data.sql
# - Click "Go"
```

### Step 2: Kiểm tra kết nối

```bash
# Truy cập vào:
http://localhost/QLtiendien/

# Nên thấy trang chủ với logo EVN
```

### Step 3: Đăng nhập thử

Dữ liệu mẫu từ database:
- Mã KH: `KH000001` (hoặc tùy theo dữ liệu bạn insert)
- Mật khẩu: Tùy theo dữ liệu

---

## ⚠️ CÁC CẢNH BÁO

### 1. Mật khẩu không mã hóa
**Vấn đề:** Mật khẩu trong database được lưu dưới dạng plain text

**Giải pháp:**
```php
// Thay thế cách lưu mật khẩu:
// ❌ $password = $_POST['password'];
// ✅ $hashedPassword = password_hash($_POST['password'], PASSWORD_BCRYPT);
```

### 2. Không có hạn rate limit
Ai đó có thể brute force mật khẩu

### 3. SQL không có validation đầy đủ
Một số giá trị từ POST không được kiểm tra kĩ

---

## 🔧 DANH SÁCH SỬA

### NGAY LẬP TỨC (Critical):
- [ ] Xóa duplicate prepare() trong [invoice-search.php](invoice-search.php)
- [ ] Thêm session check vào 4 file PHP
- [ ] Test kết nối database

### SỚM (Important):
- [ ] Mã hóa mật khẩu với password_hash()
- [ ] Thêm CSRF protection
- [ ] Validate email format

### SAU (Nice to have):
- [ ] Rate limiting cho login
- [ ] Error logging
- [ ] Audit trail

---

## ✨ TÌNH TRẠNG HOÀN CẢN

| Thành phần | Trạng thái | Ghi chú |
|-----------|----------|--------|
| Database | ✅ OK | Cấu trúc tốt, triggers OK |
| Config | ✅ OK | Kết nối đúng |
| Login | ⚠️ Cần fix | Thêm session check |
| Invoice Search | ❌ LỖI | Duplicate prepare statement |
| Invoice Payment | ⚠️ Cần fix | Thêm session check |
| Payment History | ⚠️ Cần fix | Thêm session check |
| Assets | ✅ OK | CSS + Images đầy đủ |
| HTML | ✅ OK | Cấu trúc đúng |

---

## 📞 NEXT STEPS

1. **Sửa lỗi trong invoice-search.php** (5 phút)
2. **Thêm session check** (10 phút)
3. **Import database** (2 phút)
4. **Test đăng nhập** (3 phút)
5. **Test tra cứu hóa đơn** (5 phút)

**Tổng thời gian:** ~25 phút

---

**Báo cáo được tạo tự động**

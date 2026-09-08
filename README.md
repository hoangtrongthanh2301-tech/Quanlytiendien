# 🏠 QUẢN LÝ TIỀN ĐIỆN

## ⚡ Hệ Thống Quản Lý Hóa Đơn Tiền Điện Điện Tử

Ứng dụng web giúp khách hàng tra cứu hóa đơn, thanh toán trực tuyến, và xem lịch sử thanh toán.

---

## 🚀 KHỞI ĐỘNG NHANH

### Bước 1: Chuẩn bị
```bash
✓ Docker Desktop hoặc XAMPP + MySQL
✓ Web browser sẵn sàng
✓ Port 8081 khả dụng khi chạy Docker
```

### Bước 2: Chạy bằng Docker (khuyến nghị cho demo/PaaS)
```
1. Copy `.env.example` thành `.env`
2. Đảm bảo MySQL/XAMPP đang chạy
3. Import `sql/schema.sql` vào database `qltiendien`
4. Chạy `docker compose up -d --build`
```

Truy cập:

```
http://localhost:8081
```

Tài khoản trong database hiện có vẫn được sử dụng. File `sql/normalize-demo-data.sql` chỉ là tùy chọn để chuẩn hóa role cũ hoặc tạo dữ liệu demo.

### Bước 3: Tạo khóa blockchain cho chữ ký số
```bash
php scripts/generate_blockchain_keys.php
```

### Bước 4: Khởi tạo blockchain từ dữ liệu lịch sử
```bash
php scripts/init_blockchain_from_history.php
```

Nếu cần làm lại toàn bộ blockchain từ đầu, dùng:
```bash
php scripts/init_blockchain_from_history.php --force
```

---

## 📖 HƯỚNG DẪN CHI TIẾT

**👉 ĐỌC FILE NÀY TRƯỚC:**

| File | Mô tả | Dùng cho |
|------|-------|---------|
| **[SUMMARY.md](SUMMARY.md)** | 📌 Tóng tắt kết quả kiểm tra | 👤 Tất cả |
| **[SETUP_GUIDE.md](SETUP_GUIDE.md)** | 📋 Hướng dẫn cài đặt từng bước | 👤 Nhà phát triển |
| **[DIAGNOSTIC_REPORT.md](DIAGNOSTIC_REPORT.md)** | 🔍 Báo cáo chi tiết các vấn đề | 👤 QA/Tech Lead |
| **[FIXES_APPLIED.md](FIXES_APPLIED.md)** | ✅ Chi tiết sửa chữa được thực hiện | 👤 Lập trình viên |

---

## 🎯 TÍNH NĂNG CHÍNH

### 👥 Khách Hàng
- ✅ **Đăng nhập** - Dùng mã KH hoặc số điện thoại
- ✅ **Tra cứu hóa đơn** - Xem chi tiết kỳ cước
- ✅ **Thanh toán** - Xem hóa đơn chưa thanh toán
- ✅ **Lịch sử thanh toán** - Xem các lần thanh toán trước

### 👨‍💼 Quản Trị
- ✅ **Quản lý tài khoản** - Thêm/sửa/xóa khách hàng
- ✅ **Nhập chỉ số điện** - Tạo hóa đơn mới
- ✅ **Ghi dữ liệu nhập chỉ số vào blockchain nội bộ MySQL** - Lưu hash, hash trước, chữ ký số và người nhập
- ✅ **Quản lý giá điện** - Cập nhật bảng giá
- ✅ **Thống kê doanh thu** - Báo cáo tài chính

---

## 📁 CẤU TRÚC THƯ MỤC

```
QLtiendien/
├── 📄 index.html                 # Trang chủ
├── 📄 login.html/php             # Đăng nhập
├── 📄 admin.html                 # Trang Admin
├── 📄 customer.html              # Trang khách hàng
│
├── 🔍 INVOICE (Hóa đơn)
│   ├── invoice-search.html/php   # Tra cứu
│   └── payment-list.html/php  # Thanh toán
│
├── 💳 PAYMENT (Thanh toán)
│   └── payment-history.html/php  # Lịch sử
│
├── 🗂️ assets/
│   ├── css/style.css             # CSS chính
│   └── img/                      # Logo + hình ảnh
│
├── 🔧 scripts/
│   └── rebuild_thongke.php       # Script rebuild dữ liệu
│
├── 📊 config.php                 # Cấu hình Database
├── 📊 data.sql                   # SQL script khởi tạo
│
└── 📚 Tài liệu
    ├── SUMMARY.md                # 📌 BẮT ĐẦU TỪ ĐÂY
    ├── SETUP_GUIDE.md            # Cài đặt chi tiết
    ├── DIAGNOSTIC_REPORT.md      # Báo cáo vấn đề
    └── FIXES_APPLIED.md          # Sửa chữa đã làm
```

---

## 🔌 TECH STACK

| Layer | Tech |
|-------|------|
| **Frontend** | HTML5, CSS3, JavaScript |
| **Backend** | PHP 7.4+ |
| **Database** | MySQL 5.7+ |
| **Server** | Apache (XAMPP) |
| **Charset** | UTF-8 / UTF-8MB4 |

---

## 📊 DATABASE SCHEMA

### 6 Bảng Chính
1. **taikhoan** - Tài khoản khách hàng + admin
2. **giadien** - Bảng giá điện theo bậc
3. **chisodien** - Chỉ số điện tháng/năm
4. **hoadon** - Hóa đơn (được tạo tự động)
5. **thanhtoan** - Ghi nhận thanh toán
6. **thongketiendien** - Thống kê chi tiết tiền

### 4 Triggers Tự Động
- `trg_tao_hoadon` - Tạo HĐ khi nhập chỉ số
- `trg_thongke_tiendien` - Tính chi tiết theo bậc
- `trg_tao_thanhtoan` - Tạo phiếu thanh toán
- `trg_capnhat_thanhtoan` - Cập nhật trạng thái

---

## 🧪 TEST CREDENTIALS

Sau khi setup database, dùng để test:

```
Tài khoản 1 (Khách hàng):
  Mã KH: KH000001
  Mật khẩu: 123456
  Quyền: nguoidung

Tài khoản 2 (Admin):
  Mã KH: KH000002
  Mật khẩu: 123456
  Quyền: admin
```

> ⚠️ **LƯU Ý:** Đây chỉ là dữ liệu test. Mật khẩu không mã hóa!

---

## ⚠️ LƯU Ý BẢOMAT

🔴 **DEVELOPMENT ONLY**

Hệ thống này **KHÔNG** được sử dụng trên production vì:
- ❌ Mật khẩu không mã hóa
- ❌ Không có CSRF protection
- ❌ Không có rate limiting
- ❌ Không có HTTPS

**Cần cải thiện trước khi deploy production!**

---

## 🐛 TROUBLESHOOTING

### Không kết nối được database?
```
✓ Kiểm tra MySQL đang chạy trong XAMPP
✓ Kiểm tra config.php (host, user, password, database)
✓ Kiểm tra database "quanlytiendien" đã tạo chưa
```

### Lỗi "Tên đăng nhập không đúng"?
```
✓ Kiểm tra dữ liệu trong bảng taikhoan
✓ Kiểm tra trạng thái: trangthai = 'hoatdong'
✓ Kiểm tra mật khẩu (plain text trong DB)
```

### Không thấy hóa đơn?
```
✓ Kiểm tra bảng chisodien có dữ liệu không
✓ Chạy: http://localhost/QLtiendien/scripts/rebuild_thongke.php
✓ Kiểm tra tháng/năm có dữ liệu không
```

👉 **Xem chi tiết trong [SETUP_GUIDE.md](SETUP_GUIDE.md) → Troubleshooting**

---

## 📞 LIÊN HỆ HỖ TRỢ

| Vấn đề | Giải pháp |
|-------|---------|
| Cài đặt | → SETUP_GUIDE.md |
| Lỗi lập trình | → DIAGNOSTIC_REPORT.md |
| Những gì được sửa | → FIXES_APPLIED.md |
| Tóng quát | → SUMMARY.md |

---

## 📋 CHECKLIST CÀI ĐẶT

- [ ] XAMPP + Apache + MySQL chạy
- [ ] Database "quanlytiendien" được tạo
- [ ] Tất cả 6 bảng có dữ liệu
- [ ] Tài khoản mẫu được tạo
- [ ] Có thể truy cập http://localhost/QLtiendien/
- [ ] Có thể đăng nhập thành công
- [ ] Có thể tra cứu hóa đơn

---

## 🔄 NEXT STEPS

1. **Đọc:** [SUMMARY.md](SUMMARY.md) (5 phút)
2. **Làm theo:** [SETUP_GUIDE.md](SETUP_GUIDE.md) (20 phút)
3. **Test:** Thử các chức năng (10 phút)
4. **Phát triển:** Thêm tính năng mới

---

## 📝 LICENSE

Private Project - EVN

---

## 🎉 SẴN SÀNG!

**Hệ thống Quản Lý Tiền Điện đã sẵn sàng để cài đặt.**

👉 **Bắt đầu từ: [SUMMARY.md](SUMMARY.md)**

---

*Cập nhật lần cuối: 28/05/2026*

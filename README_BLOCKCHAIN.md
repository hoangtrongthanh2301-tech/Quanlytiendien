# Hệ thống Quản lý Tiền điện với Blockchain Batch và OOP PHP

## Thiết kế ERD

- `customers` (`maKH`) 1..* `chisodien` (`maKH`): quản lý khách hàng sử dụng điện.
- `chisodien` (`maCSD`) chứa chỉ số điện tháng, tiêu thụ và ngày nhập.
- `blockchain_batch` (`block_id`) chứa dữ liệu blockchain theo tháng/năm.

## Kiến trúc thư mục

- `app/Core`: cơ sở hạ tầng ứng dụng (autoloader, request, response, container, router, database wrapper).
- `app/Models`: các thực thể dữ liệu.
- `app/Repositories`: lớp truy vấn dữ liệu theo Repository Pattern.
- `app/Services`: lớp xử lý nghiệp vụ theo Service Layer.
- `app/Controllers`: controller nhận request và trả response.
- `public/index.php`: điểm vào API.
- `sql/schema.sql`: script SQL tạo schema.

## Các thành phần chính

- `HashService`: băm SHA256 từng bản ghi.
- `MerkleTreeService`: xây dựng Merkle Root từ các hash.
- `BlockchainService`: tạo batch blockchain tháng/năm.
- `VerificationService`: xác minh Merkle Root, current hash và chữ ký.
- `ImportService`: import CSV/Excel lớn theo chunk.

## Endpoints API

- `GET public/index.php?action=status`
  - Kiểm tra kết nối API.
- `POST public/index.php?action=import`
  - Tải lên `csv_file` chứa các cột: `maKH`, `chisocu`, `chisomoi`, `thang`, `nam`, `ngaynhap`.
- `POST public/index.php?action=create_batch`
  - Tạo batch blockchain cho `thang` và `nam`.
- `GET public/index.php?action=verify_batch&thang=X&nam=Y`
  - Xác minh batch blockchain của tháng/năm.
- `GET public/index.php?action=verify_chain`
  - Kiểm tra toàn bộ chuỗi blockchain batch.
- `GET public/index.php?action=dashboard`
  - Lấy thống kê tháng và trạng thái blockchain.

## Triển khai trên XAMPP

1. Đặt thư mục vào `htdocs/QLtiendien`.
2. Tạo database bằng `sql/schema.sql`.
3. Đảm bảo `config.php` trỏ tới database đúng.
4. Tạo khóa blockchain:
   - `openssl genrsa -out blockchain_private.pem 2048`
   - `openssl rsa -in blockchain_private.pem -pubout -out blockchain_public.pem`
5. Truy cập API qua `http://localhost/QLtiendien/public/index.php`.

## Luồng dữ liệu

1. Import CSV vào `chisodien`.
2. Tạo hash từng bản ghi với `HashService`.
3. Xây dựng Merkle Root bằng `MerkleTreeService`.
4. Tạo batch blockchain tháng/năm bằng `BlockchainService`.
5. Lưu `merkle_root`, `previous_hash`, `current_hash`, `signature`, `signer`, `created_at`.
6. Khi verify, `VerificationService` tái sinh Merkle Root từ `chisodien` và so sánh với batch đã lưu.

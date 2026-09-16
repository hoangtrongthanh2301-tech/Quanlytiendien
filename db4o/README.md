# DB4O object database

> Lưu ý: hệ thống quản lý tiền điện cũ trong thư mục gốc vẫn là hệ thống chính. Các file PHP, giao diện và database MySQL cũ không bị thay thế. Thư mục `db4o/` chỉ phục vụ nghiên cứu/thử nghiệm DB4O và có thể bỏ qua khi chạy website.

Thư mục này là bản chuyển đổi thử nghiệm từ MariaDB `qltiendien` sang DB4O. Ứng dụng PHP hiện tại vẫn dùng MySQL; phần DB4O chạy độc lập bằng Java để kiểm thử mô hình object và di chuyển dữ liệu.

## Chạy hệ thống cũ

Để chạy website quản lý tiền điện ban đầu, chỉ cần bật `Apache` và `MySQL` trong XAMPP rồi truy cập:

```text
http://localhost/quanlytiendien/
```

Không cần chạy Maven hoặc mở file `.yap`. Các trang PHP sẽ tiếp tục đọc database `qltiendien` từ MySQL như trước.

## Object model

- `Customer`: thông tin tài khoản và các collection `meterReadings`, `invoices`.
- `MeterReading`: chỉ số cũ/mới, kỳ sử dụng và các `UsageBreakdown`.
- `Invoice`: hóa đơn gắn trực tiếp với `MeterReading` và `Payment`.
- `Payment`: thông tin thanh toán của hóa đơn.
- `Tariff`: giá điện theo bậc.
- `AdministrativeUnit`: xã/tỉnh và mã điện lực.
- `BlockchainRecord`: bản ghi xác minh chỉ số và tham chiếu `MeterReading`.

Các quan hệ này dùng object reference thay cho foreign key. `thongketiendien` được biểu diễn thành danh sách `UsageBreakdown` bên trong `MeterReading`.

## Chuẩn bị

Cần cài JDK 8+ và Maven 3.8+. DB4O là thư viện Java, không thể được gọi trực tiếp từ PHP. MySQL phải đang chạy và database `qltiendien` phải có dữ liệu.

## Import từ MySQL

Chạy từ thư mục dự án:

```bash
export MAVEN_OPTS="--add-opens=java.base/java.math=ALL-UNNAMED"
mvn -f db4o/pom.xml compile exec:java \
  -Dmysql.url="jdbc:mysql://127.0.0.1:3306/qltiendien" \
  -Dmysql.user=root \
  -Dmysql.password= \
  -Dmysql.customer.limit=1000 \
  -Ddb4o.file="storage/qltiendien-demo.yap"
```

Trên Windows PowerShell, đặt biến môi trường bằng `$env:MAVEN_OPTS="--add-opens=java.base/java.math=ALL-UNNAMED"` trước khi chạy. File DB4O được tạo tại `storage/qltiendien.yap`.

## Đọc object đã import

```bash
export MAVEN_OPTS="--add-opens=java.base/java.math=ALL-UNNAMED"
mvn -f db4o/pom.xml compile exec:java \
  -Dexec.mainClass=vn.quanlytiendien.db4o.Db4oQueryExample \
  -Dexec.args="storage/qltiendien.yap"
```

Để kiểm tra nhanh bản demo, dùng `-Dexec.args="storage/qltiendien-demo.yap"`. Bản demo đã được giới hạn 1.000 khách hàng và dùng để trình bày/truy vấn an toàn hơn.

## Lưu ý chuyển đổi

- Đây là bản đồng bộ một chiều MySQL -> DB4O, chưa thay thế backend PHP.
- DB4O 5.5 dùng địa chỉ file 32-bit; một file `.yap` không phù hợp để chứa toàn bộ dữ liệu lớn hơn khoảng 2 GB. Dùng `-Dmysql.customer.limit=1000` cho bản demo hoặc chia dữ liệu thành nhiều file DB4O.
- Không chạy importer đồng thời trên cùng một file `.yap`.
- Mật khẩu được chuyển nguyên trạng theo dữ liệu nguồn; cần hash lại trước khi dùng production.
- DB4O đã cũ và không có hỗ trợ chính thức như các hệ quản trị hiện đại. Nên dùng bản này cho phạm vi đồ án/nghiên cứu hoặc lớp persistence riêng, không xóa MySQL trước khi kiểm thử đầy đủ.

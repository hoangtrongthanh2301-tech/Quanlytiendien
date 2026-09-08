USE `qltiendien`;

-- Chuẩn hóa dữ liệu cũ để login và customer guard dùng cùng role.
UPDATE `taikhoan`
SET `quyen` = 'khachhang'
WHERE `quyen` = 'nguoidung';

-- Tài khoản demo cho buổi trình bày/pass.
INSERT INTO `taikhoan`
    (`maKH`, `hovaten`, `email`, `sodienthoai`, `matkhau`, `quyen`, `trangthai`, `diachi`)
VALUES
    ('ADMIN001', 'Quản trị viên demo', 'admin.demo@example.com', '0900000001', '$2y$10$i9TfhFUvJtzQroGrhEJRK.yuQYRXBNn0qJFRT9fV6oBGtwglm1loC', 'admin', 'hoatdong', 'Phòng quản trị'),
    ('KHDEMO01', 'Khách hàng demo', 'customer.demo@example.com', '0900000002', '$2y$10$HN1ZWlx9j0NGKtsNISeqfeLtcNGL.v8DfnqQFNlVaJW5UNPMOwqn.', 'khachhang', 'hoatdong', 'Địa chỉ demo')
ON DUPLICATE KEY UPDATE
    `matkhau` = VALUES(`matkhau`),
    `quyen` = VALUES(`quyen`),
    `trangthai` = VALUES(`trangthai`);

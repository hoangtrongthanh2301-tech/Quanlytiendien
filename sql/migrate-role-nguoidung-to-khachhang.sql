-- Rename the customer role while preserving existing accounts.
ALTER TABLE taikhoan
  MODIFY COLUMN quyen ENUM('admin', 'nguoidung', 'khachhang') NOT NULL DEFAULT 'khachhang';

UPDATE taikhoan
SET quyen = 'khachhang'
WHERE quyen = 'nguoidung';

ALTER TABLE taikhoan
  MODIFY COLUMN quyen ENUM('admin', 'khachhang') NOT NULL DEFAULT 'khachhang';

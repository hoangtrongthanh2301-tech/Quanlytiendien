-- Indexes used by dashboard and date/status reports.
ALTER TABLE `chisodien`
    ADD INDEX `idx_chisodien_ngaynhap` (`ngaynhap`),
    ADD INDEX `idx_chisodien_maKH_ngaynhap` (`maKH`, `ngaynhap`);

ALTER TABLE `hoadon`
    ADD INDEX `idx_hoadon_ngaytao` (`ngaytao`),
    ADD INDEX `idx_hoadon_trangthai_ngaytao` (`trangthai`, `ngaytao`);

ALTER TABLE `taikhoan`
    ADD INDEX `idx_taikhoan_quyen` (`quyen`);
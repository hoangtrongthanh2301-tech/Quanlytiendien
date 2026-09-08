-- Chay mot lan tren database qltiendien dang su dung.
-- Ma KH: PD + 2 so thanh pho + 5 so xa/phuong + 6 so thu tu.
USE `qltiendien`;

SET FOREIGN_KEY_CHECKS = 0;

ALTER TABLE `chisodien` DROP FOREIGN KEY `chisodien_ibfk_1`;
ALTER TABLE `hoadon` DROP FOREIGN KEY `hoadon_ibfk_1`;
ALTER TABLE `thanhtoan` DROP FOREIGN KEY `thanhtoan_ibfk_1`;
ALTER TABLE `thongketiendien` DROP FOREIGN KEY `thongketiendien_ibfk_1`;

ALTER TABLE `taikhoan` MODIFY `maKH` varchar(15) NOT NULL;
ALTER TABLE `chisodien` MODIFY `maKH` varchar(15) NOT NULL;
ALTER TABLE `blockchain_chisodien` MODIFY `maKH` varchar(15) NOT NULL;
ALTER TABLE `hoadon` MODIFY `maKH` varchar(15) NOT NULL;
ALTER TABLE `thanhtoan` MODIFY `maKH` varchar(15) NOT NULL;
ALTER TABLE `thongketiendien` MODIFY `maKH` varchar(15) NOT NULL;

ALTER TABLE `chisodien`
	ADD CONSTRAINT `chisodien_ibfk_1` FOREIGN KEY (`maKH`) REFERENCES `taikhoan` (`maKH`) ON DELETE RESTRICT ON UPDATE CASCADE;
ALTER TABLE `hoadon`
	ADD CONSTRAINT `hoadon_ibfk_1` FOREIGN KEY (`maKH`) REFERENCES `taikhoan` (`maKH`);
ALTER TABLE `thanhtoan`
	ADD CONSTRAINT `thanhtoan_ibfk_1` FOREIGN KEY (`maKH`) REFERENCES `taikhoan` (`maKH`);
ALTER TABLE `thongketiendien`
	ADD CONSTRAINT `thongketiendien_ibfk_1` FOREIGN KEY (`maKH`) REFERENCES `taikhoan` (`maKH`);

SET FOREIGN_KEY_CHECKS = 1;
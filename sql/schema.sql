-- Schema for Electricity Management with Blockchain Batch Integrity

CREATE DATABASE IF NOT EXISTS `qltiendien` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE `qltiendien`;

DROP TABLE IF EXISTS `view_hoadon_thanhtoan`;
DROP TABLE IF EXISTS `thongketiendien`;
DROP TABLE IF EXISTS `thanhtoan`;
DROP TABLE IF EXISTS `hoadon`;
DROP TABLE IF EXISTS `blockchain_batch`;
DROP TABLE IF EXISTS `blockchain_chisodien`;
DROP TABLE IF EXISTS `chisodien`;
DROP TABLE IF EXISTS `giadien`;
DROP TABLE IF EXISTS `taikhoan`;

CREATE TABLE IF NOT EXISTS `taikhoan` (
    `maKH` varchar(15) NOT NULL,
    `hovaten` varchar(100) NOT NULL,
    `email` varchar(100) NOT NULL,
    `sodienthoai` varchar(15) NOT NULL,
    `matkhau` varchar(255) NOT NULL,
    `quyen` enum('admin','khachhang') DEFAULT 'khachhang',
    `trangthai` enum('hoatdong','khoa') DEFAULT 'hoatdong',
    `diachi` text DEFAULT NULL,
    `ngaytao` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`maKH`),
    UNIQUE KEY `email` (`email`),
    UNIQUE KEY `sodienthoai` (`sodienthoai`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `giadien` (
    `maGD` int(11) NOT NULL AUTO_INCREMENT,
    `bac` int(11) NOT NULL,
    `sanluong` int(11) NOT NULL,
    `dongia` decimal(10,2) NOT NULL,
    `ngayapdung` date NOT NULL,
    `ngaytao` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`maGD`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `chisodien` (
    `maCSD` int(11) NOT NULL AUTO_INCREMENT,
    `maKH` varchar(15) NOT NULL,
    `chisocu` int(11) NOT NULL,
    `chisomoi` int(11) NOT NULL,
    `dntieuthu` int(11) GENERATED ALWAYS AS (`chisomoi` - `chisocu`) STORED,
    `thang` int(11) NOT NULL,
    `nam` int(11) NOT NULL,
    `ngaynhap` date NOT NULL DEFAULT CURRENT_DATE,
    `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`maCSD`),
    UNIQUE KEY `uniq_chisodien_month` (`maKH`, `thang`, `nam`),
    KEY `maKH` (`maKH`),
    KEY `idx_thang_nam` (`thang`, `nam`),
    CONSTRAINT `chisodien_ibfk_1` FOREIGN KEY (`maKH`) REFERENCES `taikhoan` (`maKH`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `blockchain_chisodien` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `maCSD` int(11) NOT NULL,
    `maKH` varchar(15) NOT NULL,
    `chisocu` int(11) NOT NULL,
    `chisomoi` int(11) NOT NULL,
    `thang` int(11) NOT NULL,
    `nam` int(11) NOT NULL,
    `created_at` datetime NOT NULL,
    `previous_hash` char(64) DEFAULT NULL,
    `current_hash` char(64) NOT NULL,
    `signature` text NOT NULL,
    `signer` varchar(64) NOT NULL,
    PRIMARY KEY (`id`),
    KEY `maKH` (`maKH`),
    CONSTRAINT `blockchain_chisodien_ibfk_1` FOREIGN KEY (`maCSD`) REFERENCES `chisodien` (`maCSD`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `blockchain_batch` (
    `block_id` int(11) NOT NULL AUTO_INCREMENT,
    `thang` int(11) NOT NULL,
    `nam` int(11) NOT NULL,
    `batch_index` int(11) NOT NULL DEFAULT 1,
    `tong_ban_ghi` int(11) NOT NULL,
    `merkle_root` char(64) NOT NULL,
    `previous_hash` char(64) DEFAULT NULL,
    `current_hash` char(64) NOT NULL,
    `signature` text NOT NULL,
    `signer` varchar(64) NOT NULL,
    `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`block_id`),
    UNIQUE KEY `uniq_block_month` (`thang`, `nam`, `batch_index`),
    KEY `idx_month_year` (`thang`, `nam`),
    KEY `idx_block_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `hoadon` (
    `maHD` int(11) NOT NULL AUTO_INCREMENT,
    `maKH` varchar(15) NOT NULL,
    `maCSD` int(11) NOT NULL,
    `sodiendatieuthu` int(11) NOT NULL,
    `tongtien` decimal(12,2) DEFAULT NULL,
    `trangthai` enum('chuathanhtoan','dathanhtoan') DEFAULT 'chuathanhtoan',
    `hansudung` date DEFAULT NULL,
    `ngaytao` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`maHD`),
    KEY `maKH` (`maKH`),
    KEY `maCSD` (`maCSD`),
    CONSTRAINT `hoadon_ibfk_1` FOREIGN KEY (`maKH`) REFERENCES `taikhoan` (`maKH`),
    CONSTRAINT `hoadon_ibfk_2` FOREIGN KEY (`maCSD`) REFERENCES `chisodien` (`maCSD`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `thanhtoan` (
    `maTT` int(11) NOT NULL AUTO_INCREMENT,
    `maKH` varchar(15) NOT NULL,
    `maHD` int(11) NOT NULL,
    `phuongthuc` varchar(50) DEFAULT 'Chua thanh toan',
    `sotien` decimal(12,2) NOT NULL,
    `trangthai` enum('chuathanhtoan','dathanhtoan') DEFAULT 'chuathanhtoan',
    `ngaytao` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `ngaythanhtoan` timestamp NULL DEFAULT NULL,
    PRIMARY KEY (`maTT`),
    KEY `maKH` (`maKH`),
    KEY `maHD` (`maHD`),
    CONSTRAINT `thanhtoan_ibfk_1` FOREIGN KEY (`maKH`) REFERENCES `taikhoan` (`maKH`),
    CONSTRAINT `thanhtoan_ibfk_2` FOREIGN KEY (`maHD`) REFERENCES `hoadon` (`maHD`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `thongketiendien` (
    `maTK` int(11) NOT NULL AUTO_INCREMENT,
    `maKH` varchar(15) NOT NULL,
    `maCSD` int(11) NOT NULL,
    `bacthang` int(11) NOT NULL,
    `dongia` decimal(10,2) NOT NULL,
    `sanluong` int(11) NOT NULL,
    `thanhtien` decimal(12,2) NOT NULL,
    PRIMARY KEY (`maTK`),
    KEY `maKH` (`maKH`),
    KEY `maCSD` (`maCSD`),
    CONSTRAINT `thongketiendien_ibfk_1` FOREIGN KEY (`maKH`) REFERENCES `taikhoan` (`maKH`),
    CONSTRAINT `thongketiendien_ibfk_2` FOREIGN KEY (`maCSD`) REFERENCES `chisodien` (`maCSD`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DROP FUNCTION IF EXISTS `tinh_tien_dien`;
DELIMITER $$
CREATE FUNCTION `tinh_tien_dien` (`kwh` INT) RETURNS DECIMAL(12,2) DETERMINISTIC BEGIN
    DECLARE done INT DEFAULT 0;
    DECLARE v_sanluong INT;
    DECLARE v_dongia DECIMAL(10,2);
    DECLARE v_dongia5 DECIMAL(10,2) DEFAULT 0;
    DECLARE conlai INT DEFAULT kwh;
    DECLARE tong DECIMAL(12,2) DEFAULT 0;
    DECLARE cur CURSOR FOR
        SELECT sanluong, dongia
        FROM giadien
        WHERE bac <= 4
        ORDER BY bac ASC;
    DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = 1;

    SELECT dongia INTO v_dongia5
    FROM giadien
    WHERE bac = 5
    ORDER BY ngayapdung DESC
    LIMIT 1;

    OPEN cur;
    read_loop: LOOP
        FETCH cur INTO v_sanluong, v_dongia;
        IF done = 1 THEN
            LEAVE read_loop;
        END IF;
        IF conlai <= 0 THEN
            LEAVE read_loop;
        END IF;
        IF conlai >= v_sanluong THEN
            SET tong = tong + (v_sanluong * v_dongia);
            SET conlai = conlai - v_sanluong;
        ELSE
            SET tong = tong + (conlai * v_dongia);
            SET conlai = 0;
        END IF;
    END LOOP;

    CLOSE cur;

    IF conlai > 0 THEN
        SET tong = tong + (conlai * v_dongia5);
    END IF;

    RETURN tong;
END$$
DELIMITER ;

DELIMITER $$
CREATE TRIGGER `trg_tao_hoadon` AFTER INSERT ON `chisodien` FOR EACH ROW BEGIN
    INSERT INTO hoadon (
        maKH,
        maCSD,
        sodiendatieuthu,
        tongtien,
        ngaytao,
        hansudung
    ) VALUES (
        NEW.maKH,
        NEW.maCSD,
        NEW.dntieuthu,
        tinh_tien_dien(NEW.dntieuthu),
        CONCAT(NEW.nam, '-', LPAD(NEW.thang, 2, '0'), '-15 12:00:00'),
        DATE_ADD(CONCAT(NEW.nam, '-', LPAD(NEW.thang, 2, '0'), '-15'), INTERVAL 1 MONTH)
    );
END$$
DELIMITER ;

DELIMITER $$
CREATE TRIGGER `trg_thongke_tiendien` AFTER INSERT ON `chisodien` FOR EACH ROW BEGIN
    DECLARE conlai INT;
    DECLARE sl_bac INT;
    DECLARE done INT DEFAULT 0;
    DECLARE v_bac INT;
    DECLARE v_sanluong INT;
    DECLARE v_dongia DECIMAL(10,2);
    DECLARE v_dongia5 DECIMAL(10,2) DEFAULT 0;
    DECLARE cur CURSOR FOR
        SELECT bac, sanluong, dongia
        FROM giadien
        WHERE bac <= 4
        ORDER BY bac ASC;
    DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = 1;

    SELECT dongia INTO v_dongia5
    FROM giadien
    WHERE bac = 5
    ORDER BY ngayapdung DESC
    LIMIT 1;

    SET conlai = NEW.dntieuthu;
    OPEN cur;

    read_loop: LOOP
        FETCH cur INTO v_bac, v_sanluong, v_dongia;
        IF done = 1 THEN
            LEAVE read_loop;
        END IF;
        IF conlai <= 0 THEN
            LEAVE read_loop;
        END IF;
        IF conlai >= v_sanluong THEN
            SET sl_bac = v_sanluong;
        ELSE
            SET sl_bac = conlai;
        END IF;

        INSERT INTO thongketiendien (
            maKH,
            maCSD,
            bacthang,
            dongia,
            sanluong,
            thanhtien
        ) VALUES (
            NEW.maKH,
            NEW.maCSD,
            v_bac,
            v_dongia,
            sl_bac,
            sl_bac * v_dongia
        );

        SET conlai = conlai - sl_bac;
    END LOOP;

    CLOSE cur;

    IF conlai > 0 THEN
        INSERT INTO thongketiendien (
            maKH,
            maCSD,
            bacthang,
            dongia,
            sanluong,
            thanhtien
        ) VALUES (
            NEW.maKH,
            NEW.maCSD,
            5,
            v_dongia5,
            conlai,
            conlai * v_dongia5
        );
    END IF;
END$$
DELIMITER ;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `view_hoadon_thanhtoan` AS
SELECT
    h.maHD AS maHD,
    h.maKH AS maKH,
    h.maCSD AS maCSD,
    h.sodiendatieuthu AS sodiendatieuthu,
    h.tongtien AS tongtien,
    h.trangthai AS trangthai_hoadon,
    h.hansudung AS hansudung,
    tt.maTT AS maTT,
    tt.sotien AS sotien,
    tt.phuongthuc AS phuongthuc,
    tt.trangthai AS trangthai_thanhtoan,
    tt.ngaythanhtoan AS ngaythanhtoan
FROM hoadon h
LEFT JOIN thanhtoan tt ON h.maHD = tt.maHD;

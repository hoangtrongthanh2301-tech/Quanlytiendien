-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Máy chủ: 127.0.0.1
-- Thời gian đã tạo: Th6 05, 2026 lúc 06:42 PM
-- Phiên bản máy phục vụ: 10.4.32-MariaDB
-- Phiên bản PHP: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Cơ sở dữ liệu: `qltiendien`
--

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `blockchain_chisodien`
--

CREATE TABLE `blockchain_chisodien` (
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
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `chisodien`
--

CREATE TABLE `chisodien` (
  `maCSD` int(11) NOT NULL,
  `maKH` varchar(15) NOT NULL,
  `chisocu` int(11) NOT NULL,
  `chisomoi` int(11) NOT NULL,
  `dntieuthu` int(11) GENERATED ALWAYS AS (`chisomoi` - `chisocu`) STORED,
  `thang` int(11) NOT NULL,
  `nam` int(11) NOT NULL,
  `ngaynhap` date DEFAULT curdate(),
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
--




-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `giadien`
--

CREATE TABLE `giadien` (
  `maGD` int(11) NOT NULL,
  `bac` int(11) NOT NULL,
  `sanluong` int(11) NOT NULL,
  `dongia` decimal(10,2) NOT NULL,
  `ngayapdung` date NOT NULL,
  `ngaytao` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Các hàm
--
DROP FUNCTION IF EXISTS `tinh_tien_dien`;
DELIMITER $$
CREATE DEFINER=`root`@`localhost` FUNCTION `tinh_tien_dien` (`kwh` INT) RETURNS DECIMAL(12,2) DETERMINISTIC BEGIN

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

    DECLARE CONTINUE HANDLER
    FOR NOT FOUND SET done = 1;

    SELECT dongia INTO v_dongia5
    FROM giadien
    WHERE bac = 5
    ORDER BY ngayapdung DESC
    LIMIT 1;

    OPEN cur;

    read_loop: LOOP

        FETCH cur
        INTO v_sanluong, v_dongia;

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

    -- Nếu còn lại sản lượng, tính theo giá bậc 5
    IF conlai > 0 THEN
        SET tong = tong + (conlai * v_dongia5);
    END IF;

    RETURN tong;

END$$

DELIMITER ;

--
-- Đang đổ dữ liệu cho bảng `giadien`
--



-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `hoadon`
--

CREATE TABLE `hoadon` (
  `maHD` int(11) NOT NULL,
  `maKH` varchar(15) NOT NULL,
  `maCSD` int(11) NOT NULL,
  `sodiendatieuthu` int(11) NOT NULL,
  `tongtien` decimal(12,2) DEFAULT NULL,
  `trangthai` enum('chuathanhtoan','dathanhtoan') DEFAULT 'chuathanhtoan',
  `hansudung` date DEFAULT NULL,
  `ngaytao` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Bẫy `hoadon`
--
DELIMITER $$
CREATE TRIGGER `trg_tao_thanhtoan` AFTER INSERT ON `hoadon` FOR EACH ROW BEGIN

    INSERT INTO thanhtoan(
        maKH,
        maHD,
        sotien
    )
    VALUES (
        NEW.maKH,
        NEW.maHD,
        NEW.tongtien
    );

END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `thanhtoan`
--

CREATE TABLE `thanhtoan` (
  `maTT` int(11) NOT NULL,
  `maKH` varchar(15) NOT NULL,
  `maHD` int(11) NOT NULL,
  `phuongthuc` varchar(50) DEFAULT 'Chua thanh toan',
  `sotien` decimal(12,2) NOT NULL,
  `trangthai` enum('chuathanhtoan','dathanhtoan') DEFAULT 'chuathanhtoan',
  `ngaytao` timestamp NOT NULL DEFAULT current_timestamp(),
  `ngaythanhtoan` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Bẫy `thanhtoan`
--
DELIMITER $$
CREATE TRIGGER `trg_capnhat_hoadon` AFTER UPDATE ON `thanhtoan` FOR EACH ROW BEGIN

    IF NEW.trangthai = 'dathanhtoan' THEN

        UPDATE hoadon
        SET trangthai = 'dathanhtoan'
        WHERE maHD = NEW.maHD;

    END IF;

END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `trg_capnhat_thanhtoan` BEFORE UPDATE ON `thanhtoan` FOR EACH ROW BEGIN

    IF NEW.trangthai = 'dathanhtoan' THEN
        SET NEW.ngaythanhtoan = CURRENT_TIMESTAMP;
    END IF;

END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `taikhoan`
--

CREATE TABLE `taikhoan` (
  `maKH` varchar(15) NOT NULL,
  `hovaten` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `sodienthoai` varchar(15) NOT NULL,
  `matkhau` varchar(255) NOT NULL,
  `quyen` enum('admin','khachhang') DEFAULT 'khachhang',
  `trangthai` enum('hoatdong','khoa') DEFAULT 'hoatdong',
  `diachi` text DEFAULT NULL,
  `ngaytao` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Đang đổ dữ liệu cho bảng `taikhoan`
--


-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `thongketiendien`
--

CREATE TABLE `thongketiendien` (
  `maTK` int(11) NOT NULL,
  `maKH` varchar(15) NOT NULL,
  `maCSD` int(11) NOT NULL,
  `bacthang` int(11) NOT NULL,
  `dongia` decimal(10,2) NOT NULL,
  `sanluong` int(11) NOT NULL,
  `thanhtien` decimal(12,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

-- Bẫy `chisodien`
-- Tự động tạo hóa đơn, thống kê tiền điện, thanh toán, blockchain khi có bản ghi chisodien mới
--
DELIMITER $$
CREATE TRIGGER `trg_tao_hoadon` AFTER INSERT ON `chisodien` FOR EACH ROW BEGIN

    INSERT INTO hoadon (
        maKH,
        maCSD,
        sodiendatieuthu,
        tongtien,
        ngaytao,
        hansudung
    )
    VALUES (
        NEW.maKH,
        NEW.maCSD,
        NEW.dntieuthu,
        tinh_tien_dien(NEW.dntieuthu),
        CONCAT(NEW.nam, '-', LPAD(NEW.thang, 2, '0'), '-15 12:00:00'),
        DATE_ADD(CONCAT(NEW.nam, '-', LPAD(NEW.thang, 2, '0'), '-15'), INTERVAL 1 MONTH)
    );

END
$$
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

    DECLARE CONTINUE HANDLER
    FOR NOT FOUND SET done = 1;

    SELECT dongia INTO v_dongia5
    FROM giadien
    WHERE bac = 5
    ORDER BY ngayapdung DESC
    LIMIT 1;

    SET conlai = NEW.dntieuthu;

    OPEN cur;

    read_loop: LOOP

        FETCH cur
        INTO v_bac, v_sanluong, v_dongia;

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

        INSERT INTO thongketiendien(
            maKH,
            maCSD,
            bacthang,
            dongia,
            sanluong,
            thanhtien
        )
        VALUES (
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

    -- Nếu còn lại sản lượng, tính theo giá bậc 5
    IF conlai > 0 THEN
        INSERT INTO thongketiendien(
            maKH,
            maCSD,
            bacthang,
            dongia,
            sanluong,
            thanhtien
        )
        VALUES (
            NEW.maKH,
            NEW.maCSD,
            5,
            v_dongia5,
            conlai,
            conlai * v_dongia5
        );
    END IF;

END
$$
DELIMITER ;

-- --------------------------------------------------------

-- Blockchain record generation for new chisodien rows is handled by application logic.
-- Khi admin lưu chỉ số điện tháng mới, PHP sẽ tạo block mới trong bảng blockchain_chisodien.
-- Việc chỉnh sửa chỉ số cũ sẽ không tạo block mới; hệ thống chỉ kiểm tra băm so với giá trị đã lưu trước đó.

-- --------------------------------------------------------

--
-- Cấu trúc đóng vai cho view `view_hoadon_thanhtoan`
-- (See below for the actual view)
--
CREATE TABLE `view_hoadon_thanhtoan` (
`maHD` int(11)
,`maKH` varchar(15)
,`maCSD` int(11)
,`sodiendatieuthu` int(11)
,`tongtien` decimal(12,2)
,`trangthai_hoadon` enum('chuathanhtoan','dathanhtoan')
,`hansudung` date
,`maTT` int(11)
,`sotien` decimal(12,2)
,`phuongthuc` varchar(50)
,`trangthai_thanhtoan` enum('chuathanhtoan','dathanhtoan')
,`ngaythanhtoan` timestamp
);

-- --------------------------------------------------------

--
-- Cấu trúc cho view `view_hoadon_thanhtoan`
--
DROP TABLE IF EXISTS `view_hoadon_thanhtoan`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `view_hoadon_thanhtoan`  AS SELECT `h`.`maHD` AS `maHD`, `h`.`maKH` AS `maKH`, `h`.`maCSD` AS `maCSD`, `h`.`sodiendatieuthu` AS `sodiendatieuthu`, `h`.`tongtien` AS `tongtien`, `h`.`trangthai` AS `trangthai_hoadon`, `h`.`hansudung` AS `hansudung`, `tt`.`maTT` AS `maTT`, `tt`.`sotien` AS `sotien`, `tt`.`phuongthuc` AS `phuongthuc`, `tt`.`trangthai` AS `trangthai_thanhtoan`, `tt`.`ngaythanhtoan` AS `ngaythanhtoan` FROM (`hoadon` `h` left join `thanhtoan` `tt` on(`h`.`maHD` = `tt`.`maHD`)) ;

--
-- Chỉ mục cho các bảng đã đổ
--

--
-- Chỉ mục cho bảng `blockchain_chisodien`
--
ALTER TABLE `blockchain_chisodien`
  ADD KEY `maKH` (`maKH`);

--
-- Chỉ mục cho bảng `chisodien`
--
ALTER TABLE `chisodien`
  ADD PRIMARY KEY (`maCSD`),
  ADD KEY `maKH` (`maKH`);

--
-- Chỉ mục cho bảng `giadien`
--
ALTER TABLE `giadien`
  ADD PRIMARY KEY (`maGD`);

--
-- Chỉ mục cho bảng `hoadon`
--
ALTER TABLE `hoadon`
  ADD PRIMARY KEY (`maHD`),
  ADD KEY `maKH` (`maKH`),
  ADD KEY `maCSD` (`maCSD`);

--
-- Chỉ mục cho bảng `taikhoan`
--
ALTER TABLE `taikhoan`
  ADD PRIMARY KEY (`maKH`),
  ADD UNIQUE KEY `email` (`email`),
  ADD UNIQUE KEY `sodienthoai` (`sodienthoai`);

--
-- Chỉ mục cho bảng `thanhtoan`
--
ALTER TABLE `thanhtoan`
  ADD PRIMARY KEY (`maTT`),
  ADD KEY `maKH` (`maKH`),
  ADD KEY `maHD` (`maHD`);

--
-- Chỉ mục cho bảng `thongketiendien`
--
ALTER TABLE `thongketiendien`
  ADD PRIMARY KEY (`maTK`),
  ADD KEY `maKH` (`maKH`),
  ADD KEY `maCSD` (`maCSD`);

--
-- AUTO_INCREMENT cho các bảng đã đổ
--

--
-- AUTO_INCREMENT cho bảng `chisodien`
--
ALTER TABLE `chisodien`
  MODIFY `maCSD` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=69;

--
-- AUTO_INCREMENT cho bảng `giadien`
--
ALTER TABLE `giadien`
  MODIFY `maGD` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT cho bảng `hoadon`
--
ALTER TABLE `hoadon`
  MODIFY `maHD` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=66;

--
-- AUTO_INCREMENT cho bảng `thanhtoan`
--
ALTER TABLE `thanhtoan`
  MODIFY `maTT` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=66;

--
-- AUTO_INCREMENT cho bảng `thongketiendien`
--
ALTER TABLE `thongketiendien`
  MODIFY `maTK` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=169;

--
-- Các ràng buộc cho các bảng đã đổ
--

--
-- Các ràng buộc cho bảng `blockchain_chisodien`
--
ALTER TABLE `blockchain_chisodien`
  ADD CONSTRAINT `blockchain_chisodien_ibfk_1` FOREIGN KEY (`maCSD`) REFERENCES `chisodien` (`maCSD`);

--
-- Các ràng buộc cho bảng `chisodien`
--
ALTER TABLE `chisodien`
  ADD CONSTRAINT `chisodien_ibfk_1` FOREIGN KEY (`maKH`) REFERENCES `taikhoan` (`maKH`);

--
-- Các ràng buộc cho bảng `hoadon`
--
ALTER TABLE `hoadon`
  ADD CONSTRAINT `hoadon_ibfk_1` FOREIGN KEY (`maKH`) REFERENCES `taikhoan` (`maKH`),
  ADD CONSTRAINT `hoadon_ibfk_2` FOREIGN KEY (`maCSD`) REFERENCES `chisodien` (`maCSD`);

--
-- Các ràng buộc cho bảng `thanhtoan`
--
ALTER TABLE `thanhtoan`
  ADD CONSTRAINT `thanhtoan_ibfk_1` FOREIGN KEY (`maKH`) REFERENCES `taikhoan` (`maKH`),
  ADD CONSTRAINT `thanhtoan_ibfk_2` FOREIGN KEY (`maHD`) REFERENCES `hoadon` (`maHD`);

--
-- Các ràng buộc cho bảng `thongketiendien`
--
ALTER TABLE `thongketiendien`
  ADD CONSTRAINT `thongketiendien_ibfk_1` FOREIGN KEY (`maKH`) REFERENCES `taikhoan` (`maKH`),
  ADD CONSTRAINT `thongketiendien_ibfk_2` FOREIGN KEY (`maCSD`) REFERENCES `chisodien` (`maCSD`);
COMMIT;



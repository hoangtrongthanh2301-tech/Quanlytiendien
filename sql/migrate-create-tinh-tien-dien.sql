-- Restore the tariff calculation function required by trg_tao_hoadon.
DROP FUNCTION IF EXISTS tinh_tien_dien;

DELIMITER $$
CREATE FUNCTION tinh_tien_dien(kwh INT)
RETURNS DECIMAL(12,2)
DETERMINISTIC
BEGIN
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

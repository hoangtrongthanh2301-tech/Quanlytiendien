from __future__ import annotations

import csv
import io
import json
import re
import unicodedata
import urllib.request
from pathlib import Path

ROOT = Path(r"D:\Đồ án quản lý tiền điện dự phòng\Dữ liệu dữ phòng")
OUT = Path(__file__).resolve().parents[1] / "sql"
TESSERACT = r"C:\Program Files\Tesseract-OCR\tesseract.exe"
TESSDATA = Path.home() / "AppData" / "Local" / "tesseract-tessdata"

PROVINCES = [
    ("01", "Thành phố Hà Nội", "PD"), ("04", "Tỉnh Cao Bằng", "PA"),
    ("08", "Tỉnh Tuyên Quang", "PA"), ("11", "Tỉnh Điện Biên", "PA"),
    ("12", "Tỉnh Lai Châu", "PA"), ("14", "Tỉnh Sơn La", "PA"),
    ("15", "Tỉnh Lào Cai", "PA"), ("19", "Tỉnh Thái Nguyên", "PA"),
    ("20", "Tỉnh Lạng Sơn", "PA"), ("22", "Tỉnh Quảng Ninh", "PA"),
    ("24", "Tỉnh Bắc Ninh", "PA"), ("25", "Tỉnh Phú Thọ", "PA"),
    ("31", "Thành phố Hải Phòng", "PH"), ("33", "Tỉnh Hưng Yên", "PA"),
    ("37", "Tỉnh Ninh Bình", "PA"), ("38", "Tỉnh Thanh Hóa", "PC"),
    ("40", "Tỉnh Nghệ An", "PC"), ("42", "Tỉnh Hà Tĩnh", "PC"),
    ("44", "Tỉnh Quảng Trị", "PC"), ("46", "Thành phố Huế", "PC"),
    ("48", "Thành phố Đà Nẵng", "PC"), ("51", "Tỉnh Quảng Ngãi", "PC"),
    ("52", "Tỉnh Gia Lai", "PC"), ("56", "Tỉnh Khánh Hòa", "PC"),
    ("66", "Tỉnh Đắk Lắk", "PC"), ("68", "Tỉnh Lâm Đồng", "PC"),
    ("75", "Tỉnh Đồng Nai", "PB"), ("79", "Thành phố Hồ Chí Minh", "PE"),
    ("80", "Tỉnh Tây Ninh", "PB"), ("82", "Tỉnh Đồng Tháp", "PB"),
    ("86", "Tỉnh Vĩnh Long", "PB"), ("91", "Tỉnh An Giang", "PB"),
    ("92", "Thành phố Cần Thơ", "PB"), ("96", "Tỉnh Cà Mau", "PB"),
]


def plain(value: str) -> str:
    return "".join(c for c in unicodedata.normalize("NFD", value) if unicodedata.category(c) != "Mn").upper()


PROVINCE_KEYS = [(code, plain(name), name, power) for code, name, power in PROVINCES]


def province_from_text(text: str):
    normalized = plain(text)
    for code, key, name, power in PROVINCE_KEYS:
        if key in normalized:
            return code, name, power
    return None


def ocr_page(page) -> str:
    pixmap = page.get_pixmap(dpi=220, colorspace=fitz.csGRAY, alpha=False)
    image = Image.open(io.BytesIO(pixmap.tobytes("png")))
    image = ImageOps.autocontrast(image)
    image = ImageEnhance.Contrast(image).enhance(1.7)
    pixels = image.load()
    width, height = image.size
    horizontal = []
    for y in range(height):
        dark = sum(1 for x in range(width) if pixels[x, y] < 100)
        if dark > width * 0.28:
            horizontal.append(y)
    vertical = []
    for x in range(width):
        dark = sum(1 for y in range(height) if pixels[x, y] < 100)
        if dark > height * 0.28:
            vertical.append(x)
    for y in horizontal:
        for offset in (-1, 0, 1):
            if 0 <= y + offset < height:
                for x in range(width):
                    pixels[x, y + offset] = 255
    for x in vertical:
        for offset in (-1, 0, 1):
            if 0 <= x + offset < width:
                for y in range(height):
                    pixels[x + offset, y] = 255
    return pytesseract.image_to_string(image, lang="vie+eng", config="--psm 4")


def rows_from_text(text: str):
    rows = []
    for line in text.splitlines():
        match = re.search(r"(?<!\d)(\d{5})(?!\d)\s+(.+?)\s*$", line)
        if not match:
            continue
        code, name = match.groups()
        name = re.sub(r"\s+", " ", name).strip(" .-_")
        if name and any(name.startswith(prefix) for prefix in ("Xã", "Xa", "Phường", "Phuong", "Đặc khu", "Dac khu")):
            rows.append((code, name))
    return rows


def sql_quote(value: str) -> str:
    return "'" + value.replace("\\", "\\\\").replace("'", "\\'") + "'"


def main() -> None:
    source = urllib.request.urlopen("https://provinces.open-api.vn/api/v2/w/?depth=1")
    wards = json.loads(source.read().decode("utf-8"))
    province_map = {int(code): (name, power) for code, name, power in PROVINCES for _ in [0]}
    locations = []
    for ward in wards:
        province_code = int(ward["province_code"])
        name, power = province_map[province_code]
        locations.append((f"{province_code:02d}", name, power, f"{int(ward['code']):05d}", ward["name"], "api"))
    locations.sort(key=lambda row: (row[0], row[3]))

    OUT.mkdir(exist_ok=True)
    csv_path = OUT / "don_vi_hanh_chinh_2025.csv"
    with csv_path.open("w", newline="", encoding="utf-8-sig") as handle:
        writer = csv.writer(handle)
        writer.writerow(["ma_tinh", "ten_tinh", "ma_dien_luc", "ma_xa", "ten_xa", "trang_ocr"])
        writer.writerows(locations)

    sql_path = OUT / "seed_don_vi_hanh_chinh_2025.sql"
    with sql_path.open("w", encoding="utf-8") as handle:
        handle.write("USE `qltiendien`;\nSET NAMES utf8mb4;\n\n")
        handle.write("CREATE TABLE IF NOT EXISTS `don_vi_hanh_chinh` (\n")
        handle.write("  `ma_xa` char(5) NOT NULL, `ma_tinh` char(2) NOT NULL,\n")
        handle.write("  `ten_xa` varchar(150) NOT NULL, `ten_tinh` varchar(100) NOT NULL,\n")
        handle.write("  `ma_dien_luc` char(2) NOT NULL, PRIMARY KEY (`ma_xa`, `ma_tinh`),\n")
        handle.write("  KEY `idx_dvhc_tinh` (`ma_tinh`)\n) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;\n\n")
        handle.write("INSERT INTO `don_vi_hanh_chinh` (`ma_xa`,`ma_tinh`,`ten_xa`,`ten_tinh`,`ma_dien_luc`) VALUES\n")
        values = ["(%s,%s,%s,%s,%s)" % tuple(sql_quote(str(value)) for value in row[3:5] + row[0:3]) for row in locations]
        handle.write(",\n".join(values) + "\nON DUPLICATE KEY UPDATE `ten_xa`=VALUES(`ten_xa`), `ten_tinh`=VALUES(`ten_tinh`), `ma_dien_luc`=VALUES(`ma_dien_luc`);\n")

    report_path = OUT / "don_vi_hanh_chinh_2025_report.txt"
    with report_path.open("w", encoding="utf-8") as handle:
        handle.write(f"Location rows: {len(locations)}\n")
        for code, _, _ in PROVINCES:
            count = sum(1 for row in locations if row[0] == code)
            handle.write(f"{code}: {count}\n")


if __name__ == "__main__":
    main()
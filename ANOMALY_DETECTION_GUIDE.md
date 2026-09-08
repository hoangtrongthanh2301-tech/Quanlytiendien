# Hướng dẫn Anomaly Detection - Phát hiện khách hàng tiêu thụ bất thường

## Tổng quan

Hệ thống đã được tích hợp 2 cách để phát hiện khách hàng có hành vi tiêu thụ điện bất thường:

1. **SQL-based Detection** (Nhanh, real-time): Sử dụng z-score + percent change trong PHP
2. **Spark Pipeline** (Sâu, batch): Sử dụng seasonal decomposition + Isolation Forest

---

## Cách 1: SQL-based Detection (Trong Dashboard)

### Vị trí
- **Trang Admin** → Menu Sidebar → Dashboard → Điện tiêu thụ
- Hoặc: Phần "Insight & Dự báo" → Tab "Phân tích nâng cao"

### Giao diện
- **Bảng Khách hàng tiêu thụ bất thường** hiển thị các chỉ số:
  - **Mã KH**: Mã khách hàng
  - **Họ và tên**: Tên khách hàng
  - **Tháng gần nhất (kWh)**: Tiêu thụ tháng cuối
  - **Trung bình (kWh)**: Tiêu thụ trung bình (6 tháng)
  - **Z-score**: Độ lệch chuẩn từ trung bình
  - **% Thay đổi**: Thay đổi so với tháng trước
  - **Mức độ**: Badge màu sắc
    - 🟡 **Chú ý** (Yellow): Z-score 2.0-2.5 hoặc % change 100-300%
    - 🟠 **Cảnh báo** (Orange): Z-score 2.5-3.5 hoặc % change 300-500%
    - 🔴 **Cảnh báo cao** (Red): Z-score ≥3.5 hoặc % change ≥500%

### Thống kê nhanh
- **Số khách bất thường**: Tổng số lượng
- **Mức cảnh báo cao**: Số khách ở mức RED
- **Mức chú ý**: Số khách ở mức WARNING

### Tính năng
- **Xem chi tiết**: Hiển thị/ẩn bảng anomalies chi tiết
- **Tải lại phân tích**: Gọi API để refresh dữ liệu (mặc định lookback 6 tháng, top 20 customers)

### API Endpoint
```bash
GET /admin-stats.php?type=anomalous_customers&months=6&z=2.5&limit=20

# Parameters:
# - months: Số tháng tìm kiếm lịch sử (mặc định 6)
# - z: Ngưỡng z-score (mặc định 2.5)
# - limit: Số khách trả về tối đa (mặc định 20)
```

---

## Cách 2: Spark Pipeline (Batch Processing)

### Yêu cầu
- **Java + Spark 3.x** (hoặc chạy trên cluster Spark)
- **Python 3.7+** với packages: `pandas`, `numpy`, `statsmodels`, `scikit-learn`, `pyspark`
- **MySQL JDBC driver** (mysql-connector-java.jar)

### Cài đặt

#### Step 1: Cài Python packages
```bash
cd analysis
python -m venv venv
source venv/bin/activate  # hoặc: venv\Scripts\activate trên Windows
pip install -r requirements.txt
```

#### Step 2: Download MySQL JDBC driver
```bash
# Windows/Linux/macOS
curl -o /path/to/mysql-connector-java.jar https://dev.mysql.com/get/Downloads/Connector-J/mysql-connector-java-8.0.33.jar
```

### Chạy Pipeline

```bash
spark-submit \
  --master local[4] \
  --jars /path/to/mysql-connector-java.jar \
  analysis/spark_pipeline.py \
  --jdbc-url "jdbc:mysql://localhost/quanlytiendien?useSSL=false&serverTimezone=UTC" \
  --user root \
  --password "your_password" \
  --months 12 \
  --output-path analysis/output/anomalies.csv
```

#### Giải thích parameters
- `--master local[4]`: Chạy trên 4 cores (thay local[*] cho tất cả cores)
- `--jdbc-url`: Kết nối MySQL (adjust host/port nếu cần)
- `--user`, `--password`: Thông tin đăng nhập MySQL
- `--months`: Lookback window (12 = 12 tháng)
- `--output-path`: Đường dẫn lưu kết quả CSV

### Kết quả
Dữ liệu được lưu ở: `analysis/output/anomalies.csv`

Cột dữ liệu:
```
maKH,hovaten,last_kwh,mean_kwh,std_kwh,is_anomaly,score,pct_change,months_count
KH001,Nguyễn Văn A,150.5,120.2,25.3,true,-1.234,25.5,12
```

### Serve kết quả CSV qua API PHP
File `admin-anomalies.php` tự động đọc CSV và serve as JSON:

```bash
curl http://localhost/quanlytiendien/admin-anomalies.php
# Response: { "success": true, "anomalies": [...] }
```

---

## Tùy chỉnh & Tinh chỉnh

### Ngưỡng Z-score
- **2.0**: Nhạy cảm cao (catch more anomalies)
- **2.5**: Cân bằng (mặc định)
- **3.0**: Khắt khe (chỉ anomaly rõ ràng)

### Seasonal Decomposition
Spark pipeline tự động lựa chọn `period` dựa trên dữ liệu:
- Nếu có ≥12 months: period = 12 (yearly seasonality)
- Nếu < 12 months: period = n/2 (fallback)

### Tuning IsolationForest
Trong `spark_pipeline.py`, line ~70:
```python
iso = IsolationForest(n_estimators=100, contamination=0.01, random_state=42)
# - n_estimators: Số cây (tăng = chính xác hơn nhưng chậm)
# - contamination: % dữ liệu dự kiến là anomaly (0.01 = 1%)
# - random_state: Seed cho reproducibility
```

---

## Trường hợp sử dụng

1. **Phát hiện lưu lượng điện bất thường**: Khách hàng tiêu thụ đột ngột nhiều hơn bình thường
2. **Cảnh báo mất mát dữ liệu**: Anomaly trong lịch sử có thể chỉ sai số nhập liệu
3. **Phân tích hành vi**: Nhận diện các pattern thay đổi (nâng cấp thiết bị, thay đổi lối sống)
4. **Tối ưu hóa giá**: Sử dụng để tạo dynamic pricing hoặc incentive programs

---

## Troubleshooting

### Dashboard không tải anomalies
1. Kiểm tra console browser (F12) → xem lỗi API
2. Kiểm tra `admin-stats.php` chạy ok: `curl "http://localhost/quanlytiendien/admin-stats.php?type=anomalous_customers"`
3. Đảm bảo `chisodien` table có ≥100 bản ghi

### Spark job fails
```bash
# Kiểm tra JDBC URL
spark-submit --master local --verbose analysis/spark_pipeline.py --jdbc-url "jdbc:mysql://..." ...

# Xem error log đầy đủ
export SPARK_LOCAL_IP=127.0.0.1
spark-submit ... 2>&1 | tee spark.log
```

### Không đủ memory
```bash
spark-submit --master local[2] --driver-memory 2g --executor-memory 2g ...
```

---

## Hiệu suất & Scalability

| Scenario | Giải pháp | Lợi ích |
|----------|----------|--------|
| < 10K customers, < 1M meter readings | SQL-based | Nhanh, real-time, no infra |
| 10K-100K customers | Spark local + batch job | Balance giữa chi phí & độ chính xác |
| > 100K customers | Spark cluster (YARN/K8s) | Distributed processing, horizontal scale |

---

## Tài liệu tham khảo

- **Seasonal Decompose**: https://www.statsmodels.org/stable/generated/statsmodels.tsa.seasonal.seasonal_decompose.html
- **Isolation Forest**: https://scikit-learn.org/stable/modules/generated/sklearn.ensemble.IsolationForest.html
- **Spark ApplyInPandas**: https://spark.apache.org/docs/latest/api/python/reference/pyspark.sql/api/pyspark.sql.GroupedData.applyInPandas.html
- **Z-score**: https://en.wikipedia.org/wiki/Standard_score

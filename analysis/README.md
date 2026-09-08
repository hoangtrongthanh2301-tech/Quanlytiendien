Spark pipeline for electricity consumption anomaly detection

Overview
- This script reads `chisodien` (meter readings) and `taikhoan` (customers) from MySQL via JDBC, aggregates monthly kWh per customer, applies seasonal decomposition and an Isolation Forest on residuals to detect anomalous recent consumption, and writes a CSV of anomalies.

Requirements
- Java + Spark (3.x)
- MySQL JDBC driver (mysql-connector-java.jar)
- Python packages (see `requirements.txt`). For local development use a virtualenv and `pip install -r requirements.txt`.

Running (local spark-submit)

1. Install Python deps (optional for driver-side pandas operations):

```bash
python -m venv venv
source venv/bin/activate  # or venv\Scripts\activate on Windows
pip install -r analysis/requirements.txt
```

2. Run with `spark-submit` (adjust paths):

```bash
spark-submit \
  --master local[4] \
  --jars /path/to/mysql-connector-java.jar \
  analysis/spark_pipeline.py \
  --jdbc-url "jdbc:mysql://localhost/quanlytiendien?useSSL=false&serverTimezone=UTC" \
  --user root \
  --password "" \
  --months 12 \
  --output-path analysis/output/anomalies.csv
```

Notes
- The script uses `applyInPandas` which requires Spark 3.x and pandas/pyarrow on the worker nodes.
- For large data (bigdata), deploy this script on a Spark cluster and configure JDBC partitioning for parallel reads (via `partitionColumn`, `lowerBound`, `upperBound`, `numPartitions`).
- Tuning: adjust IsolationForest `contamination` and seasonal `period` according to data (monthly/weekly).
- Post-processing: the generated CSV can be imported into a database or served via a simple PHP endpoint `admin-anomalies.php` (provided).

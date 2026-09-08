#!/usr/bin/env python3
import argparse
import os
import sys
from pyspark.sql import SparkSession
from pyspark.sql.functions import col, date_format, sum as _sum
from pyspark.sql.types import StructType, StructField, StringType, DoubleType, IntegerType, BooleanType
import pandas as pd
import numpy as np

# The per-group pandas function for anomaly detection
def detect_anomalies(pdf: pd.DataFrame) -> pd.DataFrame:
    # pdf columns: maKH, hovaten, ym, kwh
    try:
        pdf = pdf.sort_values('ym')
        series = pd.Series(pdf['kwh'].values, index=pd.to_datetime(pdf['ym'] + '-01'))
        # Fill missing months (reindex)
        idx = pd.date_range(series.index.min(), series.index.max(), freq='MS')
        series = series.reindex(idx, fill_value=0.0)
        n = len(series)
        if n < 2:
            return pd.DataFrame([], columns=['maKH','hovaten','last_kwh','mean_kwh','std_kwh','is_anomaly','score','months_count'])

        mean = float(series.mean())
        std = float(series.std())
        last = float(series.iloc[-1])
        prev = float(series.iloc[-2]) if n >= 2 else 0.0
        pct_change = 0.0 if prev == 0 else (last - prev) / prev * 100.0

        # Seasonal decomposition fallback: if too short, skip
        from statsmodels.tsa.seasonal import seasonal_decompose
        try:
            period = 12 if n >= 12 else max(2, n//2)
            res = seasonal_decompose(series, model='additive', period=period, two_sided=True, extrapolate_trend='freq')
            resid = res.resid.fillna(0.0)
        except Exception:
            resid = series - mean

        # Isolation Forest on residuals
        from sklearn.ensemble import IsolationForest
        iso = IsolationForest(n_estimators=100, contamination=0.01, random_state=42)
        X = resid.values.reshape(-1,1)
        try:
            iso.fit(X)
            preds = iso.predict(X)  # -1 anomaly, 1 normal
            scores = iso.decision_function(X)
            last_pred = int(preds[-1])
            last_score = float(scores[-1])
            is_anomaly = (last_pred == -1)
        except Exception:
            is_anomaly = False
            last_score = 0.0

        return pd.DataFrame([{
            'maKH': pdf['maKH'].iloc[0],
            'hovaten': pdf['hovaten'].iloc[0] if 'hovaten' in pdf.columns else '',
            'last_kwh': round(last, 3),
            'mean_kwh': round(mean, 3),
            'std_kwh': round(std, 3),
            'is_anomaly': bool(is_anomaly),
            'score': round(last_score, 6),
            'pct_change': round(pct_change, 2),
            'months_count': int(n)
        }])
    except Exception as e:
        return pd.DataFrame([], columns=['maKH','hovaten','last_kwh','mean_kwh','std_kwh','is_anomaly','score','months_count'])


def main():
    parser = argparse.ArgumentParser(description='Spark anomaly detection pipeline for electricity consumption')
    parser.add_argument('--jdbc-url', required=True, help='JDBC URL for MySQL, e.g. jdbc:mysql://localhost/quanlytiendien')
    parser.add_argument('--user', required=True)
    parser.add_argument('--password', required=True)
    parser.add_argument('--months', type=int, default=12, help='Lookback months')
    parser.add_argument('--output-path', default='analysis/output/anomalies.csv', help='Output CSV path (single file when possible)')
    parser.add_argument('--mysql-table-chisodien', default='chisodien')
    parser.add_argument('--mysql-table-taikhoan', default='taikhoan')
    args = parser.parse_args()

    spark = SparkSession.builder.appName('ConsumptionAnomalyDetection').getOrCreate()

    # Read meter readings from MySQL via JDBC. The MySQL JDBC driver must be provided to spark-submit via --jars
    query = f"(SELECT maKH, chisocu, chisomoi, ngaynhap FROM {args.mysql_table_chisodien} WHERE ngaynhap >= DATE_SUB(CURDATE(), INTERVAL {args.months} MONTH)) AS subq"
    df = spark.read.format('jdbc') \
        .option('url', args.jdbc_url) \
        .option('dbtable', query) \
        .option('user', args.user) \
        .option('password', args.password) \
        .option('fetchsize', '10000') \
        .load()

    # Compute kwh and ym
    df2 = df.withColumn('kwh', (col('chisomoi') - col('chisocu')).cast('double')) \
            .withColumn('ym', date_format(col('ngaynhap'), 'yyyy-MM')) \
            .select('maKH','ym','kwh')

    # Read customer names
    tquery = f"(SELECT maKH, hovaten FROM {args.mysql_table_taikhoan if hasattr(args, 'mysql_table_taikhoan') else args.mysql_table_taikhoan} WHERE quyen = 'nguoidung') as tsub"
    try:
        customers = spark.read.format('jdbc') \
            .option('url', args.jdbc_url) \
            .option('dbtable', f"(SELECT maKH, hovaten FROM {args.mysql_table_taikhoan} WHERE quyen = 'nguoidung') AS tsub") \
            .option('user', args.user) \
            .option('password', args.password) \
            .load()
    except Exception:
        # fallback: create empty names
        customers = None

    if customers is not None:
        df_join = df2.join(customers, on='maKH', how='left')
    else:
        df_join = df2.withColumn('hovaten', col('maKH'))

    # Aggregate to monthly kWh per customer
    df_agg = df_join.groupBy('maKH','hovaten','ym').agg(_sum('kwh').alias('kwh'))

    # Use applyInPandas to run pandas function per (maKH, hovaten)
    schema = StructType([
        StructField('maKH', StringType(), False),
        StructField('hovaten', StringType(), True),
        StructField('last_kwh', DoubleType(), True),
        StructField('mean_kwh', DoubleType(), True),
        StructField('std_kwh', DoubleType(), True),
        StructField('is_anomaly', BooleanType(), True),
        StructField('score', DoubleType(), True),
        StructField('pct_change', DoubleType(), True),
        StructField('months_count', IntegerType(), True)
    ])

    grouped = df_agg.groupBy('maKH','hovaten')
    try:
        result = grouped.applyInPandas(detect_anomalies, schema=schema)
    except Exception as e:
        # If applyInPandas not available, exit with helpful message
        print('Error: applyInPandas failed. Ensure you run on Spark 3.x with pandas and pyarrow installed. Exception:', e, file=sys.stderr)
        sys.exit(2)

    out_path = args.output_path
    # Ensure output directory exists
    out_dir = os.path.dirname(out_path)
    if out_dir and not os.path.exists(out_dir):
        os.makedirs(out_dir, exist_ok=True)

    # Write single CSV (coalesce to 1 partition)
    tmp_dir = out_path + '.tmp'
    result.coalesce(1).write.csv(tmp_dir, header=True, mode='overwrite')

    # Move generated part file to desired path (works when running locally)
    # This part assumes local filesystem access
    # Find the generated part file
    try:
        import glob
        part = glob.glob(os.path.join(tmp_dir, 'part-*.csv'))
        if part:
            os.replace(part[0], out_path)
            # remove tmp dir
            import shutil
            shutil.rmtree(tmp_dir)
            print('Wrote anomalies to', out_path)
        else:
            print('No part file found in', tmp_dir)
    except Exception as e:
        print('Warning: could not move part file automatically:', e)

    spark.stop()

if __name__ == '__main__':
    main()

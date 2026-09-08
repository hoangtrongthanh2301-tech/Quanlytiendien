<?php

namespace App\Services;

use App\Core\Database;

class AnalyticsService
{
    private Database $database;

    public function __construct(Database $database)
    {
        $this->database = $database;
    }

    /**
     * Get revenue analytics by month/year or custom range
     */
    public function getRevenueAnalytics(int $month = null, int $year = null): array
    {
        $conn = $this->database->getConnection();
        
        if ($month && $year) {
            $query = "
                SELECT 
                    DATE_FORMAT(h.ngaylapdon, '%Y-%m') as period,
                    COUNT(DISTINCT h.maHD) as total_invoices,
                    COUNT(DISTINCT c.maKH) as total_customers,
                    SUM(h.tongtien) as total_revenue,
                    AVG(h.tongtien) as avg_invoice,
                    SUM(c.chisomoi - c.chisocu) as total_kwh
                FROM hoadon h
                LEFT JOIN chisodien c ON h.maCSD = c.maCSD
                WHERE MONTH(h.ngaylapdon) = ? AND YEAR(h.ngaylapdon) = ?
                GROUP BY DATE_FORMAT(h.ngaylapdon, '%Y-%m')
            ";
            $stmt = $conn->prepare($query);
            $stmt->bind_param('ii', $month, $year);
        } else {
            $query = "
                SELECT 
                    DATE_FORMAT(h.ngaylapdon, '%Y-%m') as period,
                    COUNT(DISTINCT h.maHD) as total_invoices,
                    COUNT(DISTINCT c.maKH) as total_customers,
                    SUM(h.tongtien) as total_revenue,
                    AVG(h.tongtien) as avg_invoice,
                    SUM(c.chisomoi - c.chisocu) as total_kwh
                FROM hoadon h
                LEFT JOIN chisodien c ON h.maCSD = c.maCSD
                WHERE h.ngaylapdon >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
                GROUP BY DATE_FORMAT(h.ngaylapdon, '%Y-%m')
                ORDER BY period DESC
            ";
            $stmt = $conn->prepare($query);
        }

        $stmt->execute();
        $result = $stmt->get_result();
        $data = [];
        while ($row = $result->fetch_assoc()) {
            $data[] = array_map('intval_or_float', $row);
        }
        $stmt->close();

        return ['success' => true, 'data' => $data];
    }

    /**
     * Get consumption trends by customer segment
     */
    public function getConsumptionTrends(int $months = 12): array
    {
        $conn = $this->database->getConnection();
        
        $query = "
            SELECT 
                DATE_FORMAT(c.ngaynhap, '%Y-%m') as month,
                COUNT(DISTINCT c.maKH) as customer_count,
                AVG(c.chisomoi - c.chisocu) as avg_kwh,
                MIN(c.chisomoi - c.chisocu) as min_kwh,
                MAX(c.chisomoi - c.chisocu) as max_kwh,
                STDDEV(c.chisomoi - c.chisocu) as std_kwh,
                SUM(c.chisomoi - c.chisocu) as total_kwh
            FROM chisodien c
            WHERE c.ngaynhap >= DATE_SUB(NOW(), INTERVAL ? MONTH)
            GROUP BY DATE_FORMAT(c.ngaynhap, '%Y-%m')
            ORDER BY month ASC
        ";
        
        $stmt = $conn->prepare($query);
        $stmt->bind_param('i', $months);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $data = [];
        while ($row = $result->fetch_assoc()) {
            $data[] = [
                'month' => $row['month'],
                'customer_count' => intval($row['customer_count']),
                'avg_kwh' => floatval($row['avg_kwh']),
                'min_kwh' => floatval($row['min_kwh']),
                'max_kwh' => floatval($row['max_kwh']),
                'std_kwh' => floatval($row['std_kwh']),
                'total_kwh' => floatval($row['total_kwh']),
            ];
        }
        $stmt->close();

        return ['success' => true, 'data' => $data];
    }

    /**
     * Get top customers by consumption or revenue
     */
    public function getTopCustomers(string $orderBy = 'consumption', int $limit = 20): array
    {
        $conn = $this->database->getConnection();
        
        $orderClause = $orderBy === 'revenue' ? 'SUM(h.tongtien) DESC' : 'SUM(c.chisomoi - c.chisocu) DESC';
        
        $query = "
            SELECT 
                t.maKH,
                t.hovaten,
                t.diachi,
                COUNT(DISTINCT c.maCSD) as meter_count,
                SUM(c.chisomoi - c.chisocu) as total_kwh,
                SUM(h.tongtien) as total_revenue,
                AVG(c.chisomoi - c.chisocu) as avg_kwh,
                MAX(c.ngaynhap) as last_reading_date
            FROM taikhoan t
            LEFT JOIN chisodien c ON t.maKH = c.maKH
            LEFT JOIN hoadon h ON c.maCSD = h.maCSD
            WHERE t.quyen = 'khachhang'
            GROUP BY t.maKH, t.hovaten, t.diachi
            ORDER BY $orderClause
            LIMIT ?
        ";
        
        $stmt = $conn->prepare($query);
        if (!$stmt) {
            return ['success' => false, 'error' => 'Query error: ' . $conn->error];
        }
        
        $stmt->bind_param('i', $limit);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $data = [];
        while ($row = $result->fetch_assoc()) {
            $data[] = [
                'maKH' => $row['maKH'],
                'hovaten' => $row['hovaten'],
                'diachi' => $row['diachi'],
                'meter_count' => intval($row['meter_count']),
                'total_kwh' => floatval($row['total_kwh']),
                'total_revenue' => floatval($row['total_revenue']),
                'avg_kwh' => floatval($row['avg_kwh']),
                'last_reading_date' => $row['last_reading_date'],
            ];
        }
        $stmt->close();

        return ['success' => true, 'data' => $data];
    }

    /**
     * Get customer segmentation by consumption level
     */
    public function getCustomerSegmentation(): array
    {
        $conn = $this->database->getConnection();
        
        $query = "
            SELECT 
                CASE 
                    WHEN AVG(c.chisomoi - c.chisocu) < 50 THEN 'Thấp (< 50 kWh)'
                    WHEN AVG(c.chisomoi - c.chisocu) < 100 THEN 'Trung bình (50-100 kWh)'
                    WHEN AVG(c.chisomoi - c.chisocu) < 200 THEN 'Cao (100-200 kWh)'
                    ELSE 'Rất cao (> 200 kWh)'
                END as segment,
                COUNT(DISTINCT t.maKH) as customer_count,
                ROUND(AVG(c.chisomoi - c.chisocu), 2) as avg_kwh,
                SUM(h.tongtien) as total_revenue,
                COUNT(DISTINCT c.maCSD) as meter_count
            FROM taikhoan t
            LEFT JOIN chisodien c ON t.maKH = c.maKH
            LEFT JOIN hoadon h ON c.maCSD = h.maCSD
            WHERE t.quyen = 'khachhang' AND c.ngaynhap >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
            GROUP BY segment
            ORDER BY avg_kwh ASC
        ";
        
        $stmt = $conn->prepare($query);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $data = [];
        while ($row = $result->fetch_assoc()) {
            $data[] = [
                'segment' => $row['segment'],
                'customer_count' => intval($row['customer_count']),
                'avg_kwh' => floatval($row['avg_kwh']),
                'total_revenue' => floatval($row['total_revenue']),
                'meter_count' => intval($row['meter_count']),
            ];
        }
        $stmt->close();

        return ['success' => true, 'data' => $data];
    }

    /**
     * Get daily/monthly statistics
     */
    public function getStatistics(): array
    {
        $conn = $this->database->getConnection();
        
        $stats = [];
        
        // Total customers
        $result = $conn->query("SELECT COUNT(*) as cnt FROM taikhoan WHERE quyen = 'khachhang'");
        $stats['total_customers'] = intval($result->fetch_assoc()['cnt']);
        
        // Total meters
        $result = $conn->query("SELECT COUNT(*) as cnt FROM chisodien");
        $stats['total_meters'] = intval($result->fetch_assoc()['cnt']);
        
        // Total invoices (current month)
        $result = $conn->query("SELECT COUNT(*) as cnt FROM hoadon WHERE MONTH(ngaylapdon) = MONTH(NOW()) AND YEAR(ngaylapdon) = YEAR(NOW())");
        $stats['invoices_this_month'] = intval($result->fetch_assoc()['cnt']);
        
        // Total revenue (current year)
        $result = $conn->query("SELECT COALESCE(SUM(tongtien), 0) as total FROM hoadon WHERE YEAR(ngaylapdon) = YEAR(NOW())");
        $stats['revenue_this_year'] = floatval($result->fetch_assoc()['total']);
        
        // Average consumption
        $result = $conn->query("SELECT COALESCE(AVG(chisomoi - chisocu), 0) as avg FROM chisodien WHERE ngaynhap >= DATE_SUB(NOW(), INTERVAL 1 MONTH)");
        $stats['avg_consumption_month'] = floatval($result->fetch_assoc()['avg']);
        
        // Data quality - missing readings this month
        $result = $conn->query("SELECT COUNT(DISTINCT maKH) as cnt FROM taikhoan t WHERE quyen = 'khachhang' AND maKH NOT IN (SELECT DISTINCT maKH FROM chisodien WHERE MONTH(ngaynhap) = MONTH(NOW()) AND YEAR(ngaynhap) = YEAR(NOW()))");
        $stats['missing_readings_count'] = intval($result->fetch_assoc()['cnt']);
        
        return ['success' => true, 'stats' => $stats];
    }
}

function intval_or_float($value) {
    if ($value === null) return null;
    if (is_numeric($value)) {
        return strpos($value, '.') !== false ? floatval($value) : intval($value);
    }
    return $value;
}

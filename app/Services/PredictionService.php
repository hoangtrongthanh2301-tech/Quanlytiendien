<?php

namespace App\Services;

use App\Core\Database;

class PredictionService
{
    private Database $database;

    public function __construct(Database $database)
    {
        $this->database = $database;
    }

    /**
     * Simple linear regression for consumption prediction
     */
    public function predictConsumption(string $maKH, int $forecastMonths = 3): array
    {
        $conn = $this->database->getConnection();
        
        // Get last 12 months of data
        $query = "
            SELECT 
                DATE_FORMAT(c.ngaynhap, '%Y-%m') as month,
                SUM(c.chisomoi - c.chisocu) as kwh
            FROM chisodien c
            WHERE c.maKH = ? AND c.ngaynhap >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
            GROUP BY DATE_FORMAT(c.ngaynhap, '%Y-%m')
            ORDER BY month ASC
        ";
        
        $stmt = $conn->prepare($query);
        $stmt->bind_param('s', $maKH);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $historical = [];
        while ($row = $result->fetch_assoc()) {
            $historical[] = [
                'month' => $row['month'],
                'kwh' => floatval($row['kwh']),
            ];
        }
        $stmt->close();
        
        if (count($historical) < 3) {
            return [
                'success' => false,
                'error' => 'Insufficient data for prediction (need at least 3 months)',
                'historical' => $historical,
            ];
        }
        
        // Linear regression
        $n = count($historical);
        $x = array_keys($historical);
        $y = array_map(fn($h) => $h['kwh'], $historical);
        
        $sum_x = array_sum($x);
        $sum_y = array_sum($y);
        $sum_xy = 0;
        $sum_x2 = 0;
        
        for ($i = 0; $i < $n; $i++) {
            $sum_xy += $x[$i] * $y[$i];
            $sum_x2 += $x[$i] * $x[$i];
        }
        
        $slope = ($n * $sum_xy - $sum_x * $sum_y) / ($n * $sum_x2 - $sum_x * $sum_x);
        $intercept = ($sum_y - $slope * $sum_x) / $n;
        
        // Generate forecast
        $forecast = [];
        $lastMonth = end($historical)['month'];
        $nextDate = \DateTime::createFromFormat('Y-m', $lastMonth)->add(new \DateInterval('P1M'));
        
        for ($i = 1; $i <= $forecastMonths; $i++) {
            $predicted_kwh = $slope * ($n + $i) + $intercept;
            $predicted_kwh = max(0, $predicted_kwh); // No negative consumption
            
            $forecast[] = [
                'month' => $nextDate->format('Y-m'),
                'predicted_kwh' => round($predicted_kwh, 2),
                'confidence' => $this->calculateConfidence($historical),
            ];
            
            $nextDate->add(new \DateInterval('P1M'));
        }
        
        return [
            'success' => true,
            'maKH' => $maKH,
            'historical' => $historical,
            'forecast' => $forecast,
            'model' => [
                'type' => 'linear_regression',
                'slope' => round($slope, 4),
                'intercept' => round($intercept, 2),
            ],
        ];
    }

    /**
     * Forecast consumption for all customers in a month
     */
    public function forecastMonthlyConsumption(int $forecastMonth = 0, int $forecastYear = 0): array
    {
        if ($forecastMonth == 0 || $forecastYear == 0) {
            $forecastMonth = intval(date('m')) + 1;
            $forecastYear = intval(date('Y'));
            if ($forecastMonth > 12) {
                $forecastMonth = 1;
                $forecastYear++;
            }
        }
        
        $conn = $this->database->getConnection();
        
        // Get all active customers
        $query = "SELECT DISTINCT maKH FROM taikhoan WHERE quyen = 'khachhang'";
        $result = $conn->query($query);
        
        $forecasts = [];
        $totalForecast = 0;
        $count = 0;
        
        while ($row = $result->fetch_assoc()) {
            $prediction = $this->predictConsumption($row['maKH'], 1);
            
            if ($prediction['success'] && !empty($prediction['forecast'])) {
                $forecast = $prediction['forecast'][0];
                $forecasts[] = [
                    'maKH' => $row['maKH'],
                    'predicted_kwh' => $forecast['predicted_kwh'],
                ];
                $totalForecast += $forecast['predicted_kwh'];
                $count++;
            }
        }
        
        return [
            'success' => true,
            'month' => $forecastMonth,
            'year' => $forecastYear,
            'total_forecast_kwh' => round($totalForecast, 2),
            'customer_count' => $count,
            'avg_forecast_kwh' => $count > 0 ? round($totalForecast / $count, 2) : 0,
            'forecasts' => array_slice($forecasts, 0, 10), // Top 10
        ];
    }

    /**
     * Seasonality analysis - detect seasonal patterns
     */
    public function analyzeSeasonality(string $maKH = null): array
    {
        $conn = $this->database->getConnection();
        
        if ($maKH) {
            $query = "
                SELECT 
                    MONTH(c.ngaynhap) as month_num,
                    AVG(c.chisomoi - c.chisocu) as avg_kwh,
                    COUNT(*) as occurrences
                FROM chisodien c
                WHERE c.maKH = ? AND c.ngaynhap >= DATE_SUB(NOW(), INTERVAL 24 MONTH)
                GROUP BY MONTH(c.ngaynhap)
                ORDER BY month_num
            ";
            $stmt = $conn->prepare($query);
            $stmt->bind_param('s', $maKH);
        } else {
            $query = "
                SELECT 
                    MONTH(c.ngaynhap) as month_num,
                    AVG(c.chisomoi - c.chisocu) as avg_kwh,
                    COUNT(*) as occurrences
                FROM chisodien c
                WHERE c.ngaynhap >= DATE_SUB(NOW(), INTERVAL 24 MONTH)
                GROUP BY MONTH(c.ngaynhap)
                ORDER BY month_num
            ";
            $stmt = $conn->prepare($query);
        }
        
        $stmt->execute();
        $result = $stmt->get_result();
        
        $seasonality = [];
        while ($row = $result->fetch_assoc()) {
            $seasonality[] = [
                'month' => intval($row['month_num']),
                'month_name' => $this->monthName($row['month_num']),
                'avg_kwh' => floatval($row['avg_kwh']),
                'occurrences' => intval($row['occurrences']),
            ];
        }
        $stmt->close();
        
        return [
            'success' => true,
            'data' => $seasonality,
            'maKH' => $maKH,
        ];
    }

    private function calculateConfidence(array $historical): float
    {
        // Simple confidence based on data points
        // More data = higher confidence (max 95%)
        $count = count($historical);
        return min(0.95, 0.5 + ($count / 100));
    }

    private function monthName(int $month): string
    {
        $months = [
            1 => 'Tháng 1', 2 => 'Tháng 2', 3 => 'Tháng 3', 4 => 'Tháng 4',
            5 => 'Tháng 5', 6 => 'Tháng 6', 7 => 'Tháng 7', 8 => 'Tháng 8',
            9 => 'Tháng 9', 10 => 'Tháng 10', 11 => 'Tháng 11', 12 => 'Tháng 12',
        ];
        return $months[$month] ?? "Month $month";
    }
}

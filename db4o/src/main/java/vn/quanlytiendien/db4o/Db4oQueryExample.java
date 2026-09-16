package vn.quanlytiendien.db4o;

import com.db4o.Db4o;
import com.db4o.ObjectContainer;
import com.db4o.ObjectSet;
import vn.quanlytiendien.db4o.model.ElectricityModel.Customer;
import vn.quanlytiendien.db4o.model.ElectricityModel.MeterReading;
import vn.quanlytiendien.db4o.model.ElectricityModel.Tariff;
import vn.quanlytiendien.db4o.model.ElectricityModel.AdministrativeUnit;
import vn.quanlytiendien.db4o.model.ElectricityModel.UsageBreakdown;
import vn.quanlytiendien.db4o.model.ElectricityModel.Invoice;
import vn.quanlytiendien.db4o.model.ElectricityModel.Payment;
import vn.quanlytiendien.db4o.model.ElectricityModel.BlockchainRecord;

public final class Db4oQueryExample {
    private static final int DISPLAY_LIMIT = 50;

    private Db4oQueryExample() {
    }

    public static void main(String[] args) {
        String file = args.length == 0 ? "storage/qltiendien.yap" : args[0];
        System.out.println("Dang mo file dữ liệu db4o: " + file);
        ObjectContainer database = Db4o.openFile(file);

        try {
           
            System.out.println("\n==================================================");
            System.out.println("1. BẢNG TÀI KHOẢN / KHÁCH HÀNG (Customer)");
            System.out.println("==================================================");
            ObjectSet customers = database.query(Customer.class);
            System.out.println("-> Tổng số bản ghi: " + customers.size());
            int d1 = 0;
            while (customers.hasNext() && d1 < DISPLAY_LIMIT) {
                Customer c = (Customer) customers.next();
                System.out.printf("Mã KH: %s | Tên: %s | Email: %s | Quyền: %s%n", 
                    c.customerCode, c.fullName, c.email, c.role);
                d1++;
            }

         
            System.out.println("\n==================================================");
            System.out.println("2. BẢNG CHỈ SỐ ĐIỆN (MeterReading)");
            System.out.println("==================================================");
            ObjectSet readings = database.query(MeterReading.class);
            System.out.println("-> Tổng số bản ghi: " + readings.size());
            int d2 = 0;
            while (readings.hasNext() && d2 < DISPLAY_LIMIT) {
                MeterReading mr = (MeterReading) readings.next();
                System.out.printf("ID: %d | Cũ: %d | Mới: %d | Tiêu thụ: %d kWh (Tháng %d/%d)%n", 
                    mr.id, mr.previousValue, mr.currentValue, mr.consumption, mr.month, mr.year);
                d2++;
            }

            System.out.println("\n==================================================");
            System.out.println("3. BẢNG HÓA ĐƠN (Invoice)");
            System.out.println("==================================================");
            ObjectSet invoices = database.query(Invoice.class);
            System.out.println("-> Tổng số bản ghi: " + invoices.size());
            int d3 = 0;
            while (invoices.hasNext() && d3 < DISPLAY_LIMIT) {
                Invoice inv = (Invoice) invoices.next();
                System.out.printf("ID: %d | Điện tiêu thụ: %d kWh | Tổng tiền: %s | Trạng thái: %s%n", 
                    inv.id, inv.consumedKwh, inv.totalAmount, inv.status);
                d3++;
            }

            System.out.println("\n==================================================");
            System.out.println("4. BẢNG THANH TOÁN (Payment)");
            System.out.println("==================================================");
            ObjectSet payments = database.query(Payment.class);
            System.out.println("-> Tổng số bản ghi: " + payments.size());
            int d4 = 0;
            while (payments.hasNext() && d4 < DISPLAY_LIMIT) {
                Payment p = (Payment) payments.next();
                System.out.printf("ID: %d | PTTT: %s | Số tiền: %s | Trạng thái: %s%n", 
                    p.id, p.method, p.amount, p.status);
                d4++;
            }

            System.out.println("\n==================================================");
            System.out.println("5. BẢNG GIÁ ĐIỆN / BIỂU GIÁ (Tariff)");
            System.out.println("==================================================");
            ObjectSet tariffs = database.query(Tariff.class);
            System.out.println("-> Tổng số bản ghi: " + tariffs.size());
            int d5 = 0;
            while (tariffs.hasNext() && d5 < DISPLAY_LIMIT) {
                Tariff t = (Tariff) tariffs.next();
                System.out.printf("ID: %d | Bậc: %d | Hạn mức: %d | Đơn giá: %s%n", 
                    t.id, t.tier, t.quota, t.unitPrice);
                d5++;
            }
            System.out.println("\n==================================================");
            System.out.println("6. BẢNG ĐƠN VỊ HÀNH CHÍNH (AdministrativeUnit)");
            System.out.println("==================================================");
            ObjectSet units = database.query(AdministrativeUnit.class);
            System.out.println("-> Tổng số bản ghi: " + units.size());
            int d6 = 0;
            while (units.hasNext() && d6 < DISPLAY_LIMIT) {
                AdministrativeUnit au = (AdministrativeUnit) units.next();
                System.out.printf("Mã Xã: %s | Tên Xã: %s | Mã Tỉnh: %s | Tên Tỉnh: %s%n", 
                    au.communeCode, au.communeName, au.provinceCode, au.provinceName);
                d6++;
            }

            System.out.println("\n==================================================");
            System.out.println("7. BẢNG BLOCKCHAIN CHỈ SỐ ĐIỆN (BlockchainRecord)");
            System.out.println("==================================================");
            ObjectSet blockchains = database.query(BlockchainRecord.class);
            System.out.println("-> Tổng số bản ghi: " + blockchains.size());
            int d7 = 0;
            while (blockchains.hasNext() && d7 < DISPLAY_LIMIT) {
                BlockchainRecord br = (BlockchainRecord) blockchains.next();
                System.out.printf("ID: %d | Mã KH: %s | Hash: %s... | Người ký: %s%n", 
                    br.id, br.customerCode, 
                    (br.currentHash != null && br.currentHash.length() > 15 ? br.currentHash.substring(0, 15) : br.currentHash), 
                    br.signer);
                d7++;
            }
            System.out.println("\n==================================================");
            System.out.println("8. BẢNG CHI TIẾT TIÊU THỤ / THỐNG KÊ (UsageBreakdown)");
            System.out.println("==================================================");
            ObjectSet breakdowns = database.query(UsageBreakdown.class);
            System.out.println("-> Tổng số bản ghi: " + breakdowns.size());
            int d8 = 0;
            while (breakdowns.hasNext() && d8 < DISPLAY_LIMIT) {
                UsageBreakdown ub = (UsageBreakdown) breakdowns.next();
                System.out.printf("ID: %d | Bậc tính: %d | Số lượng chữ điện: %d | Thành tiền: %s%n", 
                    ub.id, ub.tier, ub.quantity, ub.amount);
                d8++;
            }

            System.out.println("\n==================================================");
            System.out.println("ĐÃ QUÉT XONG TOÀN BỘ CSDL DB4O THÀNH CÔNG!");
            System.out.println("==================================================");

        } finally {
            database.close();
            System.out.println("Đã đóng file db4o an toàn.");
        }
    }
}

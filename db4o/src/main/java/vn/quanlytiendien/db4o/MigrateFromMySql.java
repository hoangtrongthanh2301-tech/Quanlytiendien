package vn.quanlytiendien.db4o;

import com.db4o.Db4o;
import com.db4o.ObjectContainer;
import com.db4o.ObjectSet;
import vn.quanlytiendien.db4o.model.ElectricityModel.BlockchainRecord;
import vn.quanlytiendien.db4o.model.ElectricityModel.Customer;
import vn.quanlytiendien.db4o.model.ElectricityModel.AdministrativeUnit;
import vn.quanlytiendien.db4o.model.ElectricityModel.Invoice;
import vn.quanlytiendien.db4o.model.ElectricityModel.MeterReading;
import vn.quanlytiendien.db4o.model.ElectricityModel.Payment;
import vn.quanlytiendien.db4o.model.ElectricityModel.Tariff;
import vn.quanlytiendien.db4o.model.ElectricityModel.UsageBreakdown;

import java.math.BigDecimal;
import java.sql.Connection;
import java.sql.DriverManager;
import java.sql.PreparedStatement;
import java.sql.ResultSet;
import java.sql.Timestamp;
import java.util.Date;
import java.util.HashMap;
import java.util.Map;

public final class MigrateFromMySql {
    private MigrateFromMySql() {
    }

    public static void main(String[] args) throws Exception {
        String mysqlUrl = setting("mysql.url", "jdbc:mysql://127.0.0.1:3306/qltiendien?useUnicode=true&characterEncoding=utf8");
        String mysqlUser = setting("mysql.user", "root");
        String mysqlPassword = setting("mysql.password", "");
        String db4oFile = setting("db4o.file", "storage/qltiendien.yap");
        int customerLimit = Integer.parseInt(setting("mysql.customer.limit", "0"));

        try (Connection connection = DriverManager.getConnection(mysqlUrl, mysqlUser, mysqlPassword)) {
            ObjectContainer database = Db4o.openFile(db4oFile);
            try {
            migrate(connection, database, customerLimit);
                database.commit();
            } finally {
                database.close();
            }
        }
    }

    private static void migrate(Connection connection, ObjectContainer database, int customerLimit) throws Exception {
        Map<String, Customer> customers = importCustomers(connection, database, customerLimit);
        Map<Integer, MeterReading> readings = importReadings(connection, customers);
        importBreakdowns(connection, readings);
        importInvoicesAndPayments(connection, customers, readings);
        importBlockchainRecords(connection, customers, readings, database);
        importTariffs(connection, database);
        importAdministrativeUnits(connection, database);
        for (Customer customer : customers.values()) {
            database.set(customer);
        }
        System.out.println("DB4O migration completed: " + customers.size() + " customers, " + readings.size() + " meter readings.");
    }

    private static Map<String, Customer> importCustomers(Connection connection, ObjectContainer database, int customerLimit) throws Exception {
        Map<String, Customer> customers = new HashMap<String, Customer>();
        String sql = "SELECT maKH, hovaten, email, sodienthoai, matkhau, quyen, trangthai, diachi, ngaytao, face_descriptor, face_registered_at FROM taikhoan"
                + (customerLimit > 0 ? " LIMIT " + customerLimit : "");
        try (PreparedStatement statement = connection.prepareStatement(sql); ResultSet rows = statement.executeQuery()) {
            while (rows.next()) {
                Customer customer = new Customer(rows.getString("maKH"));
                customer.fullName = rows.getString("hovaten");
                customer.email = rows.getString("email");
                customer.phone = rows.getString("sodienthoai");
                customer.passwordHash = rows.getString("matkhau");
                customer.role = rows.getString("quyen");
                customer.status = rows.getString("trangthai");
                customer.address = rows.getString("diachi");
                customer.createdAt = date(rows.getTimestamp("ngaytao"));
                customer.faceDescriptor = rows.getString("face_descriptor");
                customer.faceRegisteredAt = date(rows.getTimestamp("face_registered_at"));
                customers.put(customer.customerCode, customer);
                database.set(customer);
            }
        }
        return customers;
    }

    private static Map<Integer, MeterReading> importReadings(Connection connection, Map<String, Customer> customers) throws Exception {
        Map<Integer, MeterReading> readings = new HashMap<Integer, MeterReading>();
        String sql = "SELECT maCSD, maKH, chisocu, chisomoi, dntieuthu, thang, nam, ngaynhap, created_at, updated_at FROM chisodien";
        try (PreparedStatement statement = connection.prepareStatement(sql); ResultSet rows = statement.executeQuery()) {
            while (rows.next()) {
                if (!customers.containsKey(rows.getString("maKH"))) {
                    continue;
                }
                MeterReading reading = new MeterReading();
                reading.id = rows.getInt("maCSD");
                reading.previousValue = rows.getInt("chisocu");
                reading.currentValue = rows.getInt("chisomoi");
                reading.consumption = rows.getInt("dntieuthu");
                reading.month = rows.getInt("thang");
                reading.year = rows.getInt("nam");
                reading.enteredAt = date(rows.getDate("ngaynhap"));
                reading.createdAt = date(rows.getTimestamp("created_at"));
                reading.updatedAt = date(rows.getTimestamp("updated_at"));
                readings.put(reading.id, reading);
                Customer customer = customers.get(rows.getString("maKH"));
                if (customer != null) {
                    customer.meterReadings.add(reading);
                }
            }
        }
        return readings;
    }

    private static void importBreakdowns(Connection connection, Map<Integer, MeterReading> readings) throws Exception {
        String sql = "SELECT maTK, maCSD, bacthang, dongia, sanluong, thanhtien FROM thongketiendien";
        try (PreparedStatement statement = connection.prepareStatement(sql); ResultSet rows = statement.executeQuery()) {
            while (rows.next()) {
                UsageBreakdown breakdown = new UsageBreakdown();
                breakdown.id = rows.getInt("maTK");
                breakdown.tier = rows.getInt("bacthang");
                breakdown.unitPrice = rows.getBigDecimal("dongia");
                breakdown.quantity = rows.getInt("sanluong");
                breakdown.amount = rows.getBigDecimal("thanhtien");
                MeterReading reading = readings.get(rows.getInt("maCSD"));
                if (reading != null) {
                    reading.breakdowns.add(breakdown);
                }
            }
        }
    }

    private static void importInvoicesAndPayments(Connection connection, Map<String, Customer> customers, Map<Integer, MeterReading> readings) throws Exception {
        String sql = "SELECT h.maHD, h.maKH, h.maCSD, h.sodiendatieuthu, h.tongtien, h.trangthai, h.hansudung, h.ngaytao, "
                + "tt.maTT, tt.phuongthuc, tt.sotien, tt.trangthai AS payment_status, tt.ngaytao AS payment_created, tt.ngaythanhtoan "
                + "FROM hoadon h LEFT JOIN thanhtoan tt ON tt.maHD = h.maHD";
        try (PreparedStatement statement = connection.prepareStatement(sql); ResultSet rows = statement.executeQuery()) {
            while (rows.next()) {
                Customer customer = customers.get(rows.getString("maKH"));
                if (customer == null) {
                    continue;
                }
                Invoice invoice = new Invoice();
                invoice.id = rows.getInt("maHD");
                invoice.consumedKwh = rows.getInt("sodiendatieuthu");
                invoice.totalAmount = rows.getBigDecimal("tongtien");
                invoice.status = rows.getString("trangthai");
                invoice.dueDate = date(rows.getDate("hansudung"));
                invoice.createdAt = date(rows.getTimestamp("ngaytao"));
                invoice.meterReading = readings.get(rows.getInt("maCSD"));
                if (invoice.meterReading != null) {
                    invoice.meterReading.invoices.add(invoice);
                }
                customer.invoices.add(invoice);
                if (rows.getObject("maTT") != null) {
                    Payment payment = new Payment();
                    payment.id = rows.getInt("maTT");
                    payment.method = rows.getString("phuongthuc");
                    payment.amount = rows.getBigDecimal("sotien");
                    payment.status = rows.getString("payment_status");
                    payment.createdAt = date(rows.getTimestamp("payment_created"));
                    payment.paidAt = date(rows.getTimestamp("ngaythanhtoan"));
                    payment.invoice = invoice;
                    invoice.payment = payment;
                }
            }
        }
    }

    private static void importBlockchainRecords(Connection connection, Map<String, Customer> customers, Map<Integer, MeterReading> readings, ObjectContainer database) throws Exception {
        String sql = "SELECT id, maCSD, maKH, chisocu, chisomoi, thang, nam, created_at, previous_hash, current_hash, signature, signer FROM blockchain_chisodien";
        try (PreparedStatement statement = connection.prepareStatement(sql); ResultSet rows = statement.executeQuery()) {
            while (rows.next()) {
                if (!customers.containsKey(rows.getString("maKH"))) {
                    continue;
                }
                BlockchainRecord record = new BlockchainRecord();
                record.id = rows.getInt("id");
                record.meterReadingId = rows.getInt("maCSD");
                record.customerCode = rows.getString("maKH");
                record.previousValue = rows.getInt("chisocu");
                record.currentValue = rows.getInt("chisomoi");
                record.month = rows.getInt("thang");
                record.year = rows.getInt("nam");
                record.createdAt = date(rows.getTimestamp("created_at"));
                record.previousHash = rows.getString("previous_hash");
                record.currentHash = rows.getString("current_hash");
                record.signature = rows.getString("signature");
                record.signer = rows.getString("signer");
                record.meterReading = readings.get(record.meterReadingId);
                database.set(record);
            }
        }
    }

    private static void importTariffs(Connection connection, ObjectContainer database) throws Exception {
        String sql = "SELECT maGD, bac, sanluong, dongia, ngayapdung, ngaytao FROM giadien";
        try (PreparedStatement statement = connection.prepareStatement(sql); ResultSet rows = statement.executeQuery()) {
            while (rows.next()) {
                Tariff tariff = new Tariff();
                tariff.id = rows.getInt("maGD");
                tariff.tier = rows.getInt("bac");
                tariff.quota = rows.getInt("sanluong");
                tariff.unitPrice = rows.getBigDecimal("dongia");
                tariff.effectiveDate = date(rows.getDate("ngayapdung"));
                tariff.createdAt = date(rows.getTimestamp("ngaytao"));
                database.set(tariff);
            }
        }
    }

    private static void importAdministrativeUnits(Connection connection, ObjectContainer database) throws Exception {
        String sql = "SELECT ma_xa, ma_tinh, ten_xa, ten_tinh, ma_dien_luc FROM don_vi_hanh_chinh";
        try (PreparedStatement statement = connection.prepareStatement(sql); ResultSet rows = statement.executeQuery()) {
            while (rows.next()) {
                AdministrativeUnit unit = new AdministrativeUnit();
                unit.communeCode = rows.getString("ma_xa");
                unit.provinceCode = rows.getString("ma_tinh");
                unit.communeName = rows.getString("ten_xa");
                unit.provinceName = rows.getString("ten_tinh");
                unit.electricityCode = rows.getString("ma_dien_luc");
                database.set(unit);
            }
        }
    }

    private static Date date(java.util.Date value) {
        return value == null ? null : new Date(value.getTime());
    }

    private static String setting(String name, String fallback) {
        String value = System.getProperty(name);
        return value == null ? fallback : value;
    }
}

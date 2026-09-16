package vn.quanlytiendien.db4o.model;

import java.math.BigDecimal;
import java.util.ArrayList;
import java.util.Date;
import java.util.List;

public final class ElectricityModel {
    private ElectricityModel() {
    }

    public static class Customer {
        public String customerCode;
        public String fullName;
        public String email;
        public String phone;
        public String passwordHash;
        public String role;
        public String status;
        public String address;
        public Date createdAt;
        public String faceDescriptor;
        public Date faceRegisteredAt;
        public final List<MeterReading> meterReadings = new ArrayList<MeterReading>();
        public final List<Invoice> invoices = new ArrayList<Invoice>();

        public Customer() {
        }

        public Customer(String customerCode) {
            this.customerCode = customerCode;
        }
    }

    public static class MeterReading {
        public int id;
        public int previousValue;
        public int currentValue;
        public int consumption;
        public int month;
        public int year;
        public Date enteredAt;
        public Date createdAt;
        public Date updatedAt;
        public final List<Invoice> invoices = new ArrayList<Invoice>();
        public final List<UsageBreakdown> breakdowns = new ArrayList<UsageBreakdown>();
    }

    public static class Tariff {
        public int id;
        public int tier;
        public int quota;
        public BigDecimal unitPrice;
        public Date effectiveDate;
        public Date createdAt;
    }

    public static class AdministrativeUnit {
        public String communeCode;
        public String provinceCode;
        public String communeName;
        public String provinceName;
        public String electricityCode;
    }

    public static class UsageBreakdown {
        public int id;
        public int tier;
        public BigDecimal unitPrice;
        public int quantity;
        public BigDecimal amount;
    }

    public static class Invoice {
        public int id;
        public int consumedKwh;
        public BigDecimal totalAmount;
        public String status;
        public Date dueDate;
        public Date createdAt;
        public MeterReading meterReading;
        public Payment payment;
    }

    public static class Payment {
        public int id;
        public String method;
        public BigDecimal amount;
        public String status;
        public Date createdAt;
        public Date paidAt;
        public Invoice invoice;
    }

    public static class BlockchainRecord {
        public int id;
        public int meterReadingId;
        public String customerCode;
        public int previousValue;
        public int currentValue;
        public int month;
        public int year;
        public Date createdAt;
        public String previousHash;
        public String currentHash;
        public String signature;
        public String signer;
        public MeterReading meterReading;
    }
}

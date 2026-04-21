# কোর মডিউল — ERP রূপান্তর (সারসংক্ষেপ)

এই ডকটি প্রস্তাবিত কোর মডিউলগুলোর তালিকা, প্রতিটির সংক্ষিপ্ত স্পেক (MVP), প্রধান টেবিল ও API নমুনা ধারণ করে। লক্ষ্য: দ্রুত MVP বানাতে যা দরকার তা এখানে।

## সার্বিক অগ্রাধিকার (Phase)
- Phase 1 (MVP): Authentication & RBAC, Accounting, Sales & Invoicing, Inventory (মৌলিক), Purchases/Procurement, Reporting/Dashboard
- Phase 2: HR & Payroll, CRM, Multi-warehouse, Payments Integration, Supplier Portal
- Phase 3: Advanced BI, Manufacturing, POS, Mobile Apps, Multi-tenant

---

## 1) Authentication & RBAC
- সংক্ষেপ: ইউজার ম্যানেজমেন্ট, রোল/পারমিশন, সেশন/টোকেন, 2FA
- MVP ফিচার: ইউজার CRUD, Login (email/password), Password reset, Roles, Basic permissions, Sanctum token support
- Key tables: users, roles, permissions, model_has_roles, model_has_permissions
- API উদাহরণ: `POST /api/v1/auth/login`, `GET /api/v1/users`, `POST /api/v1/roles`
- Priority: Phase 1
- Estimated effort: small (2–4 দিনের কাজ)

## 2) Accounting (কোর অ্যাকাউন্টিং)
- সংক্ষেপ: ডাবল-এন্ট্রি লেজার, চার্ট অফ অ্যাকাউন্ট, জার্নাল, পিরিয়ডিক ক্লোজ
- MVP ফিচার: Chart of Accounts, Journal Entries, Basic P&L, Trial Balance, Bank transactions, Export CSV/PDF
- Key tables: chart_of_accounts, ledger_accounts, journal_entries, transactions, bank_accounts
- API উদাহরণ: `POST /api/v1/accounting/journal`, `GET /api/v1/reports/trial-balance`
- Priority: Phase 1 (উচ্চ)
- Estimated effort: medium (2–3 সপ্তাহ)

## 3) Sales & Invoicing
- সংক্ষেপ: Order → Delivery → Invoice → Payment flow
- MVP ফিচার: Customer CRUD, Quotation → Sales Order → Invoice, Credit/Debit notes, Payment recording, PDF invoice generation
- Key tables: customers, sales_orders, invoices, invoice_items, payments
- API উদাহরণ: `POST /api/v1/sales/orders`, `POST /api/v1/invoices/{id}/pay`
- Priority: Phase 1
- Estimated effort: medium (1–2 সপ্তাহ)

## 4) Inventory (মৌলিক)
- সংক্ষেপ: স্টক ম্যানেজমেন্ট, স্টক মুভমেন্ট লগ, ইনভেন্টরি ব্যালান্স
- MVP ফিচার: Item/SKU CRUD, Stock In/Out, Stock adjustments, Warehouse (single), Stock valuation report
- Key tables: items, item_categories, warehouses, stock_movements, inventory_balances
- API উদাহরণ: `POST /api/v1/inventory/stock-movements`, `GET /api/v1/inventory/stock-levels`
- Priority: Phase 1
- Estimated effort: medium (1–2 সপ্তাহ)

## 5) Purchases / Procurement
- সংক্ষেপ: RFQ → PO → GRN → Supplier Bill
- MVP ফিচার: Supplier CRUD, Purchase Order creation, GRN recording, Supplier bill matching, Approvals (basic)
- Key tables: suppliers, purchase_orders, purchase_items, grns, supplier_bills
- API উদাহরণ: `POST /api/v1/purchases/orders`, `POST /api/v1/purchases/grn`
- Priority: Phase 1
- Estimated effort: medium (1–2 সপ্তাহ)

## 6) Reporting & Dashboard
- সংক্ষেপ: স্ট্যান্ডার্ড আর্থিক ও অপারেশনাল রিপোর্ট; কাস্টম ড্যাশবোর্ড
- MVP ফিচার: Trial Balance, P&L, Balance Sheet, Stock Valuation, AR/AP Ageing, Basic dashboard widgets
- Key tables: (derived from accounting/inventory/sales tables)
- Priority: Phase 1
- Estimated effort: small–medium (1–2 সপ্তাহ)

## 7) API & Integrations
- সংক্ষেপ: External systems (Payments, Banks, Attendance, SMS/Email)
- MVP ফিচার: Webhooks, Scheduled batch import (CSV), Payment gateway adapter (bKash)
- Priority: Phase 1 (basic hooks)
- Estimated effort: small (1 সপ্তাহ per integration)

## 8) HR & Payroll
- সংক্ষেপ: এমপ্লয়ি রেকর্ড, উপস্থিতি, ছুটি, বেতন হিসাব
- MVP ফিচার: Employee CRUD, Attendance import, Basic payslip generation, Loan/Advance tracking
- Key tables: employees, attendances, payslips, payroll_items
- Priority: Phase 2
- Estimated effort: medium (2–3 সপ্তাহ)

## 9) CRM / Contacts
- সংক্ষেপ: লিড ও কাস্টমার রিলেশনশিপ ম্যানেজমেন্ট
- MVP ফিচার: Leads, Contact activities, Simple pipeline, Integration with Sales
- Priority: Phase 2
- Estimated effort: small–medium (1–2 সপ্তাহ)

## 10) Advanced Modules (Phase 3)
- Manufacturing, POS, Mobile Apps, Full BI & Data Warehouse, Multi-tenant support
- এদের রোডম্যাপ আলাদা বুকে রাখা হবে।

---

## ডেলিভারি মাইক্রো-ফেজ (প্রস্তাব)
- Sprint 0: Authentication, Basic infra (docker, CI), DB মডেল বেস
- Sprint 1: Accounting core + Chart of Accounts + Journal Entries
- Sprint 2: Sales & Invoicing + PDF/Print
- Sprint 3: Inventory basics + Purchases
- Sprint 4: Reporting + Integrations (Payment, Email)

## পরবর্তী পদক্ষেপ
- প্রতিটি মডিউরের বিস্তারিত API স্পেসিফিকেশন তৈরি করা (`docs/api-specs/`)
- ERD ও মাইগ্রেশন স্ক্রিপ্ট ড্রাফট করা (আমি `docs/db_schema.md` তৈরি করেছি)।



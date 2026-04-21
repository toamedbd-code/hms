# Accounting Module — Quick Reference

এই ডকটি অ্যাকাউন্টিং মডিউলের দ্রুত রেফারেন্স দেয়: টেবিল, কনসেপ্ট এবং API এন্ডপয়েন্ট।

## মূল টেবিল
- `accounts` — Chart of Accounts
- `ledger_transactions` — Journal transactions (group header)
- `ledger_entries` — Individual debit/credit lines
- `account_balances` — Cached net balance per account

## API (v1)
- `GET /api/v1/accounting/accounts` — তালিকা দেখুন (Auth: Sanctum)
- `POST /api/v1/accounting/accounts` — নতুন অ্যাকাউন্ট তৈরি (Auth)
- `GET /api/v1/accounting/journals` — জার্নাল ট্রান্সেকশন তালিকা (Auth)
- `POST /api/v1/accounting/journals` — জার্নাল তৈরি (Auth)
- `GET /api/v1/accounting/reports/trial-balance` — ট্রায়াল ব্যালান্স রিপোর্ট (Auth)

## উদাহরণ: জার্নাল পে-লোড

```json
{
  "date": "2026-04-21",
  "description": "Sample journal",
  "lines": [
    {"account_id": 10, "entry_type": "debit", "amount": 1000.00},
    {"account_id": 20, "entry_type": "credit", "amount": 1000.00}
  ]
}
```

## নোটস
- `AccountingService::createJournal` নিশ্চিত করে যে ডেবিট ও ক্রেডিট সমান না হলে রিকোয়েস্ট rejected হবে।
- ব্যালান্স আপডেট সরল নিয়মে করা হয়: `debit` অ্যাকাউন্টের ব্যালান্স বাড়ায়, `credit` কমায়। প্রয়োজন অনুযায়ী অ্যাকাউন্ট-টাইপ অনুযায়ী লজিক সমন্বয় করা যেতে পারে।

---

পরবর্তী: আমি চাইলে একটি মাইক্রো-ইউনিট টেস্ট ও একটি ছোট সিডার তৈরি করতে পারি যা চার্ট-অফ-অ্যাকাউন্ট ও স্যাম্পল জার্নাল ইন্সার্ট করে। চান কি সেটা?
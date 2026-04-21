# সিস্টেম আর্কিটেকচার — ERP রূপান্তর

সংক্ষিপ্ত: এই ডকটি হাই-লেভেল সিস্টেম আর্কিটেকচার, প্রস্তাবিত টেক-স্ট্যাক, ডিপ্লয়মেন্ট প্যাটার্ন এবং মূল নকশা সিদ্ধান্তসমূহ সংরক্ষণ করে।

লক্ষ্য:
- বর্তমান `hms` প্রজেক্টকে একটি মডুলার, স্কেলেবল এবং নিরাপদ ERP সিস্টেমে রূপান্তর করা।
- ব্যবসায়িক মডিউলগুলো (অ্যাকাউন্টিং, HR, Inventory, Procurement, Sales, CRM) পরিষ্কারভাবে আলাদা করা।

প্রধান নকশা সিদ্ধান্ত
- মাইক্রো-সার্ভিস না: প্রাথমিকভাবে মনোলিথিক মডুলার আর্কিটেকচার (Laravel মডিউল/প্যাকেজ) রাখুন; প্রয়োজন হলে সার্ভিসগুলো সার্ভিসে বিভক্ত করবেন।
- API-first ডিজাইন: প্রতিটি মডিউল REST/GraphQL API এক্সপোজ করবে — SPA/mobile client সহজে ইন্টিগ্রেট করবে।
- ব্যাকগ্রাউন্ড জব: ভারী/লেন্থার প্রক্রিয়াগুলি Laravel Queue + Workers এ সরান।

প্রস্তাবিত টেক-স্ট্যাক
- Backend: PHP 8.1+, Laravel 10
- Frontend: Blade + Vue 3 (প্রয়োজনে React) — SPA অংশের জন্য Inertia.js বা dedicated API+SPA
- Database: MySQL 8.0 / MariaDB 10.5+ (প্রড-এ Primary + Read Replicas)
- Cache/Session: Redis
- Queue: Redis (Laravel Queue) + Horizon (অপশনাল)
- Object Storage: AWS S3 / MinIO (on-prem)
- Search (অপশনাল): Meilisearch বা Elasticsearch
- Authentication: Laravel Sanctum (SPA) / Passport (OAuth) বা JWT for external APIs; 2FA ও SSO অপশন
- Logging & Error Tracking: Monolog → Sentry/ELK
- Monitoring: Prometheus + Grafana
- CI/CD: GitHub Actions (test, lint, build, image push, migrate)
- Containerization: Docker + docker-compose (local); Production: Docker Registry / Kubernetes (optional)

হাই-লেভেল কম্পোনেন্ট
- Web/API Layer: Nginx -> PHP-FPM (Laravel)
- Worker Layer: Queue workers (jobs, async processing)
- Persistence: MySQL Primary + Replicas
- Cache: Redis
- Storage: S3-compatible object storage
- Integrations: Payment gateways (bKash), Bank APIs, SMS/Email gateways, Attendance devices
- Observability: ELK/Sentry/Prometheus

ডেপ্লয়মেন্ট প্যাটার্ন
- ডেভ: Docker Compose (single host)
- স্টেজিং: কনটেইনারাইজড সার্ভিস, ডেটাবেস স্টেজিং রেপ্লিকা
- প্রড: কনটেইনার + Orchestrator (Kubernetes) or managed services
- রিলিজ স্টেপস (CI):
  1. Run tests & static analysis (PHPStan/PHPCS/ESLint)
  2. Build containers, push to registry
  3. Deploy to staging, run migrations
  4. Smoke tests → deploy to production

সিকিউরিটি ও অপারেশনাল পয়েন্টস
- TLS everywhere (Let's Encrypt / Managed Certs)
- Secrets management (Vault/AWS Secrets Manager / Env encryption)
- RBAC: Laravel Permissions (spatie/laravel-permission) + custom policies
- Rate limiting, input validation, prepared statements
- Audit logs: ট্রানজেকশনাল অডিট ট্রেইল (user, action, timestamp, old/new)
- Backup: দৈনিক DB dump + উইক্লি/মন্তব্য-অফসাইট ব্যাকআপ to S3 (define RTO/RPO)

হাই-লেভেল ডেটা মডেল (সম্ভাব্য টেবিল/এন্টিটি)
- Users, Roles, Permissions
- Customers, Suppliers, Contacts
- Items (SKU), ItemCategories, Warehouses
- StockMovements (in/out), InventoryBalances
- PurchaseOrders, PurchaseItems, GRNs, SupplierBills
- SalesOrders, Invoices, InvoiceItems, Payments, Receivables
- ChartOfAccounts, Ledgers, JournalEntries, Transactions
- Employees, Attendances, Payslips, PayrollItems
- AuditLogs, Notifications, Integrations (external_identifiers)

স্কেলিং ও পারফরম্যান্স
- Read replicas for heavy-read reports
- Query optimization, proper indexing, pagination for large datasets
- Use Redis cache for session & hot-data
- Background jobs for report generation, bulk imports

ডাটা মাইগ্রেশন ও ইম্পোর্ট কৌশল
- স্টেজিং-এ সাবসেট ডাটা দিয়ে টেস্ট ইম্পোর্ট চালান
- ইম্পোর্ট পাইলাইন: CSV → Validation → Mapping → Dry-run → Commit
- Historical accounting data: প্রাথমিকভাবে read-only historical archive টেবিলে রাখা

ডকুমেন্ট লিংক
- আর্কিটেকচার ডায়াগ্রাম (Mermaid): [docs/architecture_diagram.mmd](docs/architecture_diagram.mmd)

পরবর্তী ধাপ
- কোর মডিউল তালিকা চূড়ান্ত করা এবং প্রতিটির ERD তৈরি করা
- প্রতিটি মডিউরের API স্পেসিফিকেশন `docs/api-specs/` এ রাখা
- Dockerfile ও GitHub Actions টেমপ্লেট তৈরি করা

---
এই ডক থেকে আমি `কোর মডিউল নির্ধারণ` কাজ শুরু করব — আপনি কোন মডিউলকে অগ্রাধিকার দিতে চান বলুন (অ্যাকাউন্টিং/HR/ইনভেন্টরি/সেলস)।

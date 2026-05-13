# ERP Execution Roadmap (12 Weeks) — HMS

এই ডকটি HMS কোডবেসে ERP-complete architecture বাস্তবায়নের জন্য week-by-week execution plan দেয়।
লক্ষ্য: কাজ ভেঙে এমনভাবে চালানো যাতে প্রতিটি 2 সপ্তাহে measurable output পাওয়া যায়।

## Scope (এই রোডম্যাপে)
- ERP Foundation: org structure, approval, audit, data access
- Finance hardening: AP/AR, bank recon, fixed assets controls
- Procurement + Inventory depth: 3-way matching, multi-warehouse, batch/expiry
- Revenue + CRM basics
- HR + Payroll integration
- Integration hub + BI baseline

## Delivery Rules
- প্রতি sprint শেষে: DB migration + API + UI + report + test artifacts থাকতে হবে
- High-risk change আগে feature flag দিয়ে release করতে হবে
- Backward-compatible schema change (nullable + backfill + enforce) ফলো করতে হবে

---

## Sprint Plan (12 Weeks)

## Week 1-2: Foundation Sprint
### লক্ষ্য
- ERP governance layer বসানো

### Deliverables
- Organization entities: company, branch, cost_center, profit_center
- Approval workflow base table/model: approval_flows, approval_steps, approval_requests
- Audit hardening: model-level before/after snapshot logging policy
- Data-access scope middleware (branch-wise filter scaffold)

### Technical Output
- Migrations
- Eloquent models + policies
- Seeder for default organization tree
- Admin setup UI (basic CRUD)

### Done যখন
- Branch-level isolation demo end-to-end কাজ করে
- Approval request create and approve flow API দিয়ে চালানো যায়

---

## Week 3-4: Finance Control Sprint
### লক্ষ্য
- Accounting কে ERP-grade control level-এ নেওয়া

### Deliverables
- AP/AR ageing report API + UI
- Bank reconciliation workflow
- Fixed assets lifecycle: register, capitalize, depreciate (monthly job)
- Period close checklist + close lock

### Technical Output
- Scheduled jobs for depreciation
- Reconciliation tables and matching status
- Month-end lock guard in posting services

### Done যখন
- Close period-এ backdated posting blocked হয়
- AP/AR ageing report export-ready হয়

---

## Week 5-6: Procurement + Inventory Sprint
### লক্ষ্য
- Procure-to-pay এবং inventory accuracy উন্নত করা

### Deliverables
- RFQ to PO to GRN to Supplier Bill flow
- 3-way matching (PO vs GRN vs Bill)
- Multi-warehouse transfer
- Batch/lot/expiry tracking
- Reorder rules and low-stock suggestion

### Technical Output
- Procurement status state machine
- Inventory movement reason codes
- Expiry and batch validation at receive and issue

### Done যখন
- Mismatch bill auto-hold হয়
- Transfer traceable ledger trail থাকে

---

## Week 7-8: Revenue + CRM Sprint
### লক্ষ্য
- Quote-to-cash reliability + collection discipline

### Deliverables
- Quotation to Sales Order to Invoice full chain
- Collection follow-up queue (overdue invoice)
- Credit limit and discount policy rules
- Basic CRM pipeline (lead, stage, follow-up)

### Technical Output
- Policy service for pricing and credit checks
- Overdue job + notification hooks

### Done যখন
- Customer-level exposure dashboard পাওয়া যায়
- Overdue collection list daily auto-refresh হয়

---

## Week 9-10: HR + Payroll Sprint
### লক্ষ্য
- Attendance থেকে payroll-ready pipeline

### Deliverables
- Employee master lifecycle states
- Shift-aware attendance computation
- Payroll run (gross, deduction, net)
- Payslip publish and salary lock

### Technical Output
- Payroll formula config table
- Attendance to payroll aggregation job

### Done যখন
- One-click payroll generation for a month complete হয়
- Locked payslip edit attempt blocked হয়

---

## Week 11-12: Integration + BI Sprint
### লক্ষ্য
- Cross-module observability এবং executive reporting

### Deliverables
- Integration hub baseline: webhook retry, failure queue, logs
- Finance + Inventory + Revenue KPI dashboard
- Department profitability snapshot
- Operational data quality checks

### Technical Output
- Integration event log tables
- KPI materialized queries/views
- Data quality command and report

### Done যখন
- Failed integration retry policy চালু থাকে
- CXO dashboard production data দেখায়

---

## Cross-Sprint Non-Functional Track
## Security
- RBAC + policy audit each sprint
- PII fields encryption review

## Quality
- প্রতি sprint regression checklist
- Critical path integration tests

## DevOps
- CI gate: migration, unit, feature test, lint
- Staging smoke run mandatory

## Documentation
- প্রতি feature শেষে API note + SOP update

---

## Module Ownership (প্রস্তাব)
- Finance Lead: Accounting, AP/AR, bank recon, close
- Supply Chain Lead: Procurement, inventory, valuation
- Operations Lead: Sales, CRM, collections
- People Lead: HR, attendance, payroll
- Platform Lead: Approvals, audit, integration hub, BI

## Tracking Board (Minimum)
- Epic level: Foundation, Finance, Supply Chain, Revenue, HR, Integration
- Ticket label: schema, api, ui, report, test, migration, risk
- Definition of Ready: business rule + acceptance criteria + data impact note
- Definition of Done: code + migration + test + document + rollback note

---

## Immediate Start Tasks (Today + Next 3 Days)
1. ERP foundation schema draft তৈরি
2. Branch and cost_center scoping strategy finalize
3. Approval flow base entities implement
4. Audit log schema contract freeze
5. Sprint board তৈরি করে owners assign

## Next Action
- এই roadmap অনুযায়ী Week 1-2 এর technical design doc আলাদা করে তৈরি হবে:
  - docs/org_structure.md
  - docs/approval_engine.md
  - docs/access_scope_policy.md
  - docs/audit_strategy.md

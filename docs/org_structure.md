# Org Structure Design (Week 1-2)

এই ডক HMS-এ organization hierarchy standardize করার design দেয়।

## Objective
- company, branch, cost center, profit center relation normalize করা
- transaction-level ownership enforce করা
- report filtering (branch/cost center/profit center) consistent করা

## Proposed Entities
- companies
- branches
- cost_centers
- profit_centers

## Field Contract (MVP)

## companies
- id
- code (unique)
- name
- status (active/inactive)

## branches
- id
- company_id
- code (unique per company)
- name
- timezone
- currency_code
- status

## cost_centers
- id
- company_id
- branch_id (nullable for company-wide)
- code (unique per company)
- name
- status

## profit_centers
- id
- company_id
- branch_id (nullable)
- code (unique per company)
- name
- status

## Relationship Rules
- একটি branch অবশ্যই একটি company-এর child
- cost_center এবং profit_center company-bound
- branch-bound center হলে branch_id required
- global center হলে branch_id nullable

## Integration Targets (Phase-0 attach)
- journal_entries: company_id, branch_id, cost_center_id, profit_center_id
- payments: branch_id
- purchases and sales documents: company_id, branch_id
- fixed_assets: company_id, branch_id, cost_center_id

## Migration Strategy
1. New tables create
2. Master seed create (default company + main branch)
3. Existing transactional tables-এ nullable FK add
4. Backfill script দিয়ে existing rows-এ default branch/company সেট
5. After backfill, selected columns NOT NULL enforce

## API Contract (Initial)
- GET /api/v1/org/companies
- GET /api/v1/org/branches
- POST /api/v1/org/branches
- GET /api/v1/org/cost-centers
- POST /api/v1/org/cost-centers
- GET /api/v1/org/profit-centers

## Policy
- super-admin: সব company/branch access
- company-admin: নিজ company
- branch-admin: নিজ branch + company-global rows

## Acceptance Criteria
- org master CRUD working
- branch-scoped user অন্য branch data list করতে পারে না
- journal report branch filter নিয়ে accurate result দেয়

## Open Decisions
- multi-currency level: company না branch
- inter-branch transaction posting pattern
- profit_center optional না mandatory for revenue docs

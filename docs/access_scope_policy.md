# Access Scope Policy (Week 1-2)

এই ডক data access scope enforce করার application pattern define করে।

## Objective
- branch/company boundary enforce করা
- accidental cross-branch data leak বন্ধ করা
- report/API/UI সব layer-এ consistent scope রাখা

## Scope Dimensions
- company scope
- branch scope
- optional: department/cost center scope

## User Scope Model
- users table-এ default_company_id, default_branch_id
- pivot (user_branches) দিয়ে multi-branch access support
- role-based privilege + scope-based filtering combine

## Enforcement Layers
1. Request context resolver
- auth user থেকে active company এবং branch resolve

2. Query scope
- reusable trait: ScopesByOrganization
- pattern: ->forCompany($id), ->forBranch($id)

3. Policy checks
- view, update, delete permission with scope validation

4. Controller/service guard
- create/update-এর সময় payload branch_id validate

## Table Convention
- organization-bound table-এ minimum:
  - company_id
  - branch_id (nullable only if global-by-design)

## Global Data Rule
- master data global হলে branch_id nullable
- read query-তে condition:
  - branch_id = active branch OR branch_id IS NULL

## Reporting Rule
- report endpoint-এ explicit scope filter required
- যদি user broad scope না পায়, requested filter narrow করা হবে

## Logging Rule
- denied access সব access_denied audit log-এ যাবে
- high-risk endpoints-এ request_id সহ trace থাকবে

## Middleware Plan
- ResolveOrganizationContext middleware
- EnsureBranchScope middleware

## Acceptance Criteria
- branch-admin অন্য branch payment দেখতে পারবে না
- company-admin cross-branch consolidated report দেখতে পারবে
- global master data branch user read করতে পারবে

## Rollout Plan
1. New critical modules first (journal, payment, purchase)
2. Existing legacy screens gradual backfill
3. Strict mode enable after data backfill

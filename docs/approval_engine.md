# Approval Engine Design (Week 1-2)

এই ডক ERP-wide maker-checker এবং multi-step approval flow design define করে।

## Objective
- reusable approval framework বানানো
- module-specific hardcoded approval logic বাদ দেওয়া
- auditable approve/reject trail রাখা

## Core Tables
- approval_flows
- approval_steps
- approval_requests
- approval_actions

## approval_flows
- id
- module (purchase_order, journal_entry, payment, etc.)
- company_id
- branch_id (nullable)
- name
- is_active

## approval_steps
- id
- approval_flow_id
- step_no
- approver_type (role, user)
- approver_role_id (nullable)
- approver_user_id (nullable)
- min_approvals
- amount_threshold_min (nullable)
- amount_threshold_max (nullable)

## approval_requests
- id
- module
- entity_type
- entity_id
- company_id
- branch_id
- flow_id
- current_step_no
- status (pending, approved, rejected, cancelled)
- requested_by
- requested_at

## approval_actions
- id
- approval_request_id
- step_no
- action (approve, reject, return)
- acted_by
- acted_at
- comment

## Runtime Rules
- request pending থাকলে target entity immutable থাকবে
- reject হলে status = rejected এবং business doc draft/returned state-এ যাবে
- flow না থাকলে module policy অনুযায়ী auto-approve বা block

## Service Contract
- ApprovalService::start(module, entity)
- ApprovalService::approve(requestId, userId, comment)
- ApprovalService::reject(requestId, userId, comment)
- ApprovalService::canUserApprove(requestId, userId)

## API Contract (Initial)
- POST /api/v1/approvals/start
- POST /api/v1/approvals/{id}/approve
- POST /api/v1/approvals/{id}/reject
- GET /api/v1/approvals/pending
- GET /api/v1/approvals/history

## UI Contract
- Pending approval inbox widget
- Request timeline (step-wise action log)
- Approve/reject modal with mandatory comment option

## Security
- same user maker and final approver দুটোই হতে পারবে কি না policy-driven
- privileged override action আলাদা permission-এ gated

## Acceptance Criteria
- purchase order sample flow: 2-step approval end-to-end কাজ করবে
- approval trail report থেকে action history দেখা যাবে
- unauthorized user approve করতে পারবে না

## Open Decisions
- parallel approvals support Phase-1 এ লাগবে কি না
- SLA/escalation notification engine immediate না later

# Audit Strategy (Week 1-2)

এই ডক ERP audit architecture define করে যাতে compliance-ready change history পাওয়া যায়।

## Objective
- কে, কখন, কী change করেছে trace করা
- high-risk action reportable করা
- incident investigation দ্রুত করা

## Audit Levels
- L1: Authentication and access events
- L2: Business data CRUD events
- L3: Financial critical events (approval, posting, void, reversal)

## Event Matrix (Minimum)
- login success/failure
- role/permission change
- master data create/update/delete
- journal post/unpost/reverse
- payment create/void/refund
- approval approve/reject/override

## Data Capture Contract
- actor_id
- action
- model_type
- model_id
- before_snapshot (JSON)
- after_snapshot (JSON)
- request_id
- ip_address
- user_agent
- created_at

## Sensitive Data Handling
- password/token/secret fields কখনও snapshot-এ রাখা যাবে না
- personal fields mask policy (phone, email আংশিক)

## Storage and Retention
- hot audit table: 12 months
- archive strategy: monthly partition বা archive table
- immutable archive export (CSV/JSON)

## Implementation Pattern
- model observer or domain event based logging
- queue-based async write allowed for non-critical
- financial critical events sync write

## API/Report
- GET /api/v1/audit/logs
- filter by module, action, actor, date range
- export endpoint for compliance review

## Alerting
- high-risk alerts:
  - role escalation
  - period close পর posting attempt
  - repeated failed approval action

## Acceptance Criteria
- journal entry update করলে before/after snapshot stored হয়
- audit report থেকে user + action + entity trace করা যায়
- sensitive fields masked থাকে

## Open Decisions
- archive storage database না object storage
- legal retention duration (12/24/60 months)

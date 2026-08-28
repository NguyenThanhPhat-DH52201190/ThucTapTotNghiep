# Schema-alignment todo list

The target is the spreadsheet schema supplied for the eight ERP modules. Changes
must be additive and retain compatibility with the existing Laravel tables and
data.

- [x] **1. Master data** — add `customer_info`, `styles`, `materials`, and
  `material_vendors`.
- [x] **2. Order management** — add nullable master-data foreign keys and
  `order_sizes`; retain the existing `ocs` workflow.
- [x] **3. BOM, MRP, procurement** — add missing source/material references,
  colour data, and PO currency without disrupting current calculations.
- [x] **4. Inventory execution** — add balances, requisitions, issues, and
  their item tables; connect them to inventory transactions.
- [x] **5. MPS and shop floor** — add sewing lines, work orders, schedules,
  detailed production logs, and downtime logs.
- [x] **6. Finance and verification** — align costing/revenue references,
  validate migrations, and produce a final schema comparison.

## Rules

1. Never rename or drop current production tables in this migration series.
2. New relationships to existing data are nullable until a deliberate data
   migration/backfill is completed.
3. Use database foreign keys and indexes for every new relationship.

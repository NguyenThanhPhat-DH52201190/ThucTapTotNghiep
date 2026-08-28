# MRP, Procurement & Inventory workflow checklist

- [x] MRP: calculate available stock as on-hand - allocated + on-order.
- [x] MRP: persist calculated results into `mrp_suggestions`.
- [x] MRP: provide scheduled execution (`mrp:run`, daily at 00:00) instead of page-load calculation.
- [x] Procurement: create one PO per selected vendor and link every source suggestion.
- [x] Procurement: persist per-line ETA and update MPS material readiness.
- [x] Inventory: receive PO through a single transaction and ledger entry.
- [x] Inventory: issue stock with `lockForUpdate()` and reject negative balances.
- [x] Inventory: prohibit direct edits to `inventory_balances.balance_qty`; the only write path is `InventoryLedgerService`.
- [x] Verify routes, syntax, migrations, and critical calculations.

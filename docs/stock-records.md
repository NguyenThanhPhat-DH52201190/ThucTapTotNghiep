# Stock Records

Open **Stock Records** in the sidebar or from Inventory. Admin, PPIC and Warehouse can access the module.

## Setup

Run `php artisan migrate` after deployment, then `php artisan stock-records:sync` to initialize records from Inventory. The **Add new codes from Inventory** button runs the same import for later additions. Repeating the import never resets an existing opening snapshot. Materials without an inventory balance are not imported; a zero-quantity inventory balance can be used to track a new code.

## Quantities and history

Each material has one immutable opening quantity, summed across warehouses, locations and lots. The snapshot stores the last existing inventory transaction ID. Subsequent signed movements come directly from Inventory Transactions; Stock Records does not create duplicate receipts/issues. The detail page includes a running balance in posting order, users, documents and CS references for material issues. The all-history option includes pretracking movements and derives the preledger balance from the snapshot. A discrepancy between the snapshot plus movements and current inventory is shown explicitly, never silently corrected.

## Priorities

Material priority changes the list order only. Within a material, order priorities control a simulation of stock coverage. Use priority numbers or up/down buttons, then **Save priorities & recalculate**. New orders default to ship-date order after explicitly prioritized orders. Changes are audited, and stale priority forms are rejected.

The plan uses current order BOM requirements, including product-size mappings, waste, material colorways and quantity amendments. Pending, confirmed, in-production and released orders are included. Completed, closed and cancelled orders do not consume future demand; their actual movements remain in history. Already-issued requisition quantities are consumed once per order/material/color/size, including when multiple BOM lines use that material.

Only matching colors/sizes cover each need. Active reservations remain with their owning orders. Remaining unreserved balances are allocated in priority order. **Projected total** is total on-hand minus cumulative remaining demand; **Shortage** reflects actual usable coverage, including color/size and reservation constraints. A positive aggregate projected total can therefore coexist with a shortage. Purchase orders not yet received are not included in coverage. Priorities never change actual balances, requisitions, reservations or inventory history.

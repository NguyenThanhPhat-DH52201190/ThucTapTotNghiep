# Delivery Bill from NORM

Open a CU in NORM → **Export Excel & Confirm Issue**. Enter No, Customer, Address, Reason, Shipper and optional Shipper Address. Day is assigned by the server when confirmation succeeds. Choose material, warehouse/location/lot/roll and actual quantity; add rows to split across lots.

The button both confirms the issue and returns an Excel download, based on the supplied `resources/templates/delivery-bill.xlsx` (`DELI`). Company/address and signature positions come from the template. More than ten lines extend the table and shift the signatures. Codes and text are written as literal strings, not Excel formulas.

## Stock and priorities

- Uses existing `material_issues`, `issue_items`, `requisition_items` and `InventoryLedgerService`; Stock Records therefore sees normal OUT transactions and updated CU issued quantities.
- Reuses a pending/partial requisition where possible. If none exists, creates a requisition for this delivery flow. Missing selected material lines are added from remaining NORM requirements without automatically reserving stock.
- Remaining NORM is informational, not an issue limit. Additional quantities are allowed when usable stock is sufficient. The selected requisition quantity is increased as needed in the same confirmation transaction; NORM Yield/Waste/Required are unchanged. All actual quantities remain recorded in issue history and Stock Records.
- May consume the selected requisition's own reservation and unreserved stock. Other reservations cannot be consumed. Colour/size must match.
- Compares Stock Records' higher-priority CU allocations before/after the tentative issue under database locks. Reduced allocation requires a separate priority override reason. The first rejected submission saves nothing; fill the reason and submit again. Sufficient stock or consumption of already reserved stock does not trigger an unnecessary override.
- The override reason and priority snapshot are retained on the delivery bill. Transaction notes include CU, business reason and override reason, visible in Stock Records.
- This priority check applies to the new NORM delivery-bill confirmation. The pre-existing Inventory issue screen is unchanged.
- Additional consumption can be explained in the required Reason field. Defect logs remain separate; issuing a bill does not automatically mark a defect replacement request as fulfilled.

## Files and retries

Excel is generated before the database transaction commits. A generation/validation failure rolls back stock, issue and requisition changes and cleans up the new file. Double-click/retry with the same submission key returns the existing bill. Use **Download again** in the confirmed-bill history after reloading the page. Download never posts another movement.

Saved bills contain immutable header/line snapshots and a private Excel file, so later material/BOM/customer edits do not change re-downloads. Back up `storage/app/private/delivery-bills` along with the database. If a saved file is missing, restore the file rather than confirming another issue.

Existing NORM admin permissions are retained; other roles are not newly granted stock-issuing access.

## Deploy

```sh
php artisan migrate --force
php artisan optimize:clear
```

Deploy the template and preserve private storage. No new Composer/npm dependency is required. Confirm PHP can write the private delivery-bills directory. Bill/ledger issue dates default to Vietnam time (`Asia/Ho_Chi_Minh`); override with `DELIVERY_BILL_TIMEZONE` if needed.

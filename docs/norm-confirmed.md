# NORM confirmed values

Open NORM → View Materials → Edit NORM. Enter `Yield confirmed` and/or
`watse confirmed`, optionally add a change reason, then Save NORM.
The column spelling `watse confirmed` is intentional, as requested.

- `Yield plan` always shows the current BOM yield.
- A blank confirmed yield uses the BOM yield. A blank confirmed waste uses
  BOM waste and is marked `(BOM)` in the table. Entering waste 0 overrides BOM
  waste with 0%; clearing a confirmed field restores its BOM fallback.
- Required = applicable product quantity × applied yield × (1 + applied waste / 100).
  Existing product-size applicability and four-decimal calculation precision remain.
- Changes are saved per CU and BOM item, with user/time/reason in Audit Trail.
  Save affects up to 50 displayed rows. Other pages and CUs are unchanged.
- Sync retains confirmations. BOM edits retain item IDs when the material is
  unchanged. Replacing/removing a material creates a different requirement; its
  old confirmation is not applied to the replacement. A changed BOM yield/waste
  is flagged for review against the last confirmed snapshot.
- Concurrent edits are checked by confirmation revision and submitted BOM rates.
- NORM, Stock Records, new MRP calculations and newly created requisitions use
  the same rate resolver. Existing MRP runs, POs, requisitions, reservations and
  inventory movements are not rewritten. Rerun MRP and review existing documents
  when a confirmation changes demand.
- Excel includes Yield plan, Yield confirmed and watse confirmed.

## Deploy

Run `php artisan migrate --force` and `php artisan view:clear` after deploying.
Migration `2026_09_22_000008_create_norm_confirmations.php` adds a separate table;
it does not modify existing BOM or stock quantities. No new dependency is needed.

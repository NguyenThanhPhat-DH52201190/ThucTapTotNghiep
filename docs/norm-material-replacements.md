# NORM material replacements

## Use

Open NORM Materials for a CU and choose **Replace material**.

- **Replace unissued requirement**: enter part of the remaining quantity, or choose **Use all remaining**. The original requirement decreases by that amount and a separate replacement line is added.
- **Replace recorded defective material**: choose an existing Material defects entry. This adds replacement demand; it does not subtract the original issued requirement again. The amount committed to replacements cannot exceed that defect's requested replacement quantity. Saving a replacement is not confirmation that it has been issued.

Select a different material from Material Master. Colour, size and unit come from that material. Enter the new confirmed yield, waste and reason; review the calculated quantity before saving.

The conversion is:

`replacement required = original quantity / (original effective yield * (1 + original waste / 100)) * new yield * (1 + new waste / 100)`

Example: original yield 1 and waste 0; replace 20 PCS with yield 2 and waste 5% -> 42 units of the new material. Values are snapshotted, with required quantity rounded to four decimal places.

## Behaviour

- BOM and other CUs are unchanged. Original lines, issued quantities and prior delivery files remain intact.
- Replacement entries are immutable audit records with original/new material snapshots, user, time and reason. The page displays the full history, including entries from older assigned BOMs.
- Repeated submission of the same form is idempotent. Stale source rates/quantities, inactive CUs, foreign CU lines and excessive replacement amounts are rejected.
- New lines appear in NORM and its Excel export, Delivery Bill selection, Material defects selection and Stock Records allocation for the replacement material. The delivery bill snapshots the replacement ID and deducts the selected material/lot; normal stock and priority controls still apply.
- New MRP runs and newly created requisitions use the amended requirements. Existing PO, MRP runs, requisitions and actual reservations are not rewritten. Review/release old reservations separately and rerun MRP as needed. Existing requisitions can still issue extra quantities under the existing stock controls.
- Original source lines continue to follow BOM/confirmed-rate edits minus the fixed quantity transferred; replacement quantities remain the amounts confirmed at replacement time. Recheck replacements when changing BOM or order quantities. Only replacements whose source BOM item still belongs to the assigned BOM participate in current requirements.
- This screen replaces original BOM-derived lines. It does not recursively replace a replacement line or edit/delete saved replacements. Defects can still be recorded against replacement materials.

## Deployment

```bash
php artisan migrate --force
php artisan optimize:clear
php artisan queue:restart
```

Migration: `2026_09_24_000004_create_norm_material_replacements.php`. No additional package is required.

## Verification

StockRecordsTest covers CU isolation, original BOM/issued history preservation, partial/full remaining transfers, rate conversion, retry/stale protection, defect quantity limits, permission checks, allocation and issue of the new code, and newly generated requisition/MRP demand.

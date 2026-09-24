# NORM: material defects

Open a CU in NORM → **Material defects / Ghi nhận lỗi**. Record up to 20 material lines per submission: date, defective quantity, requested replacement, disposition, reason and optional image (2 MB maximum each). Quantities use the displayed material unit, not the garment quantity.

Only materials currently linked to that CU's NORM/BOM are accepted. Historical entries preserve material descriptions, colour/size and units even if the BOM changes later. History is paginated and records the submitting user. Double submission of the same form is ignored.

This is a defect log, not an inventory transaction or approved requisition. It does not change Yield/Waste, requirements, reservations, stock or existing POs. Requested replacement is recorded for review; approval and warehouse issuance are not implemented here. NORM's existing admin access rules are retained.

Deploy with `php artisan migrate --force` and `php artisan optimize:clear`. No additional libraries are required. Evidence is stored in `norm-defect-images` on the private `local` disk; preserve that storage directory on deployment.

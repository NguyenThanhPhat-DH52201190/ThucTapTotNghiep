# Masterplan Database Structure & Relationships

## Overview
The application uses a **legacy database schema** with direct DB queries rather than Eloquent models. The main tables are:
- **mtp** - Master Plan table (main table)
- **ocs** - Order/Cutting Schedule table (related data)
- **colors** - Line color categories
- **holidays** - Holiday dates

---

## MTP (Master Plan) Table Schema

### Database Table Definition
```sql
CREATE TABLE mtp (
    id UNSIGNED BIGINT PRIMARY KEY AUTO_INCREMENT,
    CU VARCHAR(255) NOT NULL,                    -- Customer Unit (FK to ocs.CS)
    Line VARCHAR(255) NOT NULL,                  -- Production Line
    LineColor VARCHAR(7) NULLABLE,               -- Color code in HEX format
    Fabric1 VARCHAR(50) NULLABLE,                -- First fabric type
    ETA1 DATE NULLABLE,                          -- Estimated arrival for Fabric1
    Actual DATE NULLABLE,                        -- Actual date
    Fabric2 VARCHAR(50) NULLABLE,                -- Second fabric type
    ETA2 DATE NULLABLE,                          -- Estimated arrival for Fabric2
    Linning VARCHAR(50) NULLABLE,                -- Lining material
    ETA3 DATE NULLABLE,                          -- Estimated arrival for Lining
    Pocket VARCHAR(50) NULLABLE,                 -- Pocket material
    ETA4 DATE NULLABLE,                          -- Estimated arrival for Pocket
    Trim VARCHAR(50) NULLABLE,                   -- Trim material
    inWHDate DATE NULLABLE,                      -- In Warehouse Date
    3rd_PartyInspection VARCHAR(50) NULLABLE,    -- Third party inspection info
    ShipDate2 DATE NULLABLE,                     -- Secondary ship date
    SoTK VARCHAR(50) NULLABLE,                   -- Sales Order to Keep
    ExQty UNSIGNED INT NULLABLE,                 -- Extra Quantity
    lt INT NULLABLE,                             -- Lead Time
    FirstOPT DATE NULLABLE,                      -- First OPT (On-time Performance) date
    Qty_dis UNSIGNED INT NULLABLE,               -- Quantity distributed
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);
```

---

## OCS (Order Cutting Schedule) Table Schema

### Database Table Definition
```sql
CREATE TABLE ocs (
    id UNSIGNED BIGINT PRIMARY KEY AUTO_INCREMENT,
    CS VARCHAR(255) UNIQUE NOT NULL,             -- Customer Style ID (PK for joins)
    CsDate DATE NULLABLE,                        -- Customer Style Date
    SNo VARCHAR(255) NULLABLE,                   -- Style Number
    Sname VARCHAR(255) NULLABLE,                 -- Style Name
    Customer VARCHAR(255) NULLABLE,              -- Customer Name
    Color VARCHAR(255) NULLABLE,                 -- Color
    ONum VARCHAR(255) NULLABLE,                  -- Order Number (PO)
    CMT DECIMAL(10,2) NULLABLE,                  -- CMT (Cut, Make, Trim cost)
    Qty UNSIGNED INT DEFAULT 0,                  -- Quantity
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);
```

---

## Database Relationships

### Foreign Key Relationship
```
mtp.CU ──→ ocs.CS
```

**Purpose**: Links each masterplan record to its OCS order/cutting data

### Key Fields Mapping (in Queries)
When querying masterplan data, the following fields are joined from OCS:
```php
'mtp.*'              // All masterplan fields
'ocs.SNo as Style'   // Style Number from OCS
'ocs.ONum as PO'     // Order/PO Number from OCS
'ocs.Qty'            // Total OCS Quantity
```

---

## MTP Table Fields - Detailed Breakdown

### Identification Fields
| Field | Type | Purpose |
|-------|------|---------|
| CU | string | Customer Unit / Code (links to OCS.CS) |
| Line | string | Production Line Name |
| LineColor | hex | Line color representation (e.g., #0000FF for blue) |

### Quantity Fields
| Field | Type | Purpose |
|-------|------|---------|
| Qty_dis | int | **Quantity distributed** - portion allocated to this line |
| ExQty | int | Extra quantity beyond Qty_dis |

### Material & Supply Chain Fields
| Field | Type | Purpose |
|-------|------|---------|
| Fabric1 | string | Primary fabric type |
| ETA1 | date | Estimated arrival date for Fabric1 |
| Actual | date | Actual received date |
| Fabric2 | string | Secondary fabric type |
| ETA2 | date | Estimated arrival date for Fabric2 |
| Linning | string | Lining material |
| ETA3 | date | Estimated arrival date for Lining |
| Pocket | string | Pocket material |
| ETA4 | date | Estimated arrival date for Pocket |
| Trim | string | Trim/finishing material |

### Process & Timeline Fields
| Field | Type | Purpose |
|-------|------|---------|
| inWHDate | date | In Warehouse Date |
| 3rd_PartyInspection | string | Third party inspection status/notes |
| ShipDate2 | date | Secondary/alternate ship date |
| FirstOPT | date | First OPT (On-time Performance) date |
| lt | int | Lead Time (in days) |
| SoTK | string | Sales Order to Keep value |

---

## OCS Table Fields - Detailed Breakdown

### Key Identifiers
| Field | Type | Purpose |
|-------|------|---------|
| CS | string | Customer Style ID (UNIQUE - primary link to MTP.CU) |
| SNo | string | **Style Number** (displayed as "Style" in MTP view) |
| ONum | string | **Order/PO Number** (displayed as "PO" in MTP view) |

### Order Details
| Field | Type | Purpose |
|-------|------|---------|
| Sname | string | Style Name/Description |
| Customer | string | Customer Name |
| Color | string | Color specification |
| CsDate | date | Customer Style Date |
| CMT | decimal | CMT cost (Cut, Make, Trim) |
| Qty | int | **Total Quantity** from this OCS record |

---

## Key Business Rules & Constraints

### 1. Quantity Distribution Rule
- Sum of all `mtp.Qty_dis` for a given `CU` cannot exceed the `ocs.Qty` for that CS
- **Validation**: When creating/updating MTP records
```
SUM(mtp.Qty_dis WHERE mtp.CU = X) ≤ ocs.Qty WHERE ocs.CS = X
```

### 2. ExQty Constraint
- `mtp.ExQty` cannot exceed `mtp.Qty_dis`
- **Validation**: ExQty must be ≤ Qty_dis when both are filled

### 3. FirstOPT Constraint
- `FirstOPT` cannot be a Sunday (must be weekday)
- **Validation**: Rejects Sunday dates

### 4. Line-Color Relationship
- Lines are categorized as either 'GSV' (color lines) or 'SUBCON' (subcontract lines)
- Categories stored in `colors` table with hex color codes
- Lines are displayed with priority: Blue (1) → Yellow (2) → Green (3) → Orange (4)

---

## Query Pattern Example

### Fetch Masterplan with Related OCS Data
```php
$plan = DB::table('mtp')
    ->leftJoin('ocs', 'mtp.CU', '=', 'ocs.CS')
    ->select(
        'mtp.*',
        'ocs.SNo as Style',
        'ocs.ONum as PO',
        'ocs.Qty'
    )
    ->orderBy('mtp.Line', 'asc')
    ->get();
```

**Result Fields Available**:
- From `mtp`: id, CU, Line, LineColor, Fabric1, ETA1, Actual, Fabric2, ETA2, Linning, ETA3, Pocket, ETA4, Trim, inWHDate, 3rd_PartyInspection, ShipDate2, SoTK, ExQty, lt, FirstOPT, Qty_dis, created_at, updated_at
- From `ocs` (aliased): Style (from SNo), PO (from ONum), Qty

---

## Location Reference

### Controller
- [app/Http/Controllers/MasterPlanController.php](app/Http/Controllers/MasterPlanController.php) - Handles all MTP operations

### Test Schema Definition
- [tests/Support/CreatesLegacySchema.php](tests/Support/CreatesLegacySchema.php) - Schema setup with createMtpTable()

### Blade Views
- [resources/views/admin/masterplan/masterplan.blade.php](resources/views/admin/masterplan/masterplan.blade.php) - Main view
- [resources/views/admin/masterplan/addmaster.blade.php](resources/views/admin/masterplan/addmaster.blade.php) - Add form
- [resources/views/admin/masterplan/editmaster.blade.php](resources/views/admin/masterplan/editmaster.blade.php) - Edit form

### OCS Import
- [app/Imports/OCSImport.php](app/Imports/OCSImport.php) - Handles Excel import for OCS data

---

## Current Architecture Note

**No Eloquent Model Exists**: This application uses a legacy query approach with `DB::table()` directly instead of Eloquent ORM models. The schema is managed in test support files, suggesting this may be a migration in progress or a legacy system being maintained.

To add proper Eloquent models, you would create:
- `app/Models/Mtp.php` - for masterplan
- `app/Models/Ocs.php` - for order cutting schedule
- `app/Models/Color.php` - for line colors
- `app/Models/Holiday.php` - for holidays

# Hướng dẫn UAT chi tiết – chạy toàn bộ luồng ERP

Chạy trên dữ liệu UAT riêng, ví dụ `UAT-CS-002`. Không đóng, huỷ, xuất kho hoặc chỉnh tồn trên đơn hàng thật.

## A. Thứ tự bắt buộc

```text
Line Color → Sewing Line → BOM/Colorway → OCS → Master Plan
→ MRP → Supplier/PO → Receipt (tạo tồn) → Production Planning
→ Shop Floor → Issue kho → Finance/Close → Audit
```

Một Requisition có thể được tạo ngay khi OCS Released, nhưng Inventory chỉ có dữ liệu sau khi nhận hàng/ghi nhận tồn vật lý.

## 1. Master data: Line và kho

1. Vào **Line Colors**, xác nhận line dùng để test (ví dụ `Blue`) có `is_active = 1`.
2. Vào `/admin/production-planning`, tạo capacity profile:

| Field | Test value |
|---|---|
| Line code | `GSV-BLUE` |
| Line Color | `Blue` |
| Workers | `20` |
| Hours/day | `8` |
| Labor rate | `30000` |

3. Vào **Inventory → Warehouses** tạo kho `UAT-WH`, type `raw_material`; sau đó tạo location `A-01-01` thuộc kho đó.

**PASS:** Line và kho/location xuất hiện trong danh sách. Không tạo trùng Line code hoặc Location code.

## 2. BOM & Tech Pack

1. Vào **BOM & Tech Pack → Create BOM**.
2. Tạo BOM style trùng style OCS (ví dụ `UAT-1001`), thêm item vật tư `UAT-FAB-RED`:

| Field | Value |
|---|---:|
| Type | Fabric |
| Unit | M |
| Yield / Consumption | `1.0000` |
| Waste % | `3` |
| Unit price | `50000` |

3. Lưu, mở BOM, cập nhật status thành **Active** nếu cần.
4. Trong **Garment-to-material colorways** chọn BOM material, nhập Garment color `RED`, Material color `RED`, Change reason, rồi Save mapping.
5. Lưu Tech Pack với JSON hợp lệ nếu cần test Tech Pack.

**PASS:** Không thể lưu Yield ≤ 0, waste/price âm, hoặc thiếu Material Code/Name. Colorway được hiển thị trong bảng.

## 3. OCS và queue Requisition

1. Vào **Order Cut Sheet → Add**. Tạo `UAT-CS-002`, chọn style đúng BOM, Color `RED`, Qty `50`, size breakdown tổng đúng `50`, gắn BOM Active.
2. Tại danh sách chuyển đúng thứ tự: `pending → confirmed → released`; nhập reason khi hệ thống hỏi.
3. Trong terminal project chạy một lần:

```powershell
php artisan queue:work --once --tries=3
```

4. Kiểm tra phpMyAdmin: `ocs.requisition_job_status` phải `completed`; `material_requisitions` và `requisition_items` phải có dòng cho CS này.

```sql
SELECT ri.material_color, ri.requested_qty, ri.issued_qty
FROM requisition_items ri JOIN material_requisitions r ON r.id=ri.requisition_id
JOIN ocs o ON o.id=r.cutsheet_id WHERE o.CS='UAT-CS-002';
```

**PASS:** material_color là `RED`; requested qty = `Qty × Yield × (1 + Waste% / 100)`; issued_qty = 0.

## 4. Master Plan và MRP

1. Vào **Master Plan → Add**, chọn CU `UAT-CS-002`, Line `Blue`, Qty_dis `50`, MPS status `Planned`, Daily target `10`, các ngày cut/sew hợp lệ. Lưu.
2. Vào **MRP → Create**, chọn MTP record vừa tạo, period bao phủ ngày kế hoạch, bấm **Calculate MRP**.
3. Mở MRP result; kiểm tra Gross requirement, available/on-order, net requirement và lot allocation proposal.

**PASS:** Không tạo MRP suggestion trùng khi chạy lại. MRP có Net requirement khi chưa có tồn khả dụng.

## 5. Supplier, PO và Receipt

1. Vào **Procurement → Suppliers**, tạo `UAT Supplier A` active.
2. Từ MRP tạo PO hoặc chọn **Create PO**; chọn Supplier, material, quantity ≥ Net requirement, Expected delivery phù hợp.
3. Mở PO, đổi status thành `confirmed`.
4. Bấm **Receive goods**, chọn `UAT-WH`, location `A-01-01`, Lot `UAT-LOT-01`, quantity ví dụ `60`, rồi Post receipt.
5. Vào **Inventory** và **Transactions**, filter `UAT-FAB-RED`.

**PASS:** Inventory hiển thị tồn theo lot/location; Transaction có `IN`; PO thành Partial/Received. Receipt lớn hơn outstanding bị chặn.

## 6. Production Planning: WO và schedule

1. Vào `/admin/production-planning`; tạo Work Order từ `UAT-CS-002`, Planned Qty `50`, SMV `10`.
2. Tạo MPS Schedule: WO vừa tạo, `GSV-BLUE`, Start `2026-09-01`, End `2026-09-05`, Daily target `10`.
3. Split WO bằng `20`; WO cũ còn 30 và WO mới 20.
4. Đổi status WO: `planned → released → in_production → completed`.

**Negative tests:** tạo WO vượt Qty OCS; target một ngày `1000` với 20 workers/8h/SMV 10; start date trước ETA PO. Tất cả phải bị chặn.

## 7. Shop Floor Control

1. WO phải `released` hoặc `in_production`. Vào **Shop Floor → MPS Logs**.
2. Chọn schedule, lưu Cut: target 50, actual 40, defect 0; thêm detail `RED`, size `M`, qty 40.
3. Lưu Sewing actual 30, rồi Finishing actual 20. Nhập workers/hours/labor rate để test actual labor.
4. Mở Dashboard, WIP, Efficiency.

**PASS:** WIP Sewing = Cut good qty − Sewing good qty = 10. Sewing/Finishing vượt công đoạn trước bị chặn. Detail total phải bằng actual − defects.

## 8. Xuất kho và kiểm kê

1. Vào **Stock Counts**, tạo count lệch tồn, nhập reason, Submit và Approve.
2. Kiểm tra Inventory Transactions có `ADJUSTMENT` và balance thay đổi đúng.

> Màn hình **Requisitions & Issue** đã có tại **Inventory → Requisitions & Issue** (`/admin/inventory/requisitions`). Chọn requisition, chọn đúng lot/vị trí còn tồn phù hợp với vật tư–màu–size, nhập số lượng rồi bấm **Post issue**. Lịch sử phiếu xuất hiển thị ngay bên dưới cùng màn hình.

**PASS phần kiểm kê:** Stock count sinh `ADJUSTMENT`, không sửa balance trực tiếp. **PASS luồng Issue:** Post issue thành công tạo giao dịch `OUT`, giảm tồn lô tương ứng và tăng `issued_qty` của requisition item.

## 9. Finance và close snapshot

1. Vào Finance → FOB Costs, thêm freight/QC/packing/overhead cho CS.
2. Vào Cost Analysis, calculate; kiểm tra actual material từ issue, actual labor từ Shop Floor và variance.
3. Chuyển OCS: `in_production → completed → closed`.
4. Đổi giá vật tư sau Close và mở Order Costing lại.

**PASS:** Snapshot costing của đơn Closed không đổi; Audit có status/cost events.

## 10. Phân quyền và Regression

1. Đăng nhập role Warehouse/PPIC/Production/Finance, thử truy cập module ngoài quyền.
2. Mở **Audit Trail**, filter CS UAT.
3. Test OCS Import Excel, sticky columns Master Plan và các table ngang.

**PASS:** Route trái quyền bị chặn; audit có actor/action/reason; Import chỉ nhận `.xlsx/.xls` ≤ 2 MB.

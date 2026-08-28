# Kịch bản UAT theo luồng nghiệp vụ — ERP May mặc

Tài liệu này kiểm thử thủ công toàn bộ chuỗi nghiệp vụ. Thực hiện **theo đúng thứ tự** vì dữ liệu của bước trước là đầu vào của bước sau.

## 1. Quy ước

- Chỉ dùng dữ liệu có tiền tố `UAT-`; không dùng đơn hàng/kho thật.
- Đánh dấu PASS chỉ khi đạt toàn bộ kỳ vọng. Nếu FAIL, ghi Test ID, ảnh, URL và dữ liệu nhập.
- Không chỉnh trực tiếp `inventory_balances`, `inventory_transactions`, `requisition_items` hay `order_costings` trong database; chỉ dùng database để đối chiếu.

## 2. Dữ liệu chuẩn bị

| Hạng mục | Dữ liệu đề nghị |
|---|---|
| Customer | `UAT Customer A` |
| Material | `UAT-FAB-RED`, type Fabric, unit `M`, color `RED` |
| Supplier | `UAT Supplier A`, active, lead time 7 ngày |
| Warehouse/Location | `UAT-WH` / `A-01-01` |
| Sewing line | `UAT-LINE-01` / `UAT Line 01`, 20 workers, 8 hours/day |
| BOM | style `UAT-1001`, version `V1`, Active |
| BOM item | `UAT-FAB-RED`, consumption `1.50 M/pcs`, wastage `3%` |
| Colorway | garment `RED` → material `RED` |
| OCS | `UAT-CS-001`, PO `UAT-PO-001`, qty `100`, color `RED` |
| Receipt lot | `UAT-LOT-001` tại `UAT-WH / A-01-01` |

Gross material kỳ vọng: `100 × 1.50 × 1.03 = 154.50 M`.

## 3. Khởi động Queue/Scheduler trước khi test

Mở hai terminal tại thư mục project:

```powershell
php artisan queue:work database --tries=3
```

```powershell
php artisan schedule:work
```

Queue tạo Requisition khi OCS được `released`; scheduler chạy MRP định kỳ và dispatch outbox event. Khi chỉ test một job, dùng `php artisan queue:work --once --tries=3`.

## 4. Checklist tổng quan

| ID | Luồng | Kết quả |
|---:|---|---|
| WF-00 | Master data nền | ☐ PASS / ☐ FAIL |
| WF-01 | BOM, Tech Pack, Colorway, Clone | ☐ PASS / ☐ FAIL |
| WF-02 | OCS, size breakdown, release | ☐ PASS / ☐ FAIL |
| WF-03 | Requisition tự động và MRP | ☐ PASS / ☐ FAIL |
| WF-04 | Procurement, ETA, PO receipt | ☐ PASS / ☐ FAIL |
| WF-05 | Warehouse issue, ledger, stock count | ☐ PASS / ☐ FAIL |
| WF-06 | MPS, Work Order, capacity | ☐ PASS / ☐ FAIL |
| WF-07 | Shop Floor, WIP, downtime | ☐ PASS / ☐ FAIL |
| WF-08 | Finance, expense, costing, snapshot | ☐ PASS / ☐ FAIL |
| WF-09 | Audit, quyền và regression UI | ☐ PASS / ☐ FAIL |

---

## WF-00 — Master data nền

| Bước | Thao tác | Kỳ vọng | Kết quả |
|---:|---|---|---|
| 1 | **Customer Master**: tạo `UAT Customer A`. | Chọn được khi tạo OCS/BOM. | ☐ |
| 2 | **Material Master**: tạo `UAT-FAB-RED`, Fabric, unit M, color RED. | Material active, không trùng code. | ☐ |
| 3 | Gắn `UAT Supplier A` làm default material vendor, lead time 7 ngày. | Mapping material–supplier đúng. | ☐ |
| 4 | **Inventory → Warehouses**: tạo `UAT-WH`, location `A-01-01`. | Chọn được khi receipt/issue. | ☐ |
| 5 | **Production Planning**: tạo `UAT-LINE-01`, `UAT Line 01`, 20 workers, 8h/day. | Line active, chọn được khi lập MPS. | ☐ |
| 6 (âm) | Thử tạo code customer/material/line trùng. | Bị validation; không có record trùng. | ☐ |

## WF-01 — BOM, Tech Pack, Colorway, Clone

| Bước | Thao tác | Kỳ vọng | Kết quả |
|---:|---|---|---|
| 1 | **BOM & Tech Pack** → Create BOM: `UAT-1001`, V1. | BOM tạo ở Draft. | ☐ |
| 2 | Thêm `UAT-FAB-RED`, consumption 1.50, wastage 3%. | Item, đơn vị, chi phí đúng. | ☐ |
| 3 | Thêm colorway `RED` → `RED`; nhập Tech Pack/size nếu form hỗ trợ. | Colorway/Tech Pack lưu và hiển thị. | ☐ |
| 4 | Chuyển BOM sang Active. | Chọn được cho OCS cùng style. | ☐ |
| 5 | Clone `UAT-1001` sang `UAT-1002`. | BOM đích có item/colorway; BOM nguồn không đổi. | ☐ |
| 6 (âm) | Lưu consumption 0/âm, thiếu material, hoặc gắn BOM Draft/khác style. | Bị chặn, không tạo dữ liệu sai. | ☐ |

## WF-02 — Order Cut Sheet và phát lệnh

| Bước | Thao tác | Kỳ vọng | Kết quả |
|---:|---|---|---|
| 1 | **Order Cut Sheet** → Add: `UAT-CS-001`, PO `UAT-PO-001`, Customer UAT, style `UAT-1001`, color RED, qty 100, ship date tương lai, BOM Active. | OCS lưu; hiển thị BOM đã gắn. | ☐ |
| 2 | Nhập size breakdown M = 100. | Tổng size = Qty 100. | ☐ |
| 3 | Đổi status `pending → confirmed → released`. | Chỉ chuyển hợp lệ; có thông báo thành công. | ☐ |
| 4 | Chờ queue, mở **Inventory → Requisitions & Issue**. | Có requisition của CS; material/color RED; requested ≈154.50; issued=0. | ☐ |
| 5 (âm) | Tạo OCS có tổng size ≠ Qty hoặc `pending → completed`. | Bị chặn; không lưu dở dang/nhảy status. | ☐ |
| 6 (âm) | Khi OCS `in_production`/`closed`, thử sửa/xóa Qty hoặc size. | Bị khóa, dữ liệu kế hoạch/kho không đổi. | ☐ |

**Đối chiếu khi cần:** `ocs.requisition_job_status = completed`; requisition ban đầu `pending`.

## WF-03 — MRP và reservation

| Bước | Thao tác | Kỳ vọng | Kết quả |
|---:|---|---|---|
| 1 | Vào **MRP**, bấm Calculate/Run MRP cho UAT. | Suggestion `UAT-FAB-RED`: Gross ≈154.50; Net = Gross − Available. | ☐ |
| 2 | Chạy lại MRP không đổi dữ liệu. | Cập nhật/idempotent, không sinh suggestion trùng. | ☐ |
| 3 | Kiểm tra On Hand, Allocated, On Order, Available. | `Available = On hand − Allocated + On order`. | ☐ |
| 4 | Trên CS UAT phụ, cancel requisition trước khi issue. | Reservation được hoàn, không âm tồn. | ☐ |
| 5 (âm) | Tạo nhu cầu vượt available hoặc reserve cùng lot song song. | Không reserve quá mức, không có balance âm. | ☐ |

## WF-04 — Procurement, ETA và PO receipt

| Bước | Thao tác | Kỳ vọng | Kết quả |
|---:|---|---|---|
| 1 | Từ MRP chọn suggestion của `UAT Supplier A` → Create PO. | PO/PO item liên kết đúng suggestion. | ☐ |
| 2 | Mở PO, đổi Draft → Confirmed, đặt ETA sau hôm nay 3 ngày. | Status/ETA lưu; MPS đọc được material readiness. | ☐ |
| 3 (âm) | Thử lập MPS start trước ETA. | Bị chặn hoặc cảnh báo rõ ràng. | ☐ |
| 4 | Receive goods: 100 M, lot `UAT-LOT-001`, `UAT-WH/A-01-01`, DN `UAT-DN-001`. | Receipt + IN transaction; tồn lot tăng 100; PO partial/open. | ☐ |
| 5 | Receive phần còn lại. | Tổng receipt không vượt PO; PO chuyển status đúng. | ☐ |
| 6 (âm) | Receive vượt outstanding, thiếu lot/location, hoặc sai material. | Bị chặn; receipt/tồn cũ không đổi. | ☐ |

## WF-05 — Issue, ledger và stock count

| Bước | Thao tác | Kỳ vọng | Kết quả |
|---:|---|---|---|
| 1 | **Inventory → Requisitions & Issue**, mở requisition `UAT-CS-001`. | Item RED, requested 154.50 và lot phù hợp được hiển thị. | ☐ |
| 2 | Post Issue 100 M từ `UAT-LOT-001/A-01-01`. | Material Issue + Issue Item + OUT transaction; balance −100; requisition Partial. | ☐ |
| 3 | Nhập thêm lot, issue phần còn lại đến đủ 154.50 M. | `issued_qty ≥ requested_qty`; requisition Completed; outbox event pending/delivered. | ☐ |
| 4 | Mở **Transactions** và **Report**, lọc `UAT-FAB-RED`. | IN/RESERVE/OUT đúng reference, đúng lot/location. | ☐ |
| 5 | **Stock Counts**: tạo count lệch, nhập lý do, Approve. | Tạo ADJUSTMENT/audit, không sửa trực tiếp balance. | ☐ |
| 6 (âm) | Issue > Available; chọn sai lot/location/color/size; chọn item khác requisition. | Bị chặn hoàn toàn, không có OUT/Issue/đổi issued qty. | ☐ |

## WF-06 — MPS, Work Order và capacity

| Bước | Thao tác | Kỳ vọng | Kết quả |
|---:|---|---|---|
| 1 | **Master Plan**: chọn UAT-CS-001, UAT Line 01, Qty 100, Cut/Sew date sau ETA/material ready. | MPS Planned liên kết đúng CS/line/date. | ☐ |
| 2 | Tạo Work Order từ MPS/OCS, SMV 1.0. | WO liên kết đúng CS/schedule. | ☐ |
| 3 | Split WO thành 60 và 40. | Tổng Qty WO con =100, không vượt WO gốc. | ☐ |
| 4 | Đổi WO `planned → released → in_production`. | Không nhảy status; xuất hiện ở Shop Floor. | ☐ |
| 5 (âm) | Lập schedule trước ETA hoặc tổng phân bổ >100. | Bị chặn/cảnh báo; không lưu schedule sai. | ☐ |
| 6 (âm) | Nhập daily target vượt capacity của line. | Bị từ chối/cảnh báo over-capacity. | ☐ |

## WF-07 — Shop Floor, WIP và downtime

| Bước | Thao tác | Kỳ vọng | Kết quả |
|---:|---|---|---|
| 1 | **Shop Floor → MPS Logs**, chọn schedule/WO `in_production`. | Schedule được phép nhập log. | ☐ |
| 2 | Cut: target 100, actual 80, defect 0; detail RED/M =80; nhập workers/hours/labor rate. | Log/detail/labor/efficiency được lưu. | ☐ |
| 3 | Sewing actual 60; Finishing actual 50. | WIP Sewing = 80−60=20; Finish không vượt Sew. | ☐ |
| 4 | Sewing thêm 20; Finishing thêm 30. | Tổng Cut=Sew=Finish=80; WIP Sewing=0. | ☐ |
| 5 | **Downtime**: nhập 30 phút và lý do. | Downtime log lưu đúng line/ngày. | ☐ |
| 6 (âm) | Sew > Cut, Finish > Sew, hoặc total detail ≠ actual−defect. | Bị chặn, không ghi over-production. | ☐ |

## WF-08 — Finance, Expense, Costing và Snapshot

| Bước | Thao tác | Kỳ vọng | Kết quả |
|---:|---|---|---|
| 1 | **Finance → Expenses**: Utility 1,000,000 VND, mô tả rõ. | Expense xuất hiện đúng tháng/category. | ☐ |
| 2 | **Finance → FOB Costs**: thêm freight/QC/packing cho UAT-CS-001. | Component gắn đúng order/amount. | ☐ |
| 3 | Bấm Calculate Cost Analysis. | Actual material từ issue/receipt; labor từ Shop Floor; variance hiển thị. | ☐ |
| 4 | Mở Order Costings/Profitability. | Có estimated vs actual theo material/labor/FOB và lợi nhuận. | ☐ |
| 5 | Hoàn thành sản xuất, đổi OCS `in_production → completed → closed`. | Snapshot `order_costings` và audit được tạo. | ☐ |
| 6 | Đổi giá material/thêm expense sau close, xem lại UAT-CS-001. | Snapshot order đã closed không đổi. | ☐ |

## WF-09 — Audit, phân quyền và regression UI

| Bước | Thao tác | Kỳ vọng | Kết quả |
|---:|---|---|---|
| 1 | **Audit Trails**: lọc `UAT-CS-001`/`UAT-FAB-RED`. | Có actor, thời điểm, action, before/after hoặc reason. | ☐ |
| 2 | Nếu có role PPIC/Warehouse/Production/Finance, thử mở module không thuộc quyền. | Route trái quyền bị chặn; quyền hợp lệ hoạt động. | ☐ |
| 3 | Test Import Excel OCS: file đúng, file sai định dạng, file thiếu cột bắt buộc. | File đúng import; file sai validation, không tạo OCS dở dang. | ☐ |
| 4 | Mở Master Plan ở 1366px/1920px, cuộn ngang. | Button đồng đều, Edit/Delete neo phải, không che dữ liệu. | ☐ |

## 5. Điều kiện nghiệm thu

Chỉ nghiệm thu khi WF-00 đến WF-08 PASS và đồng thời:

- Không có tồn kho âm.
- Receipt, reserve, issue, adjustment đều có ledger/transaction tương ứng.
- Không có requisition/issue dở dang do lỗi transaction.
- Sewing/Finishing không vượt công đoạn trước.
- OCS closed giữ nguyên snapshot khi giá vật tư/expense thay đổi.

## 6. Mẫu ghi nhận lỗi

| Test ID | Bước | Dữ liệu | Thực tế | Kỳ vọng | Ảnh/URL | Mức độ |
|---|---:|---|---|---|---|---|
| WF-xx |  |  |  |  |  | Critical / High / Medium / Low |

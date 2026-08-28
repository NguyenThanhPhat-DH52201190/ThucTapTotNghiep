# Kịch bản UAT – ERP May mặc

Tài liệu này dùng để test thủ công theo từng luồng nghiệp vụ trên môi trường test/staging. Test theo đúng thứ tự bên dưới vì dữ liệu của luồng sau phụ thuộc luồng trước.

## 1. Quy ước thực hiện

- Mỗi lần test dùng mã riêng, ví dụ: `UAT-CS-001`, style `UAT-1001`, PO `UAT-PO-001`, vật tư `UAT-FAB-RED`.
- Ghi lại mã bản ghi vừa tạo để đối chiếu ở các bước sau. Không dùng đơn hàng thật để test huỷ, xuất kho hoặc đóng đơn.
- Với các thao tác tạo qua queue (release đơn hàng), queue worker phải chạy. Nếu queue không chạy, trạng thái job sẽ không chuyển hoàn tất dù dữ liệu đầu vào đúng.
- Mỗi dòng **Kỳ vọng** phải đạt trước khi chuyển sang bước kế tiếp. Đánh dấu `PASS` hoặc `FAIL` vào cột kết quả.

## 2. Dữ liệu chuẩn bị chung

| Hạng mục | Dữ liệu UAT đề nghị |
|---|---|
| Người dùng | Admin; PPIC; Warehouse; Production; Finance (nếu đã có các vai trò này) |
| Nhà cung cấp | `UAT Supplier A`, lead time 7 ngày |
| Kho/Vị trí | Kho `UAT-WH`, vị trí `A-01-01` |
| Chuyền may | `UAT Line 01`, có workers, working hours và SMV hợp lệ |
| Vật tư | `UAT-FAB-RED`, đơn vị M, có nhà cung cấp mặc định |
| BOM | Style `UAT-1001`, màu garment `RED`, định mức 1.50 M/pcs, hao hụt 3% |
| Đơn hàng | `UAT-CS-001`, Qty 100, Color `RED`, ship date trong tương lai |

## 3. Checklist chạy nhanh

| # | Workflow | Kết quả |
|---:|---|---|
| 01 | BOM & Tech Pack | ☐ PASS / ☐ FAIL |
| 02 | Order Cut Sheet và workflow status | ☐ PASS / ☐ FAIL |
| 03 | MPS, Work Order và capacity | ☐ PASS / ☐ FAIL |
| 04 | MRP và reservation | ☐ PASS / ☐ FAIL |
| 05 | Procurement, ETA và receipt | ☐ PASS / ☐ FAIL |
| 06 | Warehouse: issue, lot/location, stock count | ☐ PASS / ☐ FAIL |
| 07 | Shop Floor, WIP và actual labor | ☐ PASS / ☐ FAIL |
| 08 | Finance, actual cost, variance và snapshot | ☐ PASS / ☐ FAIL |
| 09 | Phân quyền và audit trail | ☐ PASS / ☐ FAIL |
| 10 | Regression UI / Import / sticky columns | ☐ PASS / ☐ FAIL |

---

## Workflow 01 — BOM & Tech Pack

**Mục tiêu:** BOM có thể dùng lại cho OCS/MRP, colorway map đúng theo màu đơn hàng và Tech Pack được kiểm soát.

| Bước | Thao tác | Kỳ vọng | Kết quả |
|---:|---|---|---|
| 1 | Đăng nhập Admin hoặc IE, mở `/bom`, chọn **Create BOM**. | Mở được form tạo BOM. | ☐ |
| 2 | Tạo BOM style `UAT-1001`, version `V1`, trạng thái Active; thêm material `UAT-FAB-RED`, consumption `1.50`, wastage `3%`. | BOM lưu thành công; tổng chi phí và dòng vật tư hiển thị đúng. | ☐ |
| 3 | Thêm colorway: garment color `RED` → material color phù hợp. | Colorway lưu, hiển thị trong BOM. | ☐ |
| 4 | Nhập Tech Pack gồm size/colorway hợp lệ và đính kèm tệp/ảnh nếu form hỗ trợ. | Tech Pack lưu; version/status approval hiển thị đúng. | ☐ |
| 5 | Dùng **Clone BOM** từ `UAT-1001` sang `UAT-1002`. | BOM mới có các item/colorway giống BOM nguồn; không làm thay đổi BOM nguồn. | ☐ |
| 6 (âm) | Thử lưu consumption bằng 0/âm hoặc thiếu vật tư. | Hệ thống từ chối và không tạo BOM sai. | ☐ |

**Điểm đối chiếu:** BOM dùng được ở form OCS chỉ khi Active; mã style và BOM được gắn đúng.

## Workflow 02 — Order Cut Sheet và chuyển trạng thái

**Mục tiêu:** đơn hàng là đầu vào duy nhất; status đi theo chuỗi nghiệp vụ và release sinh requisition qua queue.

| Bước | Thao tác | Kỳ vọng | Kết quả |
|---:|---|---|---|
| 1 | Mở `/order-cutsheet` → **Add**. Tạo `UAT-CS-001`, style `UAT-1001`, màu `RED`, Qty 100, size breakdown tổng bằng 100, gắn BOM Active. | Lưu thành công; dòng OCS hiện BOM đã gắn. | ☐ |
| 2 | Mở lại form Edit. | Status chỉ đọc tại form Edit; không đổi status trực tiếp ở đây. | ☐ |
| 3 | Đổi status tại danh sách: `pending → confirmed → released`; nhập lý do khi được hỏi. | Mỗi lần chỉ cho phép chuyển tiếp hợp lệ; có thông báo thành công. | ☐ |
| 4 | Chờ queue xử lý rồi mở Requisition/Inventory. | Requisition được tạo từ BOM, với màu vật tư theo colorway `RED`; job status completed. | ☐ |
| 5 | Đổi tiếp `released → in_production → completed → closed`. | Chỉ nhận chuyển trạng thái theo thứ tự; Closed tạo snapshot costing. | ☐ |
| 6 (âm) | Thử chuyển `pending → completed`, hoặc sửa/xoá khi In Production/Completed. | Bị chặn; dữ liệu OCS và size breakdown không đổi. | ☐ |
| 7 (âm) | Tạo OCS với tổng size khác Qty hoặc BOM không Active/khác style. | Validation báo lỗi; không tạo dữ liệu dở dang. | ☐ |

### Cách chạy và kiểm tra job tạo Requisition

Môi trường hiện dùng `QUEUE_CONNECTION=database`. Sau khi thực hiện bước chuyển status sang `released`, mở một terminal tại thư mục project và chạy một lần:

```powershell
php artisan queue:work --once --tries=3
```

Lệnh này xử lý một job rồi tự dừng, phù hợp để UAT. Khi chạy liên tục trong môi trường phát triển, dùng `php artisan queue:work --tries=3` và giữ terminal đó mở.

Sau khi worker chạy xong, xác nhận bằng công cụ quản trị MySQL (phpMyAdmin/HeidiSQL) với mã CS UAT:

```sql
SELECT o.CS, o.status, o.requisition_job_status, o.requisition_job_error,
       r.requisition_code, r.status AS requisition_status
FROM ocs o
LEFT JOIN material_requisitions r ON r.cutsheet_id = o.id
WHERE o.CS = 'UAT-CS-001';

SELECT ri.material_color, ri.requested_qty, ri.issued_qty
FROM requisition_items ri
JOIN material_requisitions r ON r.id = ri.requisition_id
JOIN ocs o ON o.id = r.cutsheet_id
WHERE o.CS = 'UAT-CS-001';
```

- Thành công: `requisition_job_status = completed`, có một `requisition_code`, requisition status ban đầu là `pending`, và `material_color` là `RED` (hoặc màu đã map trong colorway).
- Đang chờ: `requisition_job_status = queued` nghĩa là worker chưa chạy hoặc chưa lấy job.
- Thất bại: `requisition_job_status = failed`; xem `requisition_job_error` và bảng `failed_jobs`. Nguyên nhân thường là BOM chưa Active, BOM sai style, BOM không có item, hoặc color/material master chưa hợp lệ.

> Lưu ý: giao diện hiện chưa có màn hình danh sách requisition hoặc cột trạng thái queue trên OCS; bước xác nhận này cần thực hiện qua database cho đến khi UI đó được bổ sung.

## Workflow 03 — Master Production Schedule, Work Order, Capacity

**Mục tiêu:** xếp lịch hợp lệ theo ngày material-ready/capacity và quản lý Work Order.

| Bước | Thao tác | Kỳ vọng | Kết quả |
|---:|---|---|---|
| 1 | Mở `/master-plan`, tạo Master Plan cho `UAT-CS-001`, chọn `UAT Line 01`, Qty phân bổ ≤ Qty OCS. | Plan hiển thị CU, Line, Qty_dis, ngày kế hoạch và status Planned. | ☐ |
| 2 | Nhập SMV, workers, working hours và lịch cut/sew hợp lệ. | Capacity được tính; không báo quá tải. | ☐ |
| 3 | Tạo Work Order từ OCS/MPS, sau đó thử Split Work Order cho một phần số lượng. | Tổng Qty các Work Order con không vượt Qty gốc; liên kết CS và schedule đúng. | ☐ |
| 4 | Cập nhật status Work Order theo luồng được cho phép. | Status cập nhật thành công và không nhảy cóc trạng thái. | ☐ |
| 5 (âm) | Xếp planned start trước ETA/material-ready cuối cùng. | Bị chặn hoặc hiện cảnh báo rõ ràng. | ☐ |
| 6 (âm) | Phân bổ vượt năng lực chuyền hoặc tổng Qty lớn hơn OCS. | Bị từ chối; không tạo schedule vượt capacity. | ☐ |

## Workflow 04 — MRP và reservation

**Mục tiêu:** Gross − Available = Net; MRP không dùng nhầm on-hand thành available và không sinh đề xuất trùng.

| Bước | Thao tác | Kỳ vọng | Kết quả |
|---:|---|---|---|
| 1 | Tạo/nhập tồn `UAT-FAB-RED`: on-hand 100 M tại `UAT-WH/A-01-01`, lot `UAT-LOT-01`. | Tồn hiện đúng theo kho, vị trí, màu và lot. | ☐ |
| 2 | Chạy **Calculate MRP** cho OCS/plan UAT. | Gross ≈ `100 × 1.50 × 1.03 = 154.5 M`; Available tính sau reserved/allocated; Net suggestion hiển thị đúng. | ☐ |
| 3 | Chạy lại cùng phạm vi, không thay đổi dữ liệu. | Không có suggestion trùng; kết quả được cập nhật/idempotent. | ☐ |
| 4 | Release requisition hoặc thực hiện reserve theo flow. | Ledger có RESERVE, available giảm tương ứng; balance không âm. | ☐ |
| 5 | Cancel requisition trước khi issue. | Ledger có UNRESERVE; available được trả lại. | ☐ |
| 6 (âm) | Tạo nhu cầu lớn hơn available hoặc 2 thao tác reserve cùng một lot. | Không reserve quá số lượng khả dụng; không có balance âm. | ☐ |

## Workflow 05 — Procurement, ETA và Receipt

**Mục tiêu:** chuyển MRP proposal thành PO, theo dõi ETA, nhận hàng nhiều lần và đưa tồn vào kho đúng vị trí/lô.

| Bước | Thao tác | Kỳ vọng | Kết quả |
|---:|---|---|---|
| 1 | Vào Procurement, tạo Supplier `UAT Supplier A` (nếu chưa có) và gán lead time. | Supplier lưu thành công. | ☐ |
| 2 | Chọn nhiều MRP suggestions cùng supplier → **Create PO**. | Một PO được tạo với nhiều PO items; tổng số lượng/giá đúng proposal. | ☐ |
| 3 | Cập nhật ETA một PO item và lưu lý do nếu form yêu cầu. | ETA mới hiển thị; material readiness/MPS được cập nhật hoặc cảnh báo. Audit có bản ghi ETA. | ☐ |
| 4 | Receive lần 1 số lượng 30 M vào `UAT-WH/A-01-01`, lot `UAT-LOT-02`, delivery note `UAT-DN-001`. | Goods receipt và receipt item được tạo; tồn tăng 30 M đúng kho/vị trí/lot; PO vẫn Partial/Open. | ☐ |
| 5 | Receive phần còn lại của PO. | Có thể nhận nhiều lần; tổng received không vượt PO item; PO chuyển trạng thái phù hợp. | ☐ |
| 6 (âm) | Nhận số lượng lớn hơn outstanding hoặc thiếu lot/location. | Bị chặn; tồn và receipt trước đó không đổi. | ☐ |

## Workflow 06 — Warehouse: Requisition, Issue, Ledger và Stock Count

**Mục tiêu:** chỉ xuất đúng vật tư/lô/vị trí, mọi thay đổi tồn đi qua ledger và không có âm kho.

| Bước | Thao tác | Kỳ vọng | Kết quả |
|---:|---|---|---|
| 1 | Mở requisition sinh từ `UAT-CS-001`. Kiểm tra requested qty, material/color/size. | Số lượng tính từ BOM × Qty, đúng colorway; trạng thái Pending/Partial. | ☐ |
| 2 | Issue 30 M từ lot `UAT-LOT-02`, location `A-01-01`, map đúng requisition item. | Phiếu issue, issue item và inventory transaction được tạo trong một lần; balance giảm 30 M; issued_qty tăng 30 M. | ☐ |
| 3 | Issue phần còn lại từ lot còn đủ. | Requisition chuyển Partial rồi Completed khi đủ; event SFC được xếp/gửi qua outbox. | ☐ |
| 4 | Mở Inventory Transactions và Stock Report. | Thấy receipt/reserve/issue theo reference document; số dư theo từng lot/location đúng. | ☐ |
| 5 | Tạo Stock Count khác balance, sau đó approve với lý do. | Không sửa trực tiếp balance; sinh transaction ADJUSTMENT, audit lưu lý do. | ☐ |
| 6 (âm) | Issue > available, issue sai location/lot, hoặc issue item không thuộc requisition. | Bị chặn hoàn toàn; không có transaction/issued_qty/balance nào thay đổi. | ☐ |

## Workflow 07 — Shop Floor Control, WIP và Actual Labor

**Mục tiêu:** ghi nhận sản lượng theo công đoạn/màu/size, chặn over-production và tính actual labor theo worker-hours.

| Bước | Thao tác | Kỳ vọng | Kết quả |
|---:|---|---|---|
| 1 | Từ MPS/Work Order tạo production log cho ngày UAT. | Log liên kết đúng MPS schedule/Work Order. | ☐ |
| 2 | Ghi Cut: target 100, actual 80; khai chi tiết color `RED`/size; nhập workers, working hours, defects. | Daily log và detail lưu; dashboard/WIP hiển thị 80 cut; efficiency và labor cost có dữ liệu. | ☐ |
| 3 | Ghi Sew actual 60 và Finish actual 50. | WIP Sewing = Cut − Sew = 20 (theo báo cáo); luồng công đoạn hiển thị đúng. | ☐ |
| 4 | Ghi tiếp Sew 20, Finish 80. | Không còn WIP Sew nếu Cut=Sew; production log tổng và detail khớp. | ☐ |
| 5 (âm) | Nhập Sew > total Cut hoặc Finish > total Sew. | API/UI từ chối; không ghi log vượt sản lượng công đoạn trước. | ☐ |

## Workflow 08 — Finance, Actual Cost, Variance và Snapshot

**Mục tiêu:** actual cost dựa trên dữ liệu thật, bao gồm FOB components; Close giữ snapshot cố định.

| Bước | Thao tác | Kỳ vọng | Kết quả |
|---:|---|---|---|
| 1 | Vào Finance → FOB Costs, thêm freight, QC, packing, overhead cho `UAT-CS-001`. | Các khoản chi lưu theo Order, loại chi phí và số tiền đúng. | ☐ |
| 2 | Sau khi có receipt/issue và SFC worker-hours, chạy Cost Analysis. | Actual material từ issue/receipt; actual labor từ SFC; FOB components được cộng đúng. | ☐ |
| 3 | Mở Order Costings/Profitability. | Có estimated vs actual và variance theo material/labor/FOB; tổng variance khớp số liệu nguồn. | ☐ |
| 4 | Closed `UAT-CS-001` theo Workflow 02. | Tạo snapshot `order_costings`/audit. | ☐ |
| 5 | Thay đổi giá vật tư hoặc bổ sung chi phí cho dữ liệu sau ngày close. Mở lại báo cáo đơn đã Closed. | Giá trị snapshot của đơn Closed không đổi; báo cáo không bị viết lại theo giá mới. | ☐ |

## Workflow 09 — Phân quyền và Audit Trail

**Mục tiêu:** đúng người thực hiện đúng module và tất cả thao tác trọng yếu truy vết được.

| Bước | Thao tác | Kỳ vọng | Kết quả |
|---:|---|---|---|
| 1 | Đăng nhập PPIC, Warehouse, Production, Finance lần lượt; thử mở module không thuộc vai trò. | Chỉ được thao tác module được cấp; route trái quyền trả forbidden/redirect, không lộ dữ liệu thao tác. | ☐ |
| 2 | Với Warehouse, thử tạo receipt/issue; với PPIC, thử MPS/MRP; Finance xem costing. | Quyền hợp lệ hoạt động; quyền không hợp lệ bị chặn. | ☐ |
| 3 | Mở `/admin/audit-trails`, lọc theo `UAT-CS-001`. | Có actor, thời điểm, action, đối tượng, before/after và reason cho status/ETA/receipt/issue/adjustment. | ☐ |

## Workflow 10 — Regression UI, Import và bảng Master Plan

| Bước | Thao tác | Kỳ vọng | Kết quả |
|---:|---|---|---|
| 1 | Mở Order Cut Sheet. Bấm **Import Excel**. | Panel import mở riêng, không lẫn với filter; nút Import file đang disable. | ☐ |
| 2 | Kéo thả/chọn file `.xlsx` hợp lệ. | Hiển thị tên file và nút Import file được bật. | ☐ |
| 3 | Chọn file sai định dạng hoặc >2 MB. | Backend báo validation; không tạo OCS lỗi. | ☐ |
| 4 | Mở Master Plan, cuộn ngang sang phải. | Các cột neo bên trái giữ nguyên; chỉ `Edit` và `Delete` luôn neo ở mép phải. | ☐ |
| 5 | Thu/phóng trình duyệt và kiểm tra ở độ rộng 1366px/1920px. | Nút filter có chiều cao đồng đều; bảng vẫn cuộn được, không che dữ liệu/nút hành động. | ☐ |

## 4. Mẫu ghi nhận lỗi

| Test ID | Bước lỗi | Dữ liệu dùng | Thực tế | Kỳ vọng | Ảnh/URL | Mức độ |
|---|---:|---|---|---|---|---|
| WFxx-yy |  |  |  |  |  | Critical / High / Medium / Low |

**Quy tắc kết luận:** chỉ nghiệm thu khi các workflow 01–08 đều PASS, không có balance âm, không có issue/receipt thiếu ledger, và đơn Closed vẫn giữ nguyên snapshot chi phí.

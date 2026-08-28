# TODO – Phần còn thiếu theo workflow ERP may mặc

Quy ước ưu tiên: **P0** = có thể sai dữ liệu/tồn kho/tài chính; **P1** = ảnh hưởng vận hành; **P2** = hoàn thiện trải nghiệm hoặc tích hợp.

Quy ước trạng thái: `[x] Hoàn thành`; `[~] Đang làm`; `[ ] Chưa làm`; `[-] Tạm hoãn`.

| Module | Ưu tiên | Hạng mục còn thiếu | Tiêu chí hoàn thành | Trạng thái |
|---|---:|---|---|---|
| Order Management | P1 | Đồng bộ tên miền dữ liệu `ocs` với khái niệm Order Cut Sheet | Thêm model domain `OrderCutsheet` ánh xạ legacy table `ocs`; các module nghiệp vụ dùng `cutsheet_id` | [x] Hoàn thành |
| Order Management | P1 | Hiển thị trạng thái chỉ đọc trên form Edit | Không thể chọn trực tiếp status trong form; chỉ chuyển qua action workflow | [x] Hoàn thành |
| BOM & Tech Pack | P0 | Tạo bảng `bom_colorways` | Map `bom_item_id`, garment color, material color, ghi chú; có FK/index                   | [x] Hoàn thành |
| BOM & Tech Pack | P0 | Áp dụng colorway khi tạo requisition/MRP | Requisition/MRP dùng đúng màu vật tư theo màu của Order                   | [x] Hoàn thành |
| BOM & Tech Pack | P1 | Liên kết BOM với bảng `styles` master | BOM có `style_id`; tạo/cập nhật BOM sẽ đồng bộ master style theo `style_no` | [x] Hoàn thành |
| BOM & Tech Pack | P1 | Hoàn thiện Tech Pack | Validate JSON size/colorway, upload ảnh/mẫu kỹ thuật, version/status approval | [x] Hoàn thành |
| MRP | P0 | Quản lý tồn allocated/reserved thực tế | Khi requisition/MPS được phát hành, tồn được reserve; khi hủy/hoàn tất phải release reserve | [x] Hoàn thành |
| MRP | P0 | Tính Available theo từng material + color + lot khi cần | MRP lưu proposal phân bổ theo lot/roll từ tồn khả dụng; release requisition revalidate và reserve FIFO để bảo đảm số liệu đúng tại thời điểm phát hành | [x] Hoàn thành |
| MRP | P1 | Dùng lịch cắt/MPS để tính required date | Ngày yêu cầu lấy từ planned cut start; order release date lùi theo vendor/material lead time | [x] Hoàn thành |
| MRP | P1 | Chống chạy MRP trùng | Job có unique lock/idempotency; không tạo suggestions trùng cho cùng phạm vi chạy | [x] Hoàn thành |
| Procurement | P1 | Event/notification cho ETA thay đổi | ETA PO item thay đổi cập nhật Material Readiness và có lịch sử/cảnh báo planner | [x] Hoàn thành |
| Procurement | P1 | Hoàn thiện quản lý receipt | Hỗ trợ warehouse, location, lot/roll, delivery note và partial receipt nhiều lần | [x] Hoàn thành |
| Inventory / Warehouse | P0 | Tạo master `locations` và dùng `location_id` | Balance, receipt, issue item liên kết FK đến vị trí kệ | [x] Hoàn thành |
| Inventory / Warehouse | P0 | Tách balance theo warehouse + location + lot + color + size | Không gộp nhầm số dư giữa kho/lô/màu/size | [x] Hoàn thành |
| Inventory / Warehouse | P1 | Reservation ledger | Có transaction type RESERVE/UNRESERVE và audit đầy đủ | [x] Hoàn thành |
| Inventory / Warehouse | P1 | Kiểm kê/chênh lệch | Có stock count, approval; điều chỉnh đi qua ledger và lưu lý do | [x] Hoàn thành |
| Requisition & Issue | P1 | Chuyển release/requisition sang queued job | Release trả kết quả rõ ràng; job retry 3 lần, trạng thái queued/completed/failed và service idempotent | [x] Hoàn thành |
| Requisition & Issue | P1 | Notification/outbox đáng tin cậy | Khi requisition complete, gửi event SFC qua queue/retry, không mất sự kiện khi webhook lỗi | [x] Hoàn thành |
| MPS | P1 | Quản lý capacity theo SMV, manpower và giờ làm | Chặn schedule vượt công suất chuyền theo SMV, workers và working hours | [x] Hoàn thành |
| MPS | P1 | Quản lý Work Order đầy đủ | API tạo, tách và cập nhật trạng thái Work Order; liên kết CS và MPS schedules | [x] Hoàn thành |
| Shop Floor Control | P1 | Actual labor từ worker-hours | Lưu manpower, working hours, SMV; tính efficiency và labor cost theo dữ liệu thực tế | [x] Hoàn thành |
| Shop Floor Control | P2 | Chi tiết sản lượng theo màu/size | Dùng `production_log_details` cho dashboard và đối chiếu đơn hàng | [x] Hoàn thành |
| Finance & Costing | P0 | Tính actual labor thực tế | Không dùng estimated labor làm actual labor; lấy từ SFC/chi phí nhân công | [x] Hoàn thành |
| Finance & Costing | P1 | Hoàn thiện chi phí FOB | Bao gồm vật tư, vận chuyển, QC, packing, overhead và ownership vật tư | [x] Hoàn thành |
| Finance & Costing | P1 | Báo cáo variance | So sánh estimated vs actual theo Order, vật tư, nhân công và nguyên nhân chênh lệch | [x] Hoàn thành |
| Platform / Security | P1 | Phân quyền theo module | Warehouse thực hiện receipt/issue; PPIC MPS/MRP; Finance xem costing; không dồn mọi thao tác cho admin | [x] Hoàn thành |
| Platform / Quality | P1 | Feature tests cho workflow mới | Test release→requisition, allocation, MRP, PO receipt, issue lock, MPS, SFC và close snapshot | [x] Hoàn thành |
| Platform / Quality | P2 | Audit trail chuẩn | Lưu ai thay đổi status, BOM, ETA, receipt, issue và lý do thay đổi | [x] Hoàn thành |

## Thứ tự triển khai khuyến nghị

1. `bom_colorways` + reservation/allocated inventory.
2. Warehouse location master và balance theo kho/lô/vị trí.
3. Actual labor + actual costing/variance.
4. Queue/outbox cho release, MRP và thông báo SFC.
5. Capacity planning, phân quyền và test workflow đầu-cuối.

## UI completion — 2026-08-28

| Module | UI delivered | Status |
|---|---|---|
| Order Management | Customer Master and Customer selector on OCS create/edit | [x] Completed |
| BOM / Procurement | Material Master and Material–Supplier mapping | [x] Completed |
| Inventory / Warehouse | Requisition queue, Material Issue form, cancellation and issue history | [x] Completed |
| Inventory / Warehouse | Edit/activate/deactivate Warehouse and Location | [x] Completed |
| Procurement | Edit and activate/deactivate Supplier | [x] Completed |
| Production Planning | Edit/deactivate Sewing Line; edit/delete MPS Schedule | [x] Completed |
| Shop Floor Control | Downtime entry and downtime history | [x] Completed |
| Platform | Removed invalid resource routes with no controller action | [x] Completed |

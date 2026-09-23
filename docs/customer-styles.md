# Customer Styles

Customer Master → **Styles** trên từng khách hàng để thêm mã Style, tên và ảnh (JPG/PNG/WebP/GIF, tối đa 2 MB). Có thể thay ảnh tại đây; OCS, BOM, NORM và Master Plan tự lấy ảnh mới theo khách hàng + mã Style.

- Mã Style duy nhất trong mỗi khách hàng; hai khách hàng được dùng cùng mã với ảnh khác nhau.
- Tạo/sửa OCS, BOM và Clone BOM: chọn khách hàng rồi chọn Style đã khai báo. Tên Style lấy từ danh mục. BOM gán cho OCS phải cùng khách hàng và Style.
- Không đổi mã Style đã được OCS/BOM sử dụng. Vẫn sửa tên và thay ảnh được.
- Import OCS Excel cần tên khách hàng khớp duy nhất Customer Master và mã Style đã đăng ký. Nếu cần đổi khách hàng/Style của OCS đã có BOM, dùng Edit OCS để chọn lại BOM.
- Ảnh cũ vẫn làm dự phòng nếu Style chưa có ảnh chung. Khi đổi khách hàng/Style, ảnh riêng cũ không được mang sang Style mới.
- Ảnh mới nằm trên disk `local`, thư mục `customer-style-images`, được phục vụ qua route có đăng nhập. Không cần cài thêm thư viện hay tạo public storage link.

## Triển khai

```sh
php artisan migrate --force
php artisan customer-styles:import-existing
php artisan optimize:clear
```

Lệnh import đăng ký Style từ OCS/BOM hiện có, không ghi đè Style đã cấu hình. Nếu chưa có customer_id, chỉ ghép khi tên khớp duy nhất một khách hàng. Dòng không xác định được khách hàng sẽ được báo để xử lý thủ công. Nếu cùng Style có nhiều ảnh cũ, chọn ảnh chung trong Customer Master; lệnh không tự chọn thay người dùng.

Giữ nguyên dữ liệu trong `storage/app/private` (hoặc root đã cấu hình cho disk local) khi deploy; tài khoản chạy PHP cần quyền ghi thư mục đó.

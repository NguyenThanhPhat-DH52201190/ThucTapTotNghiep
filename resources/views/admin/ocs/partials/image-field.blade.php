@include('admin.partials.image-field', ['imageUrl' => !empty($order->image_path) ? route('admin.ocs.image', $order->id, false) : null])

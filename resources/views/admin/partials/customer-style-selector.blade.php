@php
    $styleOptions = \Illuminate\Support\Facades\DB::table('customer_styles')->orderBy('style_no')->get(['id', 'customer_id', 'style_no', 'style_name', 'image_path'])->map(fn ($s) => [
        'customer' => (string) $s->customer_id, 'code' => $s->style_no, 'name' => $s->style_name,
        'image' => $s->image_path ? route('customer-styles.image', $s->id, false) : null,
    ]);
@endphp
<script type="application/json" class="customer-style-options">@json($styleOptions)</script>
@once
@push('scripts')
<script src="{{ asset('js/customer-styles.js') }}?v={{ filemtime(public_path('js/customer-styles.js')) }}"></script>
@endpush
@endonce

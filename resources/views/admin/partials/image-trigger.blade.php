@if(!empty($imageUrl))
    <button type="button" class="order-image-trigger border-0 bg-transparent p-0 text-start" style="font: inherit; color: inherit;"
            data-image-url="{{ $imageUrl }}" aria-label="View image for {{ $imageLabel }}">{{ $imageLabel }}</button>
@else
    {{ $imageLabel }}
@endif

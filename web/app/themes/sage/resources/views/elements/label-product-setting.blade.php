@props([
  'label' => null,
  'icon' => null
])

@php
  $spriteIcon = is_string($icon) ? trim($icon) : null;
  $imageIcon = is_array($icon) ? $icon : null;
@endphp

<div class="text-green-default mb-3 flex items-center gap-2">
  @if ($spriteIcon)
    <svg class="text-gray-3 size-5 shrink-0" aria-hidden="true">
      <use href="{{ get_template_directory_uri() . '/resources/icon/sprite-base.svg' }}#{{ $spriteIcon }}"></use>
    </svg>
  @elseif ($imageIcon)
    <img
      src="{{ $imageIcon['src'] }}"
      alt="{{ ($imageIcon['alt'] ?? '') ?: $label }}"
      @if (($imageIcon['width'] ?? 0) > 0) width="{{ (int) $imageIcon['width'] }}" @endif
      @if (($imageIcon['height'] ?? 0) > 0) height="{{ (int) $imageIcon['height'] }}" @endif
      class="size-5 shrink-0 object-contain"
    />
  @endif
  <span class="max-lg:text-body-14 lg:font-semibold">{{ $label }}</span>
</div>

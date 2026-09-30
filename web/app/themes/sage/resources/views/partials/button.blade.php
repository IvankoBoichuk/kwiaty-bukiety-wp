@php
  use App\Support\Markup;
  $iconSize = Markup::buttonIconSize($size);
@endphp

@if (!empty($text) && !empty($link))
  <a
    href="{{ $link }}"
    target="{{ $target ?? '_self' }}"
    class="{{ Markup::buttonClasses($variant, $size, $showIcon) }}"
  >
    <span>{{ $text }}</span>
  </a>
@endif

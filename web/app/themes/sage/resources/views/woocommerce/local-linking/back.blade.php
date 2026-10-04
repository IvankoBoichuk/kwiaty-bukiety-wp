@if (!empty($backlink['href']))
  <nav class="local-links__block local-links--back" aria-label="{{ __('Back to the voivodeship', 'sage-front') }}">
    <h3 class="text-green-dark mb-3 text-[20px] leading-tight font-bold">
      {{ __('Back to the voivodeship', 'sage-front') }}
    </h3>
    <a href="{{ esc_url($backlink['href']) }}" class="text-green-default text-body-15 font-semibold hover:underline">
      {{
        trim(
          sprintf(__('← Back to %s voivodeship', 'sage-front'), $backlink['woj']),
        )
      }}
    </a>
  </nav>
@endif

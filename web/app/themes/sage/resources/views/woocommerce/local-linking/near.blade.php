@if (!empty($near))
  <section class="local-links__block local-links--near">
    <h3 class="text-green-dark mb-3 text-[20px] leading-tight font-bold">{{ __('Nearby cities', 'sage-front') }}</h3>
    <ul class="flex flex-wrap gap-x-6 gap-y-2">
      @foreach ($near as $link)
        <li>
          <a href="{{ esc_url($link['url']) }}" class="text-green-default text-body-15 font-semibold hover:underline">
            {{ $link['anchor'] }}
          </a>
        </li>
      @endforeach
    </ul>
  </section>
@endif

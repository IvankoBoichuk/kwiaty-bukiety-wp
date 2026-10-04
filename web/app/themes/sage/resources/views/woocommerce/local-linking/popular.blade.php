@if (!empty($popular))
  <section class="local-links__block local-links--popular">
    <h3 class="text-green-dark mb-3 text-[20px] leading-tight font-bold">
      {{
        trim(
          sprintf(__('Popular in %s', 'sage-front'), $woj),
        )
      }}
    </h3>
    <ul class="flex flex-wrap gap-x-6 gap-y-2">
      @foreach ($popular as $item)
        <li>
          <a href="{{ esc_url($item['url']) }}" class="text-green-default text-body-15 font-semibold hover:underline">
            {{ $item['anchor'] }}
          </a>
        </li>
      @endforeach
    </ul>
  </section>
@endif

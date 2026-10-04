{{--
  [kb_kwiaciarnie_pl] -- the voivodeship grid on "Kwiaciarnie w Polsce".

  The production shortcode also printed an empty "TOP 50 cities" list, a city
  search box with no script behind it and a placeholder for a map that was
  never built. Those shipped visible "to be filled in later" copy on an
  indexed page, so only the part that carries links is migrated.
--}}
<section class="kb-voivodeships flex flex-col gap-5">
  <h2 class="text-green-dark text-[20px] leading-tight font-bold md:text-[22px]">
    {{ __('Choose a voivodeship', 'sage-front') }}
  </h2>

  <ul class="grid grid-cols-2 gap-3 md:grid-cols-3 lg:grid-cols-4">
    @foreach ($voivodeships as $voivodeship)
      <li>
        <a
          href="{{ esc_url($voivodeship['url']) }}"
          class="text-green-default text-body-15 hover:border-green-easy flex h-full items-center rounded-xl border border-[#E0E0D7] bg-white px-4 py-3 font-semibold transition-colors duration-200"
        >
          {{ $voivodeship['name'] }}
        </a>
      </li>
    @endforeach
  </ul>
</section>

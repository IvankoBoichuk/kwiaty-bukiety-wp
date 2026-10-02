{{--
  Per-category FAQ: the visible block and the FAQPage markup from one source.

  Google only honours FAQ markup when the questions are visible on the page,
  which is why the four production categories that carried nothing but a
  JSON-LD script were violating the guidelines.
--}}
@php
  use App\Catalog\Faq;
@endphp

@if (!empty($faq))
  <section class="category-faq flex flex-col gap-4">
    <h2 class="text-green-dark text-[20px] leading-tight font-bold md:text-[22px]">{{ __('FAQ', 'sage-front') }}</h2>

    <div class="flex flex-col gap-2">
      @foreach ($faq as $item)
        <details class="group rounded-xl border border-[#E0E0D7] bg-white px-4 py-3">
          <summary
            class="text-green-dark text-body-16 flex cursor-pointer items-center justify-between gap-4 font-semibold marker:content-none"
          >
            {{ $item['q'] }}
            <span class="text-green-easy shrink-0 transition-transform duration-200 group-open:rotate-45">+</span>
          </summary>
          <div class="text-green-default text-body-15 prose-a:text-[#2F80ED] mt-2">{!! $item['a'] !!}</div>
        </details>
      @endforeach
    </div>
  </section>

  <script type="application/ld+json">
    {!! wp_json_encode(Faq::schema($faq), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}
  </script>
@endif

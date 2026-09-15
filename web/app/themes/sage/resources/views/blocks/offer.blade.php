@php
  use App\Blocks\Blocks;
  use Timber\ImageHelper;

  /* @var array<string, Category> $categories */
  $featuredCategory = $categories[0] ?? null;
  $secondaryCategories = array_slice($categories, 1);
  $productPlaceholderImage = function_exists('wc_placeholder_img_src') ? wc_placeholder_img_src('medium') : '';

  $legacyImage = is_array($media['img'] ?? null) ? $media['img'] : [];
  $mediaType = is_string($media['type'] ?? null) ? $media['type'] : 'img';
  $mediaOrigin = is_string($media['origin'] ?? null) ? $media['origin'] : 'file';
  $mediaFile = is_array($media['file'] ?? null) ? $media['file'] : [];
  $mediaEmbed = is_array($media['embed'] ?? null) ? $media['embed'] : [];

  $heroImageSrc = $media['file']['url'] ?? '';
  $heroImageAlt = 'Hero Image';
  if ($mediaType === 'img') {
    $heroImageAlt = (string) ($mediaFile['alt'] ?? ($mediaFile['title'] ?? 'Hero Image'));
  }
@endphp

<section
  @if (!empty($attributes->anchor)) id="{{ $attributes->anchor }}" @endif
  class="{{ trim((string) ($attributes->className ?? '')) }} mt-2.5 md:mt-4 lg:mt-8"
>
  <div
    class="lg:border-green-easy relative z-0 w-full overflow-hidden rounded-2xl p-3 pb-1.5 lg:flex lg:gap-14 lg:rounded-none lg:border-b lg:p-0 lg:pb-8.5"
  >
    <div class="flex flex-1 flex-col justify-center pt-6">
      @if (!empty($texts->title))
        <h1 class="text-dark-text mb-3">{!! Blocks::multilineTitle($texts->title) !!}</h1>
      @endif

      @if (true)
        <div class="mt-3 hidden text-lg text-[#404844] lg:block">
          Świeże bukiety na każdą okazję. <br />
          Dostarczamy szybko, pięknie i z sercem.
        </div>
      @endif

      @if (!empty($texts->text))
        <div class="mt-3 hidden text-lg text-[#404844] lg:block">{!! $texts->text !!}</div>
      @endif

      @if (true)
        <div class="mt-7 flex flex-wrap items-center gap-3">
          <a
            href="/zamowienie"
            class="bg-green-dark border-green-dark hover:bg-green-default inline-flex items-center justify-center gap-2 rounded-full border-2 px-12.5 py-3 text-base text-white transition"
          >
            <span>Zamow Online</span>
            <svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
              <path d="M7.5 15L12.5 10L7.5 5" stroke="white" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"></path>
            </svg>
          </a>

          <a
            href="tel:+48000000000"
            class="bg-background text-green-default border-green-dark hover:bg-secondary inline-flex items-center justify-center gap-2 rounded-full border-2 px-12.5 py-3 text-base transition"
          >
            <svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
              <path d="M18.3334 14.1V16.6C18.3344 16.8321 18.2868 17.0618 18.1939 17.2745C18.1009 17.4871 17.9645 17.678 17.7935 17.8349C17.6225 17.9918 17.4206 18.1112 17.2007 18.1856C16.9809 18.26 16.7479 18.2876 16.5168 18.2667C13.9525 17.988 11.4893 17.1118 9.32511 15.7083C7.31163 14.4289 5.60455 12.7218 4.32511 10.7083C2.91676 8.53435 2.04031 6.05917 1.76677 3.48334C1.74595 3.2529 1.77334 3.02064 1.84719 2.80136C1.92105 2.58208 2.03975 2.38058 2.19575 2.20969C2.35174 2.0388 2.54161 1.90227 2.75327 1.80878C2.96492 1.71529 3.19372 1.66689 3.42511 1.66668H5.92511C6.32953 1.6627 6.7216 1.80591 7.02824 2.06962C7.33488 2.33333 7.53517 2.69955 7.59177 3.10001C7.69729 3.90006 7.89298 4.68562 8.17511 5.44168C8.28723 5.73995 8.31149 6.0641 8.24503 6.37574C8.17857 6.68738 8.02416 6.97344 7.80011 7.20001L6.74177 8.25834C7.92807 10.3446 9.65549 12.072 11.7418 13.2583L12.8001 12.2C13.0267 11.976 13.3127 11.8216 13.6244 11.7551C13.936 11.6886 14.2602 11.7129 14.5584 11.825C15.3145 12.1071 16.1001 12.3028 16.9001 12.4083C17.3049 12.4655 17.6746 12.6693 17.9389 12.9813C18.2032 13.2932 18.3436 13.6913 18.3334 14.1Z" stroke="#0C4A2C" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"></path>
            </svg>

            <span>Zadzwon teraz</span>
          </a>
        </div>
      @endif

      @if (!empty($texts->advantages) && is_array($texts->advantages))
        <ul class="flex flex-wrap items-center gap-1.5 lg:mt-8 lg:gap-x-6 lg:gap-y-4">
          @foreach ($texts->advantages as $advantage)
            <li
              class="bg-accent border-background flex shrink items-center gap-2.5 rounded-2xl border px-2 md:h-7 md:px-3 lg:border-none lg:bg-transparent lg:p-0"
            >
              @if (!empty($advantage->icon))
                <span class="bg-secondary flex size-13 shrink-0 items-center justify-center rounded-full">
                  <img src="{{ $advantage->icon }}" alt="" class="size-7.5" />
                </span>
              @endif
              <div class="flex flex-col leading-tight">
                <span
                  class="text-dark-text text-xs md:text-[15px] lg:text-[16px] lg:font-semibold"
                  >{{ $advantage }}</span
                >
                @if (!empty($advantage->subtitle))
                  <span class="text-gray-3 text-[16px]">{{ $advantage->subtitle }}</span>
                @endif
              </div>
            </li>
          @endforeach
        </ul>
      @endif
    </div>

    @if (!empty($heroImageSrc))
      <img
        src="{{ ImageHelper::resize(wp_get_attachment_image_url($media['file']['id'], 'full') ?: '', 200) }}"
        alt="{{ $heroImageAlt }}"
        fetchpriority="high"
        class="object-cover max-lg:absolute max-lg:top-1/2 max-lg:right-0 max-lg:-z-20 max-lg:h-full max-lg:w-[50%] max-lg:-translate-y-1/2 lg:relative lg:aspect-video lg:min-h-90 lg:flex-1 lg:overflow-hidden lg:rounded-4xl"
      />
      <div
        class="absolute inset-0 -z-10 w-full bg-linear-to-r from-[#F9F3EB] from-50% to-[#f9f3eb00] to-70% lg:hidden"
      ></div>
    @endif
  </div>

  <div class="mt-6 grid auto-rows-fr grid-cols-2 gap-1.25 md:gap-3 lg:mt-8.5 lg:grid-cols-3">
    @if (!empty($featuredCategory->link))
      <div class="relative row-span-2 overflow-hidden rounded-2xl transition hover:brightness-105 lg:rounded-4xl">
        <img
          src="{{ $featuredCategory->image?->src('medium') ?: $productPlaceholderImage }}"
          class="absolute inset-0 size-full object-cover lg:rounded-4xl"
          alt="{{ $featuredCategory->image?->alt() ?: ($featuredCategory->name ?? '') }}"
        />
        <a
          href="{{ $featuredCategory->link ?? '#' }}"
          target="{{ $featuredCategory->target ?? '_self' }}"
          class="text-background absolute bottom-1 left-1/2 flex w-max max-w-11/12 -translate-x-1/2 items-center rounded-2xl bg-black/60 px-2.5 py-1 text-center text-[14px] leading-4 before:absolute before:inset-0 md:px-4 md:py-2 md:text-[18px]"
        >
          {{ $featuredCategory->name ?? '' }}
        </a>
      </div>
    @endif

    @if ($secondaryCategories !== [])
      @foreach ($secondaryCategories as $item)
        @if (!empty($item->link))
          <div
            class="relative flex items-center overflow-hidden rounded-2xl bg-[#F2EDE1] transition-colors hover:bg-[#F2EDE1]/80 lg:rounded-4xl"
          >
            <img
              src="{{ $item->image?->src('medium') ?: $productPlaceholderImage }}"
              class="aspect-square h-12.75 flex-none rounded-2xl object-cover md:aspect-10/9 md:h-24 lg:h-31 lg:rounded-4xl"
              alt="{{ $item->image?->alt() ?: $item->name }}"
            />
            <a
              href="{{ $item->link }}"
              target="{{ $item->target ?? '_self' }}"
              class="text-dark-text text-body-15 px-2.5 font-semibold before:absolute before:inset-0 md:px-4 md:text-[20px] lg:px-8"
            >
              {{ $item->name ?? '' }}
            </a>
          </div>
        @endif
      @endforeach
    @endif
  </div>
</section>

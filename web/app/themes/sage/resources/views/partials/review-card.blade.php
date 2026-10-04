@php
  /**
   * @var \Frontenda\Blocks\SlotComment $review
   */
@endphp

<div class="flex flex-col gap-3 bg-secondary p-4">
  <div class="flex items-center justify-between">
    <div class="flex items-center gap-2 text-[13px] leading-3.75 font-semibold">
      @if ($review->name())
        <div class="text-green-easy uppercase">{{ $review->name() }}</div>
      @endif
      @if ($review->location())
        <div class="text-gray-3">{{ $review->location() }}</div>
      @endif
    </div>

    <div class="flex items-center gap-0.5">
      @for ($index = 0; $index < $review->fullStars(); $index++)
        <svg width="14" height="13" viewBox="0 0 14 13" fill="none" xmlns="http://www.w3.org/2000/svg">
          <path d="M6.58333 0.75L8.38583 4.40167L12.4167 4.99083L9.5 7.83167L10.1883 11.845L6.58333 9.94917L2.97833 11.845L3.66667 7.83167L0.75 4.99083L4.78083 4.40167L6.58333 0.75Z" fill="#F2994A" stroke="#F2994A" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
        </svg>
      @endfor

      @if ($review->hasPartialStar())
        <svg width="14" height="13" viewBox="0 0 14 13" fill="none" xmlns="http://www.w3.org/2000/svg">
          <defs>
            <linearGradient id="review-star-{{ $review->id() }}">
              <stop offset="{{ $review->partialStarFill() }}%" stop-color="#F2994A" />
              <stop offset="{{ $review->partialStarFill() }}%" stop-color="transparent" />
            </linearGradient>
          </defs>
          <path
            fill="url(#review-star-{{ $review->id() }})"
            d="M6.58333 0.75L8.38583 4.40167L12.4167 4.99083L9.5 7.83167L10.1883 11.845L6.58333 9.94917L2.97833 11.845L3.66667 7.83167L0.75 4.99083L4.78083 4.40167L6.58333 0.75Z"
            stroke="#F2994A"
            stroke-width="1.5"
            stroke-linecap="round"
            stroke-linejoin="round"
          />
        </svg>
      @endif

      @for ($index = 0; $index < $review->totalEmptyStars(); $index++)
        <svg width="14" height="13" viewBox="0 0 14 13" fill="none" xmlns="http://www.w3.org/2000/svg">
          <path d="M6.58333 0.75L8.38583 4.40167L12.4167 4.99083L9.5 7.83167L10.1883 11.845L6.58333 9.94917L2.97833 11.845L3.66667 7.83167L0.75 4.99083L4.78083 4.40167L6.58333 0.75Z" stroke="#F2994A" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
        </svg>
      @endfor
    </div>
  </div>

  @if (!empty($review->text()))
    <p class="text-[14px] font-medium">&quot;{{ $review->text() }}&quot;</p>
  @endif
</div>

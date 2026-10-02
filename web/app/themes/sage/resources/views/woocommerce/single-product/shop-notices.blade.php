{{--
  Category lead time and quantity discounts, shown above the delivery options.

  The production snippets printed these from woocommerce_single_product_summary,
  a hook this theme's single-product template never fires, so they are rendered
  from the template instead.
--}}
@use('App\Shop\LeadTimeRules')
@use('App\Shop\WholesaleDiscount')
@php
  $productId = (int) ($product?->get_id() ?? 0);
  $leadTimeNotice = $productId > 0 ? LeadTimeRules::noticeFor($productId) : '';
  $wholesaleTiers = $productId > 0 && WholesaleDiscount::appliesTo($productId) ? WholesaleDiscount::tiers() : [];
@endphp

@if ($leadTimeNotice !== '' || $wholesaleTiers !== [])
  <div class="flex flex-col gap-3">
    @if ($leadTimeNotice !== '')
      <p class="text-body-14 text-green-default rounded-xl border border-[#E0D7B8] bg-[#FBF6E7] px-4 py-3">
        <strong>{{ __('Ważne:', 'sage-front') }}</strong> {{ $leadTimeNotice }}
      </p>
    @endif

    @if ($wholesaleTiers !== [])
      <div class="text-body-14 text-green-default rounded-xl border border-[#E0E0D7] bg-white px-4 py-3">
        <p class="text-green-dark mb-1 font-semibold">{{ __('Rabat ilościowy', 'sage-front') }}</p>
        <ul class="flex flex-col gap-1">
          @foreach ($wholesaleTiers as $threshold => $discount)
            <li>
              {{
                sprintf(
                  __('od %1$d szt. — %2$d%% taniej', 'sage-front'),
                  (int) $threshold,
                  (int) round($discount * 100),
                )
              }}
            </li>
          @endforeach
        </ul>
        <p class="text-gray-3 mt-2">
          {{
            sprintf(
              __('Minimalna ilość zamówienia: %d szt.', 'sage-front'),
              WholesaleDiscount::minimumQuantity(),
            )
          }}
        </p>
      </div>
    @endif
  </div>
@endif

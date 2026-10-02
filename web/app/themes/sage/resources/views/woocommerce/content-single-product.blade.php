@php
  global $product;

  if (!($product instanceof \WC_Product)) {
    $product = wc_get_product(get_the_ID());
  }

  $productData = $productView ?? null;
  $productPayload = null;

  if ($product instanceof \WC_Product) {
    $currencySymbol = function_exists('get_woocommerce_currency_symbol')
      ? html_entity_decode((string) get_woocommerce_currency_symbol(), ENT_QUOTES | ENT_HTML5, 'UTF-8')
      : 'zł';
    /*
     * WooCommerce separates the amount from the symbol with a literal &nbsp; in
     * every spaced currency position, and this template is handed to JS, which
     * writes the result out as text -- so the entity has to be decoded here or
     * the customer reads "179&nbsp;zl" on the page. Only the format is decoded:
     * the symbol above already is, and running it through again would mangle a
     * symbol that legitimately contained an ampersand.
     */
    $priceFormat = html_entity_decode(
      function_exists('get_woocommerce_price_format')
        ? (string) get_woocommerce_price_format()
        : '%2$s %1$s',
      ENT_QUOTES | ENT_HTML5,
      'UTF-8',
    );
    $formattedPriceTemplate = str_replace('%1$s', $currencySymbol, $priceFormat);
    [$currencyPrefix, $currencySuffix] = array_pad(explode('%2$s', $formattedPriceTemplate, 2), 2, '');

    $productPayload = [
      'productId' => $product->get_id(),
      'basePrice' => (float) wc_get_price_to_display($product),
      'currencySymbol' => $currencySymbol,
      'currencyPrefix' => $currencyPrefix,
      'currencySuffix' => $currencySuffix,
      'currencyDecimalSeparator' => function_exists('wc_get_price_decimal_separator')
        ? (string) wc_get_price_decimal_separator()
        : ',',
      'currencyThousandSeparator' => function_exists('wc_get_price_thousand_separator')
        ? (string) wc_get_price_thousand_separator()
        : ' ',
      'currencyMinorUnit' => function_exists('wc_get_price_decimals') ? (int) wc_get_price_decimals() : 2,
      'isVariable' => $product->is_type('variable'),
      'storeApiNonce' => (string) wp_create_nonce('wc_store_api'),
      // Travels in the config rather than through @wordpress/i18n, so the
      // bundle needs neither the global wp.i18n nor the two render-blocking
      // scripts behind it (see resources/js/modules/strings.ts).
      'i18n' => [
        'deliveryDateRequired' => __('Choose a delivery date', 'sage-front'),
        'deliveryTimeRequired' => __('Choose a delivery time', 'sage-front'),
      ],
    ];
  }

  $orderOptionsView = $orderOptionsView ?? 'woocommerce.single-product.order-options';
@endphp

@if ($product instanceof \WC_Product && $productData)
  @php
    do_action('woocommerce_before_single_product');
  @endphp
  @if (post_password_required())
    {!! get_the_password_form() !!}
  @else
    <section
      class="bg-background bx-container relative items-start gap-x-8 gap-y-6 lg:grid lg:auto-rows-max lg:grid-cols-2 lg:gap-x-8 lg:gap-y-6 2xl:grid-cols-[minmax(0,1fr)_833px]"
    >
      {{-- Product Gallery --}}
      @include('woocommerce.single-product.gallery', ['gallery' => $productData['gallery'], 'title' => $productData['title']])

      {{-- Details --}}
      <div
        class="grid gap-8 lg:sticky lg:top-(--header-top-height) lg:col-start-2 lg:row-span-3 lg:row-start-1 lg:gap-6"
      >
        <div class="grid gap-4 md:gap-7 lg:gap-5">
          <div class="pt-1 md:pt-3">
            @include('elements.badges',
              [
                'badges' => $productData['badges'],
                'wrapperClass' => 'max-lg:absolute mb-3 top-2 right-2 z-10 flex flex-wrap gap-1.5'
              ])
            <h1 class="text-green-default h2-mobile md:h3-desktop lg:h2-desktop">{{ $productData['title'] }}</h1>
          </div>

          {{-- Variations --}}
          @if ($productData['isVariable'] &&
            !empty($productData['variationAttributes']) &&
            !empty($productData['availableVariations']))
            @include('woocommerce.single-product.variations', ['attributeGroups' => $productData['variationAttributes']])
          @endif
        </div>

        {{-- Order Options --}}
        @include($orderOptionsView)

        {{-- Cross-sell products --}}
        @if (!empty($productData['additions']))
          @include('woocommerce.single-product.cross-sell-products', ['additions' => $productData['additions']])
        @endif

        {{-- Add to cart button --}}
        @if (!wp_is_mobile())
          @include('woocommerce.single-product.add-to-cart-bar.desktop')
        @endif
      </div>

      {{-- Product Description --}}
      @if ($productData['description'])
        <div class="mb-12 lg:col-start-1 lg:row-start-2 lg:mb-0">
          @include('woocommerce.single-product.description', ['description' => $productData['description']])
        </div>
      @endif

      {{-- Products Reviews --}}
      @if (!empty($productData['reviews']))
        <div class="mb-12 lg:col-start-1 lg:row-start-3 lg:mb-0">
          @include('woocommerce.single-product.reviews', ['reviews' => $productData['reviews']])
        </div>
      @endif
    </section>
    @if (!empty($relatedProducts))
      <section class="bx-container bg-[#E5EFDE] py-12 lg:py-25">
        <h2 class="h2-mobile lg:h3-desktop text-green-default mb-6">{{ __('Similar products', 'sage-front') }}</h2>
        <div class="grid auto-rows-auto grid-cols-2 gap-x-2.75 gap-y-6 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5">
          @foreach ($relatedProducts as $item)
            @include('partials.product-card-3', ['item' => $item])
          @endforeach
        </div>
      </section>
    @endif
    @php(do_action('woocommerce_after_single_product'))
  @endif
@endif

@push('scripts')
  @if ($productPayload)
    <script>
      window.product = @json($productPayload);
    </script>
  @endif
  @if ($productData && $productData['isVariable'] && !empty($productData['availableVariations']))
    <script>
      window.productVariations = @json($productData['availableVariations']);
    </script>
  @endif
@endpush

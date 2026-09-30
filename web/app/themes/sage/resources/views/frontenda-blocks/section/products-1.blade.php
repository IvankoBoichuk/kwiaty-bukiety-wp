@php
    use App\Catalog\Product;
    use Frontenda\Blocks\SectionContext;
    /**
     * @var SectionContext $context
     */
    $header = $context->header();
    $text = $context->text();
    $query = $context->query();
    $sliderId = wp_unique_id('products-swiper-');
    // The card partials render an App\Catalog\Product, while fa/query hands back
    // Timber posts, so map the ids across. wc_get_product() returns false for a
    // draft or deleted product, which fromID() cannot represent.
    $products = collect($query?->posts() ?? [])
        ->map(fn ($post) => wc_get_product($post->ID) ?: null)
        ->filter()
        ->map(fn ($product) => Product::fromWooCommerce($product));
@endphp
<section {!! $context->wrapperAttributes() !!}>
    @if ($header?->get('title') || $header?->get('subtitle') || $text?->html())
        <div class="mx-container mb-4.5 md:mb-6 lg:mb-16">
            @if ($header?->get('title'))
                <div class="h2-mobile md:h2-desktop mb-1 md:mb-0">
                    {!! $header->get('title')->render() !!}
                </div>
            @endif

            @if ($header?->get('subtitle'))
                <div class="text-body-15 md:text-body-16">
                    {!! $header->get('subtitle')->render() !!}
                </div>
            @endif

            @if ($text?->html())
                <div class="text-body-15 md:text-body-16">
                    {!! $text->html() !!}
                </div>
            @endif
        </div>
    @endif

    @if ($products->isNotEmpty())
        <div class="overflow-x-hidden">
            <div class="events-swiper swiper bx-container! overflow-visible!" id="{{ $sliderId }}">
                <div class="swiper-wrapper mb-3">
                    @foreach ($products as $item)
                        @include('partials.product-card-slider', ['item' => $item])
                    @endforeach
                </div>
                <div class="mx-auto flex w-max items-center justify-center gap-5">
                    <button
                        id="{{ $sliderId }}-prev"
                        aria-label="{{ __('Previous', 'sage-front') }}"
                        class="[&.swiper-button-disabled]:opacity-50 [&.swiper-button-disabled]:pointer-events-none flex size-10 flex-none cursor-pointer items-center justify-center rounded-xl border-2 border-[#C7C7C7] hover:border-[#B19BC5]"
                    >
                        <svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                            <path d="M12.5 15L7.5 10L12.5 5" stroke="#B19BC5" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                        </svg>
                    </button>
                    <div class="swiper-pagination relative! m-0 flex justify-center [--swiper-pagination-bottom:0]"></div>
                    <button
                        id="{{ $sliderId }}-next"
                        aria-label="{{ __('Next', 'sage-front') }}"
                        class="[&.swiper-button-disabled]:opacity-50 [&.swiper-button-disabled]:pointer-events-none flex size-10 flex-none cursor-pointer items-center justify-center rounded-xl border-2 border-[#C7C7C7] hover:border-[#B19BC5]"
                    >
                        <svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                            <path d="M7.5 15L12.5 10L7.5 5" stroke="#B19BC5" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                        </svg>
                    </button>
                </div>
            </div>
        </div>
    @endif
</section>

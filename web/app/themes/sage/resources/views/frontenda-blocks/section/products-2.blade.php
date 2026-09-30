@php
    use App\Catalog\Product;
    use Frontenda\Blocks\SectionContext;
    /**
     * @var SectionContext $context
     */
    $header = $context->header();
    $text = $context->text();
    $buttons = $context->buttons();
    $query = $context->query();
    $products = collect($query?->posts() ?? [])
        ->map(fn ($post) => wc_get_product($post->ID) ?: null)
        ->filter()
        ->map(fn ($product) => Product::fromWooCommerce($product));
@endphp
<section {!! $context->wrapperAttributes() !!}>
    <div class="flex flex-col gap-y-6 pt-1.5">
        @if ($header?->get('title') || $header?->get('subtitle'))
            <div class="relative mb-6">
                @if ($header?->get('subtitle'))
                    <div
                        class="font-deco pointer-events-none absolute -top-13 left-28 z-0 text-[69px] leading-15 text-[#E0EAD9] uppercase select-none md:-top-4 md:left-45 md:text-[144px] lg:-top-10 lg:left-50 lg:text-[245px]"
                        aria-hidden="true"
                    >
                        {!! $header->get('subtitle')->render() !!}
                    </div>
                @endif

                @if ($header?->get('title'))
                    <div class="h2-mobile md:h2-desktop text-green-default relative z-10">
                        {!! $header->get('title')->render() !!}
                    </div>
                @endif
            </div>
        @endif

        @if ($text?->html())
            <div class="text-body-15 md:text-body-16">
                {!! $text->html() !!}
            </div>
        @endif

        @if ($products->isNotEmpty())
            <div class="grid auto-rows-auto grid-cols-2 gap-x-3 gap-y-6 md:grid-cols-3 lg:grid-cols-4">
                @foreach ($products as $item)
                    @include('partials.product-card-grid', ['item' => $item])
                @endforeach
            </div>
        @endif

        @if ($buttons?->html())
            <div class="flex min-w-max flex-1 flex-wrap justify-center">
                {!! $buttons->html() !!}
            </div>
        @endif
    </div>
</section>

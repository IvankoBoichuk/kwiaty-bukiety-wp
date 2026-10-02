@php
    use App\Support\Markup;
    use Frontenda\Blocks\SectionContext;
    /** @var SectionContext $context */
@endphp

<section {!! $context->wrapperAttributes() !!} data-counter-section>
    @if ($context->header()?->get('title'))
        <div class="mb-8.5 text-center lg:text-left h2-mobile md:h2-desktop">
            {!! $context->header()->get('title')->render() !!}
        </div>
    @endif

    @if ($context->numbers())
        <div class="space-y-4 md:grid md:grid-cols-2 md:gap-8 lg:grid-cols-4 lg:gap-3">
            @foreach ($context->numbers()->items() as $item)
                <div class="flex h-max flex-col items-center gap-2 border-b border-[#C7C7C7] pb-4 text-center lg:border-b-0 lg:border-l lg:last:border-r">
                    <div class="text-green-default text-5xl leading-14 font-medium" data-counter="{{ $item->number() }}">
                        0
                    </div>
                    <div class="text-body-15 text-gray-1 flex items-center justify-center gap-1 leading-normal font-semibold lg:text-[22px]">
                        @if ($item->icon()?->post_mime_type === 'image/svg+xml')
                            <div class="flex-none">{!! Markup::sanitizeSvg($item->icon()) !!}</div>
                        @elseif ($item->icon())
                            <div class="flex-none">
                                <img
                                    src="{{ $item->icon()->src() }}"
                                    alt="{{ $item->icon()->alt() ?: $item->label() }}"
                                    @if (($item->icon()->width() ?? 0) > 0) width="{{ $item->icon()->width() }}" @endif
                                    @if (($item->icon()->height() ?? 0) > 0) height="{{ $item->icon()->height() }}" @endif
                                    class="size-5 object-contain"
                                />
                            </div>
                        @endif
                        {{ $item->label() }}
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    @if ($context->reviews())
        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4 md:mt-6 lg:mt-12">
            @foreach ($context->reviews()->comments() as $review)
                @include('partials.review-card', ['review' => $review])
            @endforeach
        </div>
    @endif
</section>
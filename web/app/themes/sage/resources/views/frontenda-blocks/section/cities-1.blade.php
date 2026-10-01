@php
    use Frontenda\Blocks\SectionContext;
    /**
     * @var SectionContext $context
     */
    $header = $context->header();
    $text = $context->text();
    $media = $context->media();
    $terms = $context->terms();
    $cities = $terms?->terms() ?? [];
    // total() reports the whole pool rather than this page, so it answers
    // whether anything is left to load in either selection mode.
    $renderedIds = array_map(static fn ($city) => $city->term_id, $cities);
    $remaining = $terms ? max(0, $terms->total() - count($cities)) : 0;
    $widthPattern = [
        'flex-[1_1_28%]',
        'flex-[1_1_35%]',
        'flex-[1_1_25%]',
        'flex-[1_1_30%]',
        'flex-[1_1_32%]',
        'flex-[1_1_27%]',
        'flex-[1_1_33%]',
        'flex-[1_1_29%]',
        'flex-[1_1_26%]',
    ];
@endphp
<section {!! $context->wrapperAttributes() !!}>
    <div class="mx-auto flex w-full grow flex-col">
        <div class="grid grid-cols-1 gap-8 lg:grid-cols-12 lg:gap-12">
            <div class="flex flex-col justify-center lg:col-span-7 lg:pt-20 lg:pb-7">
                @if ($header?->get('title') || $text?->html())
                    <div class="mb-6">
                        @if ($header?->get('title'))
                            <div class="h2-mobile md:h2-desktop mb-2">
                                {!! $header->get('title')->render() !!}
                            </div>
                        @endif

                        @if ($text?->html())
                            <div class="text-body-15 md:text-body-16">
                                {!! $text->html() !!}
                            </div>
                        @endif
                    </div>
                @endif

                @if ($cities !== [])
                    <ul class="mb-6 flex flex-wrap gap-3" data-cities-list>
                        @foreach ($cities as $city)
                            <li class="{{ $widthPattern[$loop->index % count($widthPattern)] }}">
                                <a
                                    href="{{ esc_url($city->link()) }}"
                                    class="bg-green-easy text-h4 flex h-full items-center justify-center rounded-2xl px-4 py-4.5 text-center text-white lg:px-20"
                                >
                                    {{ $city->title() }}
                                </a>
                            </li>
                        @endforeach

                        @if ($remaining > 0)
                            <li class="flex min-w-max flex-1 justify-center lg:w-auto lg:flex-1" data-cities-load-more>
                                <button
                                    data-cities-button
                                    data-taxonomy="{{ esc_attr($terms->taxonomy()) }}"
                                    data-name-like="{{ esc_attr($terms->nameLike()) }}"
                                    data-hide-empty="{{ $terms->hideEmpty() ? '1' : '0' }}"
                                    data-order-by="{{ esc_attr($terms->orderBy()) }}"
                                    data-order="{{ esc_attr(strtolower($terms->order())) }}"
                                    data-number="{{ $terms->perPage() }}"
                                    data-exclude="{{ esc_attr(implode(',', $renderedIds)) }}"
                                    data-initial-count="{{ count($cities) }}"
                                    data-rendered-count="0"
                                    data-total-count="{{ $remaining }}"
                                    class="bg-green-dark text-gray-6 border-green-default inline-flex w-full items-center justify-center gap-2 rounded-full border-2 px-8 py-4 text-[13px] leading-[auto] font-semibold tracking-normal uppercase transition-all duration-200"
                                >
                                    <span>{{ __('More cities', 'sage-front') }}</span>
                                </button>
                            </li>
                        @endif
                    </ul>
                @endif
            </div>

            @if ($media)
                {{-- The image bleeds out to the right edge of the window, which
                     means cancelling exactly the gutter the section reserves —
                     a fixed -120px only matched it at 1920px and overhung the
                     viewport on anything narrower, leaving a horizontal
                     scrollbar. --}}
                <div class="-mr-container relative -my-10 hidden overflow-hidden lg:col-span-5 lg:block">
                    <div class="absolute inset-y-0 left-0 w-[50vw]">
                        {{-- The column is hidden below lg, so only the desktop size is worth generating. --}}
                        {!! $media->html(['desktop' => '760x520']) !!}
                    </div>
                </div>
            @endif
        </div>
    </div>
</section>

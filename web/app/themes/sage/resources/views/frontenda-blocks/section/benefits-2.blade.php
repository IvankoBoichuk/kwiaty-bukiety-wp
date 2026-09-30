@php
    use Frontenda\Blocks\SectionContext;
    /**
     * @var SectionContext $context
     */
    $header = $context->header();
    $text = $context->text();
    $list = $context->list();
@endphp
<section {!! $context->wrapperAttributes() !!}>
    @if ($header?->get('subtitle'))
        <div class="section__subtitle">
            {!! $header->get('subtitle')->render() !!}
        </div>
    @endif

    @if ($header?->get('title'))
        <div class="h2-mobile md:h2-desktop mb-6">
            {!! $header->get('title')->render() !!}
        </div>
    @endif

    @if ($text?->html())
        <div class="text-body-15 md:text-body-16 mb-6">
            {!! $text->html() !!}
        </div>
    @endif

    @if ($list && !$list->isEmpty())
        <div class="grid grid-cols-1 gap-3 lg:grid-cols-6">
            @foreach ($list->items() as $item)
                <div
                    class="text-green-dark flex flex-col gap-2 rounded-sm bg-[#F6EDDB] px-5 py-4 lg:col-span-2 lg:p-8 nth-4:lg:col-span-3 nth-5:lg:col-span-3"
                >
                    <div class="flex items-center gap-2.5">
                        @if ($item->icon())
                            <span class="size-5 flex-none lg:size-6">{!! $item->icon() !!}</span>
                        @endif

                        @if ($item->title())
                            <h3 class="h3-mobile lg:h4-desktop">{{ $item->title() }}</h3>
                        @endif
                    </div>

                    @if ($item->text())
                        <p class="text-body-15 text-gray-600">{{ $item->text() }}</p>
                    @endif
                </div>
            @endforeach
        </div>
    @endif
</section>

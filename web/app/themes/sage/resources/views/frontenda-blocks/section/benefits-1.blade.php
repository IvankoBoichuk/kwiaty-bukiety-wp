@php
    use Frontenda\Blocks\SectionContext;
    /**
     * @var SectionContext $context
     */
    $header = $context->header();
    $text = $context->text();
    $buttons = $context->buttons();
    $media = $context->media();
    $list = $context->list();
@endphp
<section {!! $context->wrapperAttributes() !!}>
    <div class="mb-6 md:mb-12">
        @include('elements.section-subtitle', ['subtitle' => $header?->subtitle()])
    
        @if ($header?->get('title'))
            <div class="h2-mobile md:h2-desktop text-dark-text">
                {!! $header->get('title')->render() !!}
            </div>
        @endif

        @if ($buttons?->html())
            <div class="mt-3 lg:mt-6">
                {!! $buttons->html() !!}
            </div>
        @endif

        @if ($list)
            @foreach (collect($list->items())->chunk(2) as $rowIndex => $row)
                <ul
                    @class([
                        'grid gap-4 lg:gap-8',
                        'md:grid-cols-[1fr_1fr]' => $rowIndex === 0,
                        'md:grid-cols-[1.15fr_0.85fr]' => $rowIndex === 1,
                    ])
                >
                    @foreach ($row as $itemIndex => $item)
                        @include('elements.list-item-' . $list->layout(), [
                            'item' => $item,
                            'tag' => 'li',
                            'index' => $rowIndex * 2 + $loop->iteration,
                        ])
                    @endforeach
                </ul>
            @endforeach
        @endif
    </div>
</section>
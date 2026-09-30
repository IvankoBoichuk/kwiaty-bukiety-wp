@php
    use Frontenda\Blocks\SectionContext;

    /** @var SectionContext $context */
    $headers = $context->slots()->all('header');
    $texts = $context->slots()->all('text');
    $heroHeader = $headers[0] ?? null;
    $storyHeader = $headers[1] ?? null;
    $heroText = $texts[0] ?? null;
    $storyText = $texts[1] ?? null;
    $buttons = $context->buttons();
    $media = $context->media();

    $blocksToArray = static function ($blocks): array {
        if ($blocks instanceof Traversable) {
            return iterator_to_array($blocks, false);
        }

        return is_array($blocks) ? array_values($blocks) : [];
    };

    $sectionBlocks = $blocksToArray($context->block()->inner_blocks);
    $textBlocks = array_values(array_filter(
        $sectionBlocks,
        static fn ($block): bool => $block->name === 'fa/text',
    ));
    $storyBlocks = $blocksToArray($textBlocks[1]->inner_blocks ?? null);
    $storyParagraphs = array_values(array_filter(
        $storyBlocks,
        static fn ($block): bool => $block->name !== 'fa/numbers',
    ));
    $numbersBlock = current(array_filter(
        $storyBlocks,
        static fn ($block): bool => $block->name === 'fa/numbers',
    )) ?: null;

    $stats = array_map(static function ($numberBlock): array {
        $parts = [];

        foreach ($numberBlock->inner_blocks as $part) {
            $parts[$part->name] = trim(wp_strip_all_tags($part->render()));
        }

        return [
            'number' => $parts['fa/title'] ?? '',
            'label' => $parts['fa/text'] ?? '',
        ];
    }, $blocksToArray($numbersBlock?->inner_blocks ?? null));
@endphp

<section {!! $context->wrapperAttributes() !!}>
    <div class="text-green-dark grid gap-4 lg:grid lg:grid-cols-2 lg:items-start lg:gap-x-12 relative">
        @if ($media)
            <div class="-mx-container aspect-394/277 md:mx-0 md:aspect-704/426 lg:aspect-808/436 overflow-hidden lg:col-start-2 lg:row-start-1 lg:self-start lg:sticky lg:top-(--header-top-height)">
                {!! $media->html([
                    'mobile' => '767x540',
                    'tablet' => '1024x620',
                    'desktop' => '808x436',
                ]) !!}
            </div>
        @endif

        <div class="flex flex-col gap-20 lg:col-start-1 lg:row-start-1 lg:gap-12">
            @if ($heroHeader || $heroText?->html() || $buttons?->html())
                <div class="flex flex-col items-center gap-4 md:gap-6 text-center lg:items-start lg:text-left">
                    @if ($heroHeader?->get('title'))
                        <div class="h1-offer-2">
                            {!! $heroHeader->get('title')->render() !!}
                        </div>
                    @endif

                    @if ($heroText?->html())
                        <div class="text-green-default max-w-md text-[15px] md:text-[16px] lg:max-w-full">
                            {!! $heroText->html() !!}
                        </div>
                    @endif

                    @if ($buttons?->html())
                        {!! $buttons->html() !!}
                    @endif
                </div>
            @endif

            @if ($storyHeader || $storyText?->html())
                <div class="flex flex-col gap-5">
                    <div class="flex flex-col gap-3 lg:mb-7">
                        @if ($storyHeader?->get('title'))
                            <div class="h2-mobile lg:h2-desktop text-green-default">
                                {!! $storyHeader->get('title')->render() !!}
                            </div>
                        @endif
    
                        @if ($storyHeader?->get('subtitle'))
                            <div class="text-green-default text-body-15 font-normal md:text-[16px]">
                                {!! $storyHeader->get('subtitle')->render() !!}
                            </div>
                        @endif
                    </div>

                    @if ($storyParagraphs !== [])
                        <div class="text-body-13 flex flex-col gap-3 md:text-[16px]">
                            @foreach ($storyParagraphs as $paragraph)
                                {!! $paragraph->render() !!}
                            @endforeach
                        </div>
                    @endif

                    @if ($stats !== [])
                        <div class="flex gap-2 md:gap-3">
                            @foreach ($stats as $stat)
                                <div class="grow shrink-0 basis-auto flex flex-col items-center justify-center gap-0.5 rounded-xl bg-[#F2EDE1] px-3 py-1.5 text-center md:py-4">
                                    <div class="text-green-default h3-mobile md:h4-desktop">
                                        {{ $stat['number'] }}
                                    </div>
                                    <div class="text-green-dark text-body-13 md:text-body-16">
                                        {{ $stat['label'] }}
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            @endif
        </div>
    </div>
</section>

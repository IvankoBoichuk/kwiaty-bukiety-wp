@php
    use App\Support\Markup;
    use Frontenda\Blocks\SectionContext;

    /** @var SectionContext $context */
    $header = $context->header();
    $list = $context->list();
    $items = $list?->items() ?? [];
    $sectionId = sanitize_html_class(
        $context->anchor()
            ?: 'faq-' . ($context->attributes()['clientId'] ?? wp_unique_id()),
    );
    $schemaItems = array_map(
        static fn ($item): array => [
            'title' => $item->title(),
            'text' => $item->text(),
        ],
        $items,
    );
@endphp

<section {!! $context->wrapperAttributes() !!}>
    @if ($header?->subtitle() || $header?->title())
        <div class="h2-mobile md:h2-desktop mb-8 lg:mb-16">
            @if ($header?->subtitle())
                <div class="section__subtitle">
                    {!! $header->subtitle()->render() !!}
                </div>
            @endif

            @if ($header?->title())
                {!! $header->title()->render() !!}
            @endif
        </div>
    @endif

    @if ($list && ! $list->isEmpty())
        <div
            x-data="accordion"
            class="space-y-4 divide-y divide-[#E0E0D7] lg:space-y-7 lg:border-b lg:border-[#E0E0D7]"
        >
            @foreach ($items as $item)
                @php
                    $itemKey = $sectionId . '-item-' . $loop->index;
                    $buttonId = $itemKey . '-button';
                    $panelId = $itemKey . '-panel';
                @endphp

                <div class="overflow-hidden pb-3 last:max-lg:pb-0 lg:pb-7">
                    <button
                        id="{{ $buttonId }}"
                        type="button"
                        x-on:click="toggle('{{ $itemKey }}')"
                        x-bind:aria-expanded="isActive('{{ $itemKey }}')"
                        aria-controls="{{ $panelId }}"
                        class="flex w-full items-center justify-between text-left"
                    >
                        <span class="text-body-16 font-bold max-lg:hidden">
                            {{ sprintf('%02d', $loop->iteration) }}
                        </span>
                        <span class="h3-mobile md:h4-desktop md:text-green-dark text-green-default w-full pr-6.5 lg:ml-auto lg:max-w-202.5 lg:pr-45">
                            {{ $item->title() }}
                        </span>
                        <span class="relative size-6 shrink-0" aria-hidden="true">
                            <span
                                class="bg-dark-text absolute top-1/2 left-1/2 h-0.5 w-4 -translate-x-1/2 -translate-y-1/2 transition-transform duration-300"
                                x-bind:class="{ 'rotate-0': !isActive('{{ $itemKey }}'), 'rotate-180': isActive('{{ $itemKey }}') }"
                            ></span>
                            <span
                                class="bg-dark-text absolute top-1/2 left-1/2 h-0.5 w-4 origin-center -translate-x-1/2 -translate-y-1/2 transition-transform duration-300"
                                x-bind:class="{ 'rotate-90': !isActive('{{ $itemKey }}'), 'rotate-0': isActive('{{ $itemKey }}') }"
                            ></span>
                        </span>
                    </button>

                    <div
                        id="{{ $panelId }}"
                        role="region"
                        aria-labelledby="{{ $buttonId }}"
                        x-cloak
                        x-show="isActive('{{ $itemKey }}')"
                        x-collapse
                    >
                        <div class="text-body-13 text-green-dark md:text-body-16 ml-auto w-full pt-2 font-light md:pt-4 lg:max-w-208.5 lg:pr-51">
                            {!! wp_kses_post($item->text()) !!}
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        {!! Markup::faqSchema($schemaItems) !!}
    @elseif ($list?->textIfEmpty())
        <p>{{ $list->textIfEmpty() }}</p>
    @endif
</section>

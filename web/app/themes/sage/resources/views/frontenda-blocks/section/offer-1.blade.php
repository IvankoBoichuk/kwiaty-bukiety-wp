@php
    use App\Support\Markup;
    use Frontenda\Blocks\SectionContext;
    /**
     * @var SectionContext $context
     */
    $header = $context->header();
    $text = $context->text();
    $buttons = $context->buttons();
    $media = $context->media();
    $list = $context->list();
    $query = $context->query();
    $posts = $query?->posts() ?? [];
    $featuredPost = $posts[0] ?? null;
    $secondaryPosts = array_slice($posts, 1);
    $productPlaceholderImage = function_exists('wc_placeholder_img_src') ? wc_placeholder_img_src('medium') : '';
    $postTitle = static fn ($post): string => html_entity_decode($post->title(), ENT_QUOTES | ENT_HTML5, 'UTF-8');
@endphp
<section {!! $context->wrapperAttributes() !!}>
    <div
        class="lg:border-green-easy relative z-0 w-full overflow-hidden rounded-2xl p-3 pb-1.5 lg:flex lg:gap-14 lg:rounded-none lg:border-b lg:p-0 lg:pb-8.5"
    >
        @if ($header || $text || $buttons)
            <div class="flex flex-1 flex-col justify-center pt-6">
                @if ($header?->get('subtitle'))
                    <div class="section__subtitle">
                        {!! $header->get('subtitle')->render() !!}
                    </div>
                @endif
            
                @if ($header?->get('title'))
                    <div class="text-dark-text mb-3">
                        {!! Markup::multilineTitle($header->get('title')->render()) !!}
                    </div>
                @endif

                @if ($text?->html())
                    <div class="mt-3 hidden text-lg text-[#404844] lg:block">
                        {!! $text->html() !!}
                    </div>
                @endif
                
                @if ($buttons?->html())
                    <div class="mt-7 hidden lg:block">
                        {!! $buttons->html() !!}
                    </div>
                @endif

                @if ($list)
                    <ul class="flex flex-wrap items-center gap-1.5 lg:mt-8 lg:gap-x-6 lg:gap-y-4">
                        @foreach ($list->items() as $item)
                            <li class="bg-accent border-background flex shrink items-center gap-2.5 rounded-2xl border px-2 md:h-7 md:px-3 lg:border-none lg:bg-transparent lg:p-0">
                                @if ($item->icon())
                                    <span class="hidden bg-secondary lg:flex size-13 shrink-0 items-center justify-center rounded-full">
                                        {!! $item->icon() !!}
                                    </span>
                                @endif
                                <div class="flex flex-col leading-tight">
                                    @if ($item->title())
                                        <span class="text-dark-text text-xs md:text-[15px] lg:text-[16px] lg:font-semibold">
                                            {{ $item->title() }}
                                        </span>
                                    @endif
                                    @if ($item->subtitle() || $item->text())
                                        <span class="hidden lg:inline text-gray-3 text-[16px]">{{ $item->subtitle() ?: $item->text() }}</span>
                                    @endif
                                </div>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        @endif
        
        @if ($media)
            <div class="object-cover max-lg:absolute max-lg:top-1/2 max-lg:right-0 max-lg:-z-20 max-lg:h-full max-lg:w-[50%] max-lg:-translate-y-1/2 lg:relative lg:aspect-video lg:min-h-90 lg:flex-1 lg:overflow-hidden lg:rounded-4xl">
                {!! $media->html([
                    'mobile' => '372x252',
                    'tablet' => '480x290',
                    'desktop' => '812x458'
                ]) !!}
            </div>
            <div
                class="absolute inset-0 -z-10 w-full bg-linear-to-r from-[#F9F3EB] from-50% to-[#f9f3eb00] to-70% lg:hidden"
            ></div>
        @endif
    </div>

    @if ($featuredPost || $secondaryPosts !== [])
        <div class="mt-6 grid auto-rows-fr grid-cols-2 gap-1.25 md:gap-3 lg:mt-8.5 lg:grid-cols-3 md:auto-rows-[100px] lg:auto-rows-[124px]">
            @if ($featuredPost)
                <a href="{{ $featuredPost->link() }}" class="relative row-span-2 overflow-hidden rounded-2xl transition hover:brightness-105 lg:rounded-4xl">
                    <img
                        src="{{ $featuredPost->thumbnail()?->src('large') ?: $productPlaceholderImage }}"
                        class="absolute inset-0 size-full object-cover lg:rounded-4xl"
                        alt="{{ $featuredPost->thumbnail()?->alt() ?: $postTitle($featuredPost) }}"
                    />
                    <span
                        class="text-background absolute bottom-1 left-1/2 flex w-max max-w-11/12 -translate-x-1/2 items-center rounded-2xl bg-black/60 px-2.5 py-1 text-center text-[14px] leading-4 md:px-4 md:py-2 md:text-[18px]"
                    >
                        {{ $postTitle($featuredPost) }}
                    </span>
                </a>
            @endif

            @foreach ($secondaryPosts as $post)
                <div class="relative flex items-center overflow-hidden rounded-2xl bg-[#F2EDE1] transition-colors hover:bg-[#F2EDE1]/80 lg:rounded-4xl">
                    <img
                        src="{{ $post->thumbnail()?->src('medium') ?: $productPlaceholderImage }}"
                        class="w-12.5 h-full flex-none rounded-2xl object-cover md:w-26 lg:w-36 lg:rounded-4xl"
                        alt="{{ $post->thumbnail()?->alt() ?: $postTitle($post) }}"
                    />
                    <a
                        href="{{ $post->link() }}"
                        class="text-dark-text h4-mobile px-2.5 md:px-4 md:text-[20px] lg:px-8 before:absolute before:inset-0"
                    >
                        {{ $postTitle($post) }}
                    </a>
                </div>
            @endforeach
        </div>
    @endif
</section>

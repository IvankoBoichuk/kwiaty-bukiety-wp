@php
    use Frontenda\Blocks\SlotListItem;
    /**
     * @var SlotListItem $item
     */
    $tag ??= 'div';
@endphp

<{{ $tag }} class="flex flex-col gap-2 pb-4 pt-6 border-b border-[#C7C7C7] min-w-0 flex-auto basis-auto">
    <h3 class="flex items-center gap-2">
        <span class="flex items-center flex-none justify-center size-8 text-body-13 text-green-dark bg-white rounded-full border border-[#E0E0D7] md:size-10.5 md:text-body-16">{{ sprintf('%02d', $index) }}</span>
        <span class="text-[16px] font-bold md:h4-desktop text-green-default">{{ $item->title() }}</span>
    </h3>
    <p class="text-body-13 md:text-body-16 text-green-dark">{{ $item->text() }}</p>
</{{ $tag }}>

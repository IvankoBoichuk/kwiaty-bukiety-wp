@php
  use Frontenda\Blocks\SlotListItem;
  /**
   * @var SlotListItem $item
   */
  $tag ??= 'div';
  $index ??= 0;
@endphp

{{-- One step of the "how it works" list, across three breakpoints: a vertical
     rail on mobile and tablet, and a horizontal timeline with 01..n counters on
     desktop. The Figma rails are single SVGs with dot positions baked in for
     four items of fixed size, so they cannot follow a dynamic list; the dot and
     the connector are rebuilt per item instead, keeping the design's palette
     (#0C4A2C dot, #426E59 connector, fading #80D4AB tail on the last step). --}}
<{{ $tag }} class="group relative flex min-w-0 flex-col gap-1.5 pl-5 md:pl-7 lg:gap-3 lg:pt-10 lg:pl-0">
  {{-- Dot: 8px on the vertical rail, 13px on the desktop timeline. --}}
  <span
    class="bg-green-default absolute top-1.5 left-0 size-2 rounded-full border border-[#426E59] lg:top-0 lg:size-[13px]"
    aria-hidden="true"
  ></span>

  {{-- Connector: runs down to the next dot, or right to it on desktop. The
         last step gets the fading stub instead of a connector. --}}
  <span
    class="absolute top-3.5 -bottom-6.5 left-[3.5px] w-px bg-[#426E59] group-last:bottom-auto group-last:h-9 group-last:bg-linear-to-b group-last:from-[#426E59] group-last:to-[#80D4AB]/0 lg:top-[6px] lg:right-[-68px] lg:bottom-auto lg:left-[13px] lg:h-px lg:w-auto lg:group-last:right-0 lg:group-last:h-px lg:group-last:bg-linear-to-r"
    aria-hidden="true"
  ></span>

  @if ($item->title())
    <h3 class="text-green-default md:h4-desktop flex items-center gap-3 text-[18px] font-semibold">
      <span class="hidden text-[24px] font-semibold lg:block">{{ sprintf('%02d', $index) }}</span>
      <span>{{ $item->title() }}</span>
    </h3>
  @endif

  @if ($item->text())
    <p class="text-body-15 md:text-body-16 text-green-dark">{{ $item->text() }}</p>
  @endif
</{{ $tag }}>

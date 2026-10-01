@php
  use Frontenda\Blocks\SlotSubtitle;
  /**
   * @var SlotSubtitle|null $subtitle
   */
  $isEyebrow = $subtitle?->variant() === 'eyebrow';
@endphp

@if ($subtitle?->render())
  <div
    class="{{ $isEyebrow ? 'section__subtitle--eyebrow' : 'section__subtitle' }}"
    @unless ($isEyebrow) aria-hidden="true" @endunless
  >
    {!! $subtitle->render() !!}
  </div>
@endif

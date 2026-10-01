@php
  use Frontenda\Blocks\SlotSubtitle;
  /**
   * @var SlotSubtitle|null $subtitle
   */

  /*
   * The design's subtitle is the oversized decorative word, so that is the
   * default. The small uppercase label it replaced survives as an opt-in: give
   * the subtitle block `eyebrow` as its Additional CSS class in the editor.
   *
   * A slot carries no variant of its own -- SlotSubtitle adds nothing to Slot,
   * and Slot::get() reads the nested-slot data, which is empty here -- so the
   * block's own attributes are what the choice travels in.
   */
  $subtitleClasses = preg_split('/\s+/', trim((string) ($subtitle?->attributes()['className'] ?? '')), -1, PREG_SPLIT_NO_EMPTY) ?: [];
  $isEyebrow = in_array('eyebrow', $subtitleClasses, true);
@endphp

@if ($subtitle?->render())
  <div
    class="{{ $isEyebrow ? 'section__subtitle--eyebrow' : 'section__subtitle' }}"
    @unless ($isEyebrow) aria-hidden="true" @endunless
  >
    {!! $subtitle->render() !!}
  </div>
@endif

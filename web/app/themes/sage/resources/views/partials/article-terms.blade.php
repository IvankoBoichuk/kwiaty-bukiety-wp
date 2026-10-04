@php
  /*
   * The filing of an article: its categories in the header, its tags under the
   * copy.
   *
   * One partial for both, because they are the same control -- a row of pills
   * linking to the term archive -- and differ only in where the article page
   * puts them and in the `#` the design puts in front of a tag.
   *
   * get_the_terms() is used rather than get_the_category()/get_the_tags() so
   * both calls take the same shape, and it is the cached one: the terms of the
   * post are already in the object cache from the single query the loop primed.
   */
  $taxonomy ??= 'category';
  $wrapperClass ??= '';

  $terms = get_the_terms(get_the_ID(), $taxonomy);
  $terms = is_array($terms) ? $terms : [];

  $isTag = $taxonomy === 'post_tag';
  $label = $isTag ? __('Tags', 'sage-front') : __('Categories', 'sage-front');
@endphp

@if ($terms !== [])
  <nav @class(['flex flex-wrap items-center gap-2', $wrapperClass]) aria-label="{{ esc_attr($label) }}">
    @if ($isTag)
      <span class="text-body-13 md:text-body-16 font-semibold text-[#969998]">{{ $label }}:</span>
    @endif

    @foreach ($terms as $term)
      <a
        href="{{ esc_url(get_term_link($term)) }}"
        @class([
          'inline-flex items-center rounded-full border px-3 py-1 text-[13px] leading-5 transition-colors md:text-[14px]',
          'border-green-easy text-green-default hover:bg-secondary' => !$isTag,
          'border-[#E0E0D7] bg-background text-[#5A6561] hover:border-green-easy hover:text-green-default' => $isTag
        ])
      >
        {{ $isTag ? '#' : '' }}{{ $term->name }}
      </a>
    @endforeach
  </nav>
@endif

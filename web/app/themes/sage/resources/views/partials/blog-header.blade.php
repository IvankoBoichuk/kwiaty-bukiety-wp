@php
  /*
   * The title block of the blog listing (Figma 681-8167 / 681-8168 / 682-8208):
   * the page name over one line of supporting copy.
   *
   * get_the_archive_title() is not used for the term archives, because it
   * prefixes the name with "Category:" and the design prints the name alone.
   */
  if (is_home()) {
    $pageForPosts = (int) get_option('page_for_posts');
    $blogTitle = $pageForPosts > 0 ? get_the_title($pageForPosts) : __('Blog', 'sage-front');
    // The raw excerpt field, never get_the_excerpt(): that falls back to the
    // first 55 words of the page body, which on a blog page assembled from
    // blocks is a paragraph of stray markup.
    $blogDescription = $pageForPosts > 0 ? (string) get_post_field('post_excerpt', $pageForPosts) : '';
  } elseif (is_category() || is_tag() || is_tax()) {
    $blogTitle = single_term_title('', false);
    $blogDescription = term_description();
  } elseif (is_author()) {
    $blogTitle = (string) get_the_author();
    $blogDescription = (string) get_the_author_meta('description');
  } else {
    $blogTitle = get_the_archive_title();
    $blogDescription = get_the_archive_description();
  }

  $blogTitle = trim((string) $blogTitle);
  $blogDescription = trim((string) $blogDescription);
@endphp

<header class="flex flex-col gap-3">
  <h1 class="text-green-default text-[22px] leading-normal font-extrabold md:text-[40px] md:leading-11.75 md:font-bold">
    {!! $blogTitle !!}
  </h1>

  @if ($blogDescription !== '')
    <div class="text-body-15 md:text-body-16 text-gray-1 [&_p]:m-0">{!! $blogDescription !!}</div>
  @endif
</header>

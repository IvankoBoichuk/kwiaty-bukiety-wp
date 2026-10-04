@php
  /*
   * One comment of the article page.
   *
   * wp_list_comments() calls this once per comment (App\View\Composers\Comments
   * passes it as the walker callback), so the markup opens the list item and
   * leaves it open: Walker_Comment prints the nested <ol class="children"> of
   * the replies after it and closes the <li> itself.
   *
   * $args carries the walker arguments, which is where the reply link reads the
   * nesting depth the site allows -- without them it would offer a Reply
   * control on a comment that is already as deep as threading goes.
   *
   * Everything this template computes is computed in this one block, the reply
   * link included. A second PHP block further down the file would be read as
   * opening at the inline directive on the list item below -- Blade pairs the
   * first opening tag it finds with the next closing one -- and the markup
   * between the two would be compiled as PHP.
   *
   * @var WP_Comment $comment
   * @var array $args
   * @var int $depth
   */
  $commentId = (int) $comment->comment_ID;
  $author = wp_strip_all_tags(get_comment_author($commentId));
  $initial = mb_strtoupper(mb_substr(trim($author), 0, 1));
  $isApproved = (string) $comment->comment_approved === '1';
  $replyLink = get_comment_reply_link(
    array_merge($args, [
      'add_below' => 'div-comment',
      'depth' => $depth,
      'max_depth' => $args['max_depth'] ?? 0,
      'before' => '',
      'after' => '',
      'reply_text' => __('Reply', 'sage-front'),
      // The accessible name of the link; core would otherwise build it from
      // its own untranslated "Reply to %s".
      'reply_to_text' => __('Reply to %s', 'sage-front'),
    ]),
    $comment,
  );
  $moderationNotice = __('Your comment is awaiting moderation.', 'sage-front');
@endphp

<li id="comment-{{ $commentId }}" @php(comment_class('list-none', $comment))>
  {{-- comment-reply.js moves the form below this element, which it finds by the
       id the reply link points at (`add_below` above), so the id belongs on the
       body of the comment and not on the item that also holds the replies. --}}
  <article id="div-comment-{{ $commentId }}" class="bg-background rounded-2xl border border-[#E0E0D7] p-3 md:p-4">
    <header class="flex items-center gap-3">
      <span
        class="bg-secondary text-green-default flex size-10 shrink-0 items-center justify-center rounded-full text-[16px] font-semibold uppercase"
        aria-hidden="true"
      >
        {{ $initial }}
      </span>

      <div class="flex min-w-0 flex-col">
        <span class="text-gray-1 truncate text-[14px] leading-5 font-semibold md:text-[16px]">
          {!! get_comment_author_link($commentId) !!}
        </span>

        <time class="text-body-13 text-[#969998]" datetime="{{ get_comment_time('c', false, true, $commentId) }}">
          {{ get_comment_date('d.m.Y', $commentId) }}
        </time>
      </div>
    </header>

    @if (!$isApproved)
      <p class="text-body-13 text-green-default border-green-easy bg-secondary mt-3 mb-0 rounded-xl border px-3 py-2">
        {{ $moderationNotice }}
      </p>
    @endif

    <div class="prose prose-content mt-3 text-[13px] md:text-[16px]">
      @php(comment_text($commentId))
    </div>

    @if ($replyLink)
      <footer
        class="[&_a]:text-green-default [&_a]:text-[13px] [&_a]:font-semibold [&_a]:underline [&_a]:hover:no-underline mt-3 flex"
      >
        {!! $replyLink !!}
      </footer>
    @endif
  </article>

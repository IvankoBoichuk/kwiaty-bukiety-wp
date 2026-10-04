<?php

namespace App\View\Composers;

use App\Support\Markup;
use Roots\Acorn\View\Composer;

class Comments extends Composer
{
    /**
     * List of views served by this composer.
     *
     * @var array
     */
    protected static $views = ['partials.comments'];

    /**
     * The field, label and note classes of the comment form.
     *
     * They are the ones the account and checkout forms already use: the article
     * page has no design of its own for comments, so the form borrows the one
     * the site states everywhere else rather than inventing a third.
     */
    private const FIELD_CLASS = 'text-green-default focus:border-green-easy w-full rounded-xl border border-[#DDD7CF] bg-[#FCF9F6] px-4 py-3 text-[14px] leading-5 placeholder:text-[#A4A094] focus:outline-none';

    private const LABEL_CLASS = 'text-green-default mb-1.5 block text-[14px] font-medium';

    private const NOTE_CLASS = 'text-body-13 m-0 text-[#5A6561]';

    /**
     * The comment title.
     */
    public function title(): string
    {
        return sprintf(
            /* translators: %1$s is replaced with the number of comments and %2$s with the post title */
            _nx(
                '%1$s response to &ldquo;%2$s&rdquo;',
                '%1$s responses to &ldquo;%2$s&rdquo;',
                get_comments_number(),
                'comments title',
                'sage-front',
            ),
            get_comments_number() === 1
                ? _x('One', 'comments title', 'sage-front')
                : number_format_i18n(get_comments_number()),
            get_the_title(),
        );
    }

    /**
     * Retrieve the comments.
     *
     * The default walker prints its own unstyled markup, and the theme drops
     * every core stylesheet, so each comment is drawn by partials/comment
     * instead. Walker_Comment opens the nesting list and closes the <li> around
     * whatever the callback returns, so the partial only draws the item itself.
     *
     * Avatars are off: avatar_default is the generic "mystery person", so every
     * comment on the page would cost a request to secure.gravatar.com to render
     * the same silhouette. partials/comment prints the author's initial.
     */
    public function responses(): ?string
    {
        if (!have_comments()) {
            return null;
        }

        return wp_list_comments([
            'style' => 'ol',
            'short_ping' => true,
            'echo' => false,
            'avatar_size' => 0,
            'callback' => static function (
                $comment,
                array $args,
                int $depth,
            ): void {
                echo view('partials.comment', [
                    'comment' => $comment,
                    'args' => $args,
                    'depth' => $depth,
                ])->render();
            },
        ]);
    }

    /**
     * The previous comments link.
     */
    public function previous(): ?string
    {
        if (!get_previous_comments_link()) {
            return null;
        }

        return get_previous_comments_link(
            __('&larr; Older comments', 'sage-front'),
        );
    }

    /**
     * The next comments link.
     */
    public function next(): ?string
    {
        if (!get_next_comments_link()) {
            return null;
        }

        return get_next_comments_link(
            __('Newer comments &rarr;', 'sage-front'),
        );
    }

    /**
     * Determine if the comments are paginated.
     */
    public function paginated(): bool
    {
        return get_comment_pages_count() > 1 && get_option('page_comments');
    }

    /**
     * Determine if the comments are closed.
     */
    public function closed(): bool
    {
        return !comments_open()
            && get_comments_number() != '0'
            && post_type_supports(get_post_type(), 'comments');
    }

    /**
     * The arguments comment_form() is drawn with.
     *
     * Every piece of the form is passed in rather than filtered afterwards:
     * comment_form() prints raw markup with no classes of its own, and the
     * theme ships no stylesheet that would catch it.
     *
     * Two fields, not three -- the stock "Website" field is dropped. It is the
     * one field a reader never has a reason to fill in and a link spammer
     * always does, and nothing in the templates prints the author URL.
     *
     * @return array<string, mixed>
     */
    public function formArgs(): array
    {
        $required = (bool) get_option('require_name_email');

        return [
            'format' => 'html5',
            'class_container' => 'comment-respond mt-10 flex flex-col gap-4 rounded-3xl border border-[#E0E0D7] bg-white p-4 md:p-6',
            'class_form' => 'comment-form m-0 grid gap-4 md:grid-cols-2',
            'class_submit' => Markup::buttonClasses('purple', 'md', false)
                . ' h-13 w-full text-[14px]! md:w-[280px] md:text-base!',
            'title_reply_before' => '<h3 id="reply-title" class="comment-reply-title text-green-default m-0 text-[18px] leading-normal font-bold md:text-[22px]">',
            'title_reply_after' => '</h3>',
            'title_reply' => __('Leave a comment', 'sage-front'),
            /* translators: %s is replaced with the name of the comment author being replied to */
            'title_reply_to' => __('Reply to %s', 'sage-front'),
            'cancel_reply_before' => ' <span class="text-body-13 font-normal [&_a]:text-[#2F80ED] [&_a]:underline [&_a]:hover:no-underline">',
            'cancel_reply_after' => '</span>',
            'cancel_reply_link' => __('Cancel reply', 'sage-front'),
            'label_submit' => __('Publish comment', 'sage-front'),
            'submit_button' => '<button name="%1$s" type="submit" id="%2$s" class="%3$s">%4$s</button>',
            'submit_field' => '<p class="form-submit m-0 md:col-span-2">%1$s %2$s</p>',
            'comment_notes_before' => sprintf(
                '<p class="%s md:col-span-2">%s</p>',
                self::NOTE_CLASS,
                esc_html__(
                    'Your e-mail address will not be published. The fields marked with an asterisk are required.',
                    'sage-front',
                ),
            ),
            'logged_in_as' => $this->loggedInAs(),
            'must_log_in' => $this->mustLogIn(),
            'comment_field' => $this->commentField(),
            'fields' => array_filter([
                'author' => $this->field(
                    'author',
                    'comment_author',
                    __('Name', 'sage-front'),
                    'text',
                    'name',
                    $required,
                ),
                'email' => $this->field(
                    'email',
                    'comment_author_email',
                    __('E-mail', 'sage-front'),
                    'email',
                    'email',
                    $required,
                ),
                'cookies' => $this->consentField(),
            ]),
        ];
    }

    /**
     * One text field of the form, prefilled from the commenter cookie.
     */
    private function field(
        string $name,
        string $valueKey,
        string $label,
        string $type,
        string $autocomplete,
        bool $required,
    ): string {
        $commenter = wp_get_current_commenter();

        return sprintf(
            '<p class="comment-form-%1$s m-0 flex flex-col">'
            . '<label class="%2$s" for="%1$s">%3$s%4$s</label>'
            . '<input class="%5$s" id="%1$s" name="%1$s" type="%6$s" value="%7$s" autocomplete="%8$s" maxlength="245"%9$s />'
            . '</p>',
            esc_attr($name),
            self::LABEL_CLASS,
            esc_html($label),
            $required ? $this->requiredMark() : '',
            self::FIELD_CLASS,
            esc_attr($type),
            esc_attr((string) ($commenter[$valueKey] ?? '')),
            esc_attr($autocomplete),
            $required ? ' required="required"' : '',
        );
    }

    /**
     * The comment textarea. It is the only field that spans both columns.
     */
    private function commentField(): string
    {
        return sprintf(
            '<p class="comment-form-comment m-0 flex flex-col md:col-span-2">'
            . '<label class="%1$s" for="comment">%2$s%3$s</label>'
            . '<textarea class="%4$s" id="comment" name="comment" rows="5" maxlength="65525" placeholder="%5$s" required="required"></textarea>'
            . '</p>',
            self::LABEL_CLASS,
            esc_html__('Comment', 'sage-front'),
            $this->requiredMark(),
            self::FIELD_CLASS,
            esc_attr__('Share your thoughts about this article', 'sage-front'),
        );
    }

    /**
     * The cookie consent checkbox, when the site asks for it.
     *
     * It has to be passed as the `cookies` field and not appended elsewhere:
     * comment_form() injects its own unstyled copy into the field list whenever
     * the consent is switched on and the arguments do not carry that key, so
     * putting the styled one anywhere else prints the checkbox twice. A
     * logged-in commenter leaves no cookie and comment_form() skips the field
     * for them on its own.
     */
    private function consentField(): string
    {
        $wantsConsent = has_action(
            'set_comment_cookies',
            'wp_set_comment_cookies',
        ) && get_option('show_comments_cookies_opt_in');

        if (!$wantsConsent) {
            return '';
        }

        $commenter = wp_get_current_commenter();

        return sprintf(
            '<p class="comment-form-cookies-consent m-0 flex items-start gap-2 md:col-span-2">'
            . '<input class="accent-green-default mt-0.5 size-4 shrink-0 rounded border-[#DDD7CF]" id="wp-comment-cookies-consent" name="wp-comment-cookies-consent" type="checkbox" value="yes"%1$s />'
            . '<label class="%2$s mb-0" for="wp-comment-cookies-consent">%3$s</label>'
            . '</p>',
            empty($commenter['comment_author_email'])
                ? ''
                : ' checked="checked"',
            self::NOTE_CLASS,
            esc_html__(
                'Save my name and e-mail in this browser for my next comment.',
                'sage-front',
            ),
        );
    }

    /**
     * The "logged in as" line above the form.
     */
    private function loggedInAs(): string
    {
        $user = wp_get_current_user();

        if (!$user->exists()) {
            return '';
        }

        return sprintf(
            '<p class="logged-in-as %1$s md:col-span-2">%2$s <a class="text-[#2F80ED] underline hover:no-underline" href="%3$s">%4$s</a></p>',
            self::NOTE_CLASS,
            sprintf(
                /* translators: %s is replaced with the display name of the logged-in user */
                esc_html__('Commenting as %s.', 'sage-front'),
                esc_html($user->display_name),
            ),
            esc_url(
                wp_logout_url(
                    (string) apply_filters(
                        'the_permalink',
                        get_permalink(),
                        get_queried_object_id(),
                    ),
                ),
            ),
            esc_html__('Log out', 'sage-front'),
        );
    }

    /**
     * The line that replaces the form when only members may comment.
     */
    private function mustLogIn(): string
    {
        return sprintf(
            '<p class="must-log-in %1$s"><a class="text-[#2F80ED] underline hover:no-underline" href="%2$s">%3$s</a></p>',
            self::NOTE_CLASS,
            esc_url(wp_login_url((string) get_permalink())),
            esc_html__(
                'You have to be logged in to post a comment.',
                'sage-front',
            ),
        );
    }

    private function requiredMark(): string
    {
        return ' <span class="text-[#C6463D]" aria-hidden="true">*</span>';
    }
}

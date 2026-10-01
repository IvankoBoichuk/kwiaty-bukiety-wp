<!doctype html>
<html @php(language_attributes())>
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  @php(do_action('get_header'))
  @php(wp_head())
  @php($webfonts = \App\Support\Markup::viteAsset('webfonts.css'))

  @if ($webfonts)
    <link
      rel="preload"
      as="style"
      href="{{ $webfonts }}"
      onload="
        this.onload = null;
        this.rel = 'stylesheet';
      "
    />
    <noscript><link rel="stylesheet" href="{{ $webfonts }}" /></noscript>
  @endif
</head>

<body @php(body_class()) x-data="mobileMenu">
  @php(wp_body_open())

  @include('sections.header')
  <main id="main" class="flex grow flex-col flex-1 gap-12 bg-background lg:gap-25 xl:gap-35">
    <!-- pb-12 lg:pb-25 -->
    <a class="sr-only focus:not-sr-only" href="#main"> {{ __('Skip to content', 'sage-front') }} </a>
    @yield('content')
  </main>
  @hasSection('sidebar')
    <aside class="sidebar">
      @yield('sidebar')
    </aside>
  @endif

  @include('sections.footer')

  @php(do_action('get_footer'))
  @stack('scripts')
  @php(wp_footer())
</body>
</html>

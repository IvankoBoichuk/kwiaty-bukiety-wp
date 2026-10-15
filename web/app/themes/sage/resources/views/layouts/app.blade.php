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

  {{-- First in the body so a keyboard user can jump past the header. --}}
  <a class="sr-only focus:not-sr-only" href="#main"> {{ __('Skip to content', 'sage-front') }} </a>

  @include('sections.header')
  {{-- page-flow: the vertical spacing between sections, see the "Section
       flow" block in app.css. --}}
  <main id="main" class="page-flow grow flex-1 bg-background">
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

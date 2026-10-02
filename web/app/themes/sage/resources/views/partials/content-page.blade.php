{{--
  The editor content of a WordPress page. The <section> is what picks up the
  page gutter: app.css applies mx-container to every section in the base layer.
  The measure is capped at the width the design lays the article out in (node
  702-8385), so the copy does not run the full 1680px the container allows.
--}}
<section class="py-8 lg:py-12">
  <div class="prose prose-content mx-auto w-full max-w-[1040px]">
    @php(the_content())
  </div>

  @if ($pagination())
    <nav class="page-nav text-body-13 md:text-body-16 mx-auto mt-8 w-full max-w-[1040px]" aria-label="Page">
      {!! $pagination !!}
    </nav>
  @endif
</section>

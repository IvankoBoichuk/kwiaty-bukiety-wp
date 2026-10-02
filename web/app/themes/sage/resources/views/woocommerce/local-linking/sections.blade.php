{{--
  City cross-linking printed under the catalogue on a local (city) category.

  archive-product.blade.php fires woocommerce_after_main_content outside its own
  container, so the wrapper carries the gutter itself.
--}}
<div class="local-links mx-container flex flex-col gap-8 pb-12 lg:pb-20">
  @include('woocommerce.local-linking.near', ['near' => $near])
  @include('woocommerce.local-linking.popular', ['popular' => $popular, 'woj' => $woj])
  @include('woocommerce.local-linking.back', ['backlink' => $backlink])
</div>

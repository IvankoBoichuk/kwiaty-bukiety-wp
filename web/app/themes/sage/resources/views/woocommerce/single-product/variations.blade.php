<div class="space-y-4" id="product-variations">
  @foreach ($attributeGroups as $attributeGroup)
    <div data-attribute-group="{{ $attributeGroup['key'] }}">
      @if ($attributeGroup['label'])
        @include('elements.label-product-setting',
          [
            'label' => $attributeGroup['label'],
            'icon' => $attributeGroup['icon'] ?? null
          ])
      @endif

      <div class="flex flex-wrap gap-2">
        @foreach ($attributeGroup['options'] as $option)
          <button
            type="button"
            class="group single-product-settings-option flex flex-1 flex-col items-center justify-center gap-1"
            data-attribute-name="{{ $attributeGroup['key'] }}"
            data-attribute-value="{{ $option['value'] }}"
            aria-pressed="false"
          >
            <div class="text-gray-2 group-[.active]:text-background flex items-center gap-1.5">
              <span class="text-[14px] leading-tight font-medium">{{ $option['label'] }}</span>
              @if ($option['priceHtml'] !== '')
                <span class="text-[15px] leading-tight font-bold">{!! $option['priceHtml'] !!}</span>
              @endif
            </div>

            @if ($option['description'] !== '')
              <span class="text-gray-3 group-[.active]:text-background text-center text-sm">
                {!! wp_kses_post($option['description']) !!}
              </span>
            @endif
          </button>
        @endforeach
      </div>
    </div>

  @endforeach
</div>

@php
  $badges ??= [];
  $wrapperClass ??= 'absolute top-1 left-1 flex flex-wrap gap-1.5';
@endphp

@if (!empty($badges))
  <div @class($wrapperClass)>
    @foreach ($badges as $key => $label)
      @php
        $bg = match ($key) {
          'free-delivery' => 'bg-purple-dark',
          'discount' => 'bg-green-500',
          'express' => 'bg-red-500',
          default => 'bg-purple',
        };
      @endphp
      <span
        @class([
          'flex items-center px-3 py-1.5 backdrop-blur-md text-white text-[11px] leading-[13px] md:text-[13px] md:leading-[15px] font-medium uppercase rounded-full whitespace-nowrap',
          $bg
        ])
        >{{ $label }}</span
      >
    @endforeach
  </div>
@endif

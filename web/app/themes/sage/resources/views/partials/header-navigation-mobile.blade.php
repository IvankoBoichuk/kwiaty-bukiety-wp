<button
  type="button"
  @click="toggleMenu()"
  class="bg-background inline-flex size-11 items-center justify-center rounded-full border border-[#426E59] text-[#244734] transition-colors duration-200 hover:bg-[#F3F7F4]"
  :aria-expanded="isOpen.toString()"
  aria-controls="mobile-navigation"
  aria-label="{{ __('Toggle navigation', 'sage-back') }}"
>
  <svg x-show="!isOpen" class="size-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
  </svg>
  <svg x-show="isOpen" x-cloak class="size-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 6l12 12M18 6l-12 12" />
  </svg>
</button>

<div
  x-show="isOpen"
  @click="closeMenu()"
  x-transition:enter="transition-opacity ease-out duration-300"
  x-transition:enter-start="opacity-0"
  x-transition:enter-end="opacity-100"
  x-transition:leave="transition-opacity ease-in duration-200"
  x-transition:leave-start="opacity-100"
  x-transition:leave-end="opacity-0"
  class="fixed inset-0 z-40 bg-black/50"
  x-cloak
></div>
<div
  id="mobile-navigation"
  x-show="isOpen"
  x-transition:enter="transition ease-out duration-300"
  x-transition:enter-start="translate-x-full"
  x-transition:enter-end="translate-x-0"
  x-transition:leave="transition ease-in duration-200"
  x-transition:leave-start="translate-x-0"
  x-transition:leave-end="translate-x-full"
  class="bg-background fixed top-0 right-0 bottom-0 z-50 flex w-80 max-w-[85vw] flex-col overflow-y-auto overscroll-contain border-l border-[#426E59]"
  @keydown.escape.window="closeMenu()"
  x-cloak
>
  <nav class="flex-1 overflow-y-auto px-4 pt-4 pb-16" aria-label="{{ wp_get_nav_menu_name('primary_navigation') }}">
    <div x-data="accordion">
      @foreach ($menu as $item)
        <div class="border-b border-gray-200 last:border-b-0">
          @if (!empty($item['children']))
            <button
              @click="toggle('parent-{{ $loop->index }}')"
              type="button"
              class="flex w-full items-center justify-between py-3 text-left font-medium"
            >
              <span>{{ $item['title'] }}</span>
              <svg
                class="size-5 transition-transform"
                :class="isActive('parent-{{ $loop->index }}') ? 'rotate-180' : ''"
                fill="none"
                stroke="currentColor"
                viewBox="0 0 24 24"
              >
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
              </svg>
            </button>
            <div x-show="isActive('parent-{{ $loop->index }}')" x-collapse class="pb-2 pl-4">
              <div x-data="accordion">
                @foreach ($item['children'] as $child)
                  @if (!empty($child['children']))
                    <div class="my-2 border-l-2 border-gray-300">
                      <button
                        @click="toggle('child-{{ $loop->parent->index }}-{{ $loop->index }}')"
                        type="button"
                        class="flex w-full items-center justify-between py-2 pl-3 text-left"
                      >
                        <span class="text-sm">{{ $child['title'] }}</span>
                        <svg
                          class="size-4 transition-transform"
                          :class="isActive('child-{{ $loop->parent->index }}-{{ $loop->index }}') ? 'rotate-180' : ''"
                          fill="none"
                          stroke="currentColor"
                          viewBox="0 0 24 24"
                        >
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                        </svg>
                      </button>

                      <div
                        x-show="isActive('child-{{ $loop->parent->index }}-{{ $loop->index }}')"
                        x-collapse
                        class="pb-2 pl-4"
                      >
                        @foreach ($child['children'] as $subchild)
                          <a
                            href="{{ $subchild['url'] }}"
                            class="hover:text-primary block py-2 pl-3 text-sm text-gray-600"
                            @click="closeMenu()"
                          >
                            {{ $subchild['title'] }}
                          </a>
                        @endforeach
                      </div>
                    </div>
                  @else
                    <a
                      href="{{ $child['url'] }}"
                      class="hover:text-primary block py-2 pl-3 text-sm text-gray-600"
                      @click="closeMenu()"
                    >
                      {{ $child['title'] }}
                    </a>
                  @endif
                @endforeach
              </div>
            </div>
          @else
            <a href="{{ $item['url'] }}" class="block py-3 font-medium" @click="closeMenu()"> {{ $item['title'] }} </a>
          @endif
        </div>
      @endforeach
    </div>
  </nav>
</div>

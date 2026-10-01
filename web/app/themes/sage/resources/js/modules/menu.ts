import Alpine from 'alpinejs';

const SEARCH_INPUT_ID = 'header-search';

export function initMenu() {
  Alpine.data('mobileMenu', () => ({
    isOpen: false,
    isSearchOpen: false,
    activeDesktopMenu: null as string | null,

    toggleMenu() {
      this.isOpen = !this.isOpen;
      this.activeDesktopMenu = null;
      this.isSearchOpen = false;

      // Prevent body scroll when menu is open
      if (this.isOpen) {
        document.body.style.overflow = 'hidden';
      } else {
        document.body.style.overflow = '';
      }
    },

    closeMenu() {
      this.isOpen = false;
      this.activeDesktopMenu = null;
      document.body.style.overflow = '';
    },

    openDesktopMenu(id: string) {
      if (!window.matchMedia('(min-width: 1024px)').matches) {
        return;
      }

      this.activeDesktopMenu = id;
    },

    closeDesktopMenu() {
      this.activeDesktopMenu = null;
    },

    isDesktopMenuActive(id: string) {
      return this.activeDesktopMenu === id;
    },

    hasDesktopMenuOpen() {
      return this.activeDesktopMenu !== null;
    },

    getDesktopMenuTop() {
      const header = document.getElementById('header');
      return header ? header.getBoundingClientRect().bottom : 0;
    },

    /**
     * Toggles the header search panel. Called by the header's search icon and
     * by the mobile bottom bar, which both sit inside this component.
     */
    search() {
      if (this.isSearchOpen) {
        this.closeSearch();
        return;
      }

      this.openSearch();
    },

    openSearch() {
      this.isSearchOpen = true;

      // The drawer and the mega menu both cover the panel, so whichever is
      // open steps aside -- and the drawer takes the scroll lock with it.
      this.isOpen = false;
      this.activeDesktopMenu = null;
      document.body.style.overflow = '';

      // The field is behind x-show and cannot take focus until Alpine has
      // flushed that update; a frame later it is in the document.
      window.requestAnimationFrame(() => {
        const input = document.getElementById(SEARCH_INPUT_ID);

        if (input instanceof HTMLInputElement) {
          input.focus();
          input.select();
        }
      });
    },

    closeSearch() {
      this.isSearchOpen = false;
    },
  }));

  Alpine.data('accordion', () => ({
    activeItem: null as string | null,

    toggle(id: string) {
      this.activeItem = this.activeItem === id ? null : id;
    },

    isActive(id: string) {
      return this.activeItem === id;
    },
  }));
}

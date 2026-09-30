import Alpine from 'alpinejs';

export function initMenu() {
  Alpine.data('mobileMenu', () => ({
    isOpen: false,
    activeDesktopMenu: null as string | null,

    toggleMenu() {
      this.isOpen = !this.isOpen;
      this.activeDesktopMenu = null;

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

    search() {
      // Placeholder for search functionality
      // You can implement search modal or redirect here
    }
  }));

  Alpine.data('accordion', () => ({
    activeItem: null as string | null,

    toggle(id: string) {
      this.activeItem = this.activeItem === id ? null : id;
    },

    isActive(id: string) {
      return this.activeItem === id;
    }
  }));
}

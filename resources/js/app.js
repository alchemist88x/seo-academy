function initAlpineStores() {
    const Alpine = window.Alpine;
    if (!Alpine || (typeof Alpine.store === 'function' && Alpine.store('theme'))) return;

    // Theme Store: light | dark | system
    Alpine.store('theme', {
        mode: localStorage.getItem('seo_academy_theme') || 'system',

        init() {
            this.apply();

            window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', () => {
                if (this.mode === 'system') {
                    this.apply();
                }
            });
        },

        set(mode) {
            this.mode = mode;
            localStorage.setItem('seo_academy_theme', mode);
            this.apply();
        },

        toggle() {
            const isDark = document.documentElement.classList.contains('dark');
            this.set(isDark ? 'light' : 'dark');
        },

        apply() {
            const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
            const shouldBeDark = this.mode === 'dark' || (this.mode === 'system' && prefersDark);

            document.documentElement.classList.toggle('dark', shouldBeDark);
            document.documentElement.classList.toggle('light', !shouldBeDark);
        },

        get isDark() {
            const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
            return this.mode === 'dark' || (this.mode === 'system' && prefersDark);
        },
    });

    // Sidebar Store: collapsed on desktop, open on mobile
    Alpine.store('sidebar', {
        collapsed: localStorage.getItem('seo_academy_sidebar_collapsed') === 'true',
        mobileOpen: false,

        toggleCollapse() {
            this.collapsed = !this.collapsed;
            localStorage.setItem('seo_academy_sidebar_collapsed', this.collapsed);
        },

        toggleMobile() {
            this.mobileOpen = !this.mobileOpen;
        },

        closeMobile() {
            this.mobileOpen = false;
        },
    });

    // Toast Store: global client-side toast notifications
    Alpine.store('toasts', {
        items: [],
        counter: 0,

        add({ title = '', message = '', type = 'info', duration = 4500 } = {}) {
            const id = ++this.counter;
            this.items.push({ id, title, message, type });

            if (duration > 0) {
                setTimeout(() => this.remove(id), duration);
            }
        },

        remove(id) {
            this.items = this.items.filter((item) => item.id !== id);
        },
    });
}

if (window.Alpine) {
    initAlpineStores();
} else {
    document.addEventListener('alpine:init', initAlpineStores);
    document.addEventListener('livewire:init', () => {
        if (window.Alpine) initAlpineStores();
    });
    document.addEventListener('DOMContentLoaded', () => {
        if (window.Alpine) initAlpineStores();
    });
}

// Listen for global toast events (from Livewire or JS)
window.addEventListener('toast', (event) => {
    const detail = Array.isArray(event.detail) ? event.detail[0] : event.detail;
    if (window.Alpine && typeof window.Alpine.store === 'function' && window.Alpine.store('toasts')) {
        window.Alpine.store('toasts').add(detail || {});
    }
});

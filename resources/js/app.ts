import 'bootstrap';
import PerfectScrollbar from 'perfect-scrollbar';
import { createApp, h } from 'vue';
import { createInertiaApp } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';

(window as any).PerfectScrollbar = PerfectScrollbar;

createInertiaApp({
    title: (title) => title ? `${title} · Dexterton WorkHub` : 'Dexterton WorkHub',
    resolve: (name) => resolvePageComponent(`./Pages/${name}.vue`, import.meta.glob('./Pages/**/*.vue')),
    setup({ el, App, props, plugin }) {
        createApp({ render: () => h(App, props) }).use(plugin).mount(el);
        import('../vendor/mazer/static/js/sidebar');
    },
    progress: { color: '#435ebe' },
});

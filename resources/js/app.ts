import "../css/app.css";

import { createApp, h } from "vue";
import { createInertiaApp, router } from "@inertiajs/vue3";
import { resolvePageComponent } from "laravel-vite-plugin/inertia-helpers";
import { createPinia } from "pinia";
import { ZiggyVue } from "../../vendor/tightenco/ziggy";
import { digits } from "./directives/digits.js";
import { flushMetaPixel } from "./support/metaPixel.js";
import type { DefineComponent } from "vue";

router.on("navigate", (event) => flushMetaPixel(event.detail.page.props.metaPixel as unknown[]));

const appName = (import.meta.env.VITE_APP_NAME as string) || "DeBoletoMX";

createInertiaApp({
    title: (title) => (title ? `${title} — ${appName}` : appName),
    resolve: (name) =>
        resolvePageComponent(
            `./Pages/${name}.vue`,
            import.meta.glob<DefineComponent>("./Pages/**/*.vue"),
        ),
    setup({ el, App, props, plugin }) {
        const pinia = createPinia();

        createApp({ render: () => h(App, props) })
            .use(plugin)
            .use(pinia)
            .use(ZiggyVue)
            .directive("digits", digits)
            .mount(el);
    },
    progress: {
        color: "#2ba17c",
    },
});

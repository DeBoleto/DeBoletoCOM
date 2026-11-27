<script>
import { h } from "vue";
import { Head, usePage } from "@inertiajs/vue3";

export default {
    name: "SeoHead",
    setup() {
        const page = usePage();

        const meta = (key, attrs) => h("meta", { "head-key": key, ...attrs });
        const json = (value) =>
            JSON.stringify(value)
                .replace(/</g, "\\u003C")
                .replace(/>/g, "\\u003E")
                .replace(/&/g, "\\u0026");

        return () => {
            const seo = page.props.seo;

            if (!seo) {
                return null;
            }

            const title = seo.title || seo.siteName;
            const image = seo.image || {};

            const tags = [
                meta("description", { name: "description", content: seo.description }),
                meta("robots", { name: "robots", content: seo.robots }),
                h("link", { "head-key": "canonical", rel: "canonical", href: seo.url }),
                meta("og:site_name", { property: "og:site_name", content: seo.siteName }),
                meta("og:locale", { property: "og:locale", content: "es_MX" }),
                meta("og:type", { property: "og:type", content: seo.type }),
                meta("og:url", { property: "og:url", content: seo.url }),
                meta("og:title", { property: "og:title", content: title }),
                meta("og:description", { property: "og:description", content: seo.description }),
                meta("og:image", { property: "og:image", content: image.url }),
            ];

            if (image.width && image.height) {
                tags.push(
                    meta("og:image:width", { property: "og:image:width", content: String(image.width) }),
                    meta("og:image:height", { property: "og:image:height", content: String(image.height) }),
                );
            }

            tags.push(
                meta("twitter:card", { name: "twitter:card", content: "summary_large_image" }),
                meta("twitter:title", { name: "twitter:title", content: title }),
                meta("twitter:description", { name: "twitter:description", content: seo.description }),
                meta("twitter:image", { name: "twitter:image", content: image.url }),
                ...(seo.schemas || []).map((schema, index) =>
                    h("script", { "head-key": `schema-${index}`, type: "application/ld+json" }, json(schema)),
                ),
            );

            return h(Head, { title: seo.title }, () => tags);
        };
    },
};
</script>

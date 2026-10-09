import { getCurrentInstance } from "vue";
import VueKonva from "vue-konva";

export function registerKonva() {
    const app = getCurrentInstance()?.appContext.app;
    if (!app || app.component("VStage")) return;
    app.use(VueKonva);
}

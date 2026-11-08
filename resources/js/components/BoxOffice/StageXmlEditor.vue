<template>
    <div class="grid gap-4 lg:grid-cols-2">
        <label class="text-sm font-medium">
            <span class="mb-1.5 block">{{ label }}</span>
            <textarea
                :value="modelValue"
                rows="18"
                spellcheck="false"
                class="block w-full rounded-lg border-bo-line font-mono text-xs leading-relaxed focus:border-bo-green focus:ring-bo-green"
                @input="emit('update:modelValue', $event.target.value)"
            />
            <span v-if="error" class="mt-1.5 block text-sm text-bo-sold">{{ error }}</span>
        </label>
        <div>
            <div class="mb-1.5 flex items-center justify-between text-sm">
                <span class="font-medium">Vista previa</span>
                <button type="button" class="inline-flex items-center gap-1 font-semibold text-bo-green hover:text-bo-green-dark disabled:opacity-50" :disabled="!modelValue || loading" @click="preview">
                    <BoIcon name="refresh" :size="14" />
                    {{ loading ? "Leyendo…" : "Actualizar" }}
                </button>
            </div>
            <div class="min-h-72 rounded-lg border border-bo-line bg-bo-paper p-2">
                <ZoneCanvas v-if="stage?.sections.length" :sections="stage.sections" :canvas-size="stage.canvas" />
                <p v-else class="p-6 text-center text-sm text-bo-slate">{{ previewError || "Pega el XML y pulsa Actualizar para ver las zonas." }}</p>
            </div>
            <p v-if="stage" class="tabular mt-2 text-xs text-bo-slate">
                {{ stage.sections.length }} secciones, {{ seatCount }} asientos numerados
            </p>
        </div>
    </div>
</template>

<script setup>
import { computed, ref } from "vue";
import BoIcon from "./BoIcon.vue";
import ZoneCanvas from "@/components/seat-map/ZoneCanvas.vue";

const props = defineProps({
    modelValue: { type: String, default: "" },
    label: { type: String, default: "XML del mapa" },
    error: { type: String, default: "" },
});

const emit = defineEmits(["update:modelValue"]);
const stage = ref(null);
const loading = ref(false);
const previewError = ref("");

const seatCount = computed(() => (stage.value?.sections ?? []).reduce((sum, section) => sum + section.rows.reduce((rows, row) => rows + row.seats.length, 0), 0));

function csrfToken() {
    const match = document.cookie.match(/(?:^|; )XSRF-TOKEN=([^;]+)/);
    return match ? decodeURIComponent(match[1]) : "";
}

async function preview() {
    loading.value = true;
    previewError.value = "";
    try {
        const response = await fetch(route("admin.venues.preview"), {
            method: "POST",
            credentials: "same-origin",
            headers: { Accept: "application/json", "Content-Type": "application/json", "X-XSRF-TOKEN": csrfToken() },
            body: JSON.stringify({ xml: props.modelValue }),
        });
        const data = await response.json().catch(() => ({}));
        if (!response.ok) {
            stage.value = null;
            previewError.value = data.message ?? "El XML no es válido.";
            return;
        }
        stage.value = data;
    } catch {
        previewError.value = "No se pudo generar la vista previa.";
    } finally {
        loading.value = false;
    }
}
</script>

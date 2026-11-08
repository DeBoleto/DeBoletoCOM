<template>
    <BackOfficeLayout title="Control de acceso">
        <div class="mx-auto max-w-2xl">
            <form @submit.prevent="scan">
                <label for="code" class="mb-2 block text-sm font-medium">Escanea o escribe el código del boleto</label>
                <input
                    id="code"
                    ref="input"
                    v-model.trim="code"
                    type="text"
                    autocomplete="off"
                    autocapitalize="off"
                    spellcheck="false"
                    class="tabular block w-full rounded-xl border-bo-line py-4 text-center text-2xl tracking-wider focus:border-bo-green focus:ring-bo-green"
                    :disabled="busy"
                    @blur="refocus"
                />
            </form>

            <div
                v-if="last"
                class="mt-6 rounded-xl border-2 p-6 text-center"
                :class="last.success ? 'border-bo-green bg-bo-green-soft text-bo-green-dark' : 'border-bo-sold bg-bo-sold-soft text-bo-sold'"
                role="status"
                aria-live="assertive"
            >
                <BoIcon :name="last.success ? 'check' : 'alert'" :size="40" class="mx-auto" />
                <p class="mt-2 text-2xl font-semibold">{{ last.message }}</p>
                <p v-if="last.event" class="mt-1 text-bo-ink">{{ last.event }}</p>
                <p v-if="last.ticket" class="tabular mt-1 text-sm text-bo-slate">
                    Folio {{ last.ticket.id }}<template v-if="last.ticket.seat">, asiento {{ last.ticket.seat }}</template>
                </p>
                <p v-if="last.entered" class="tabular mt-3 text-sm text-bo-slate">{{ last.entered }} accesos registrados en este evento</p>
            </div>

            <section v-if="history.length" class="mt-8" aria-label="Últimos escaneos">
                <h2 class="mb-2 text-sm font-semibold text-bo-slate">Últimos escaneos</h2>
                <ul class="divide-y divide-bo-line rounded-xl border border-bo-line bg-bo-card text-sm">
                    <li v-for="(item, index) in history" :key="index" class="flex items-center justify-between gap-3 px-4 py-2">
                        <span class="tabular">{{ item.code }}</span>
                        <span class="truncate" :class="item.success ? 'text-bo-green-dark' : 'text-bo-sold'">{{ item.message }}</span>
                        <span class="tabular text-bo-muted">{{ item.scannedAt }}</span>
                    </li>
                </ul>
            </section>
        </div>
    </BackOfficeLayout>
</template>

<script setup>
import { nextTick, onMounted, ref } from "vue";
import BackOfficeLayout from "@/layouts/BackOfficeLayout.vue";
import BoIcon from "@/components/BoxOffice/BoIcon.vue";

const input = ref(null);
const code = ref("");
const busy = ref(false);
const last = ref(null);
const history = ref([]);

function csrfToken() {
    const match = document.cookie.match(/(?:^|; )XSRF-TOKEN=([^;]+)/);
    return match ? decodeURIComponent(match[1]) : "";
}

function refocus() {
    setTimeout(() => input.value?.focus(), 50);
}

async function scan() {
    if (!code.value || busy.value) return;

    const scanned = code.value;
    busy.value = true;

    try {
        const response = await fetch(route("boxoffice.count.scan"), {
            method: "POST",
            credentials: "same-origin",
            headers: { Accept: "application/json", "Content-Type": "application/json", "X-XSRF-TOKEN": csrfToken() },
            body: JSON.stringify({ codigo: scanned }),
        });
        const data = await response.json().catch(() => ({}));

        last.value = response.ok ? data : { success: false, message: data.message ?? "No se pudo registrar el código." };
    } catch {
        last.value = { success: false, message: "Sin conexión con el servidor." };
    } finally {
        history.value = [{ code: scanned, ...last.value, scannedAt: last.value.scannedAt ?? "" }, ...history.value].slice(0, 15);
        code.value = "";
        busy.value = false;
        await nextTick();
        input.value?.focus();
    }
}

onMounted(() => input.value?.focus());
</script>

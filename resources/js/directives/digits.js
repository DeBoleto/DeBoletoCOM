/**
 * v-digits: el campo solo acepta números. Bloquea lo que se escribe que no sea
 * dígito y limpia lo que se pega o autocompleta. `v-digits="false"` lo apaga.
 */
const enabled = (el) => el._digits !== false;

function onBeforeInput(event) {
    const el = event.currentTarget;
    if (!enabled(el) || !event.inputType?.startsWith("insert") || event.data == null) return;
    if (/\D/.test(event.data)) event.preventDefault();
}

function onInput(event) {
    const el = event.currentTarget;
    if (!enabled(el)) return;

    const clean = el.value.replace(/\D/g, "");
    if (clean === el.value) return;

    el.value = clean;
    el.dispatchEvent(new Event("input", { bubbles: true }));
}

export const digits = {
    mounted(el, binding) {
        el._digits = binding.value;
        if (binding.value !== false && !el.hasAttribute("inputmode")) el.setAttribute("inputmode", "numeric");
        el.addEventListener("beforeinput", onBeforeInput);
        el.addEventListener("input", onInput);
    },
    updated(el, binding) {
        el._digits = binding.value;
    },
    unmounted(el) {
        el.removeEventListener("beforeinput", onBeforeInput);
        el.removeEventListener("input", onInput);
    },
};

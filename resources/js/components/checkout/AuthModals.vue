<template>
    <PhoneLoginModal
        :show="current === 'phone'"
        @close="close"
        @switch-to-login="current = 'email'"
        @switch-to-register="current = 'register'"
        @code-sent="codeSent"
    />
    <VerifyCodeModal
        :show="current === 'verify'"
        :lada="phone.lada"
        :telefono="phone.telefono"
        :canal="phone.canal"
        @close="close"
        @verified="close"
        @resend="current = 'phone'"
        @switch-to-login="current = 'email'"
        @switch-to-register="current = 'register'"
    />
    <LoginModal :show="current === 'email'" @close="close" @switch-to-register="current = 'register'" />
    <RegisterModal :show="current === 'register'" @close="close" @switch-to-login="current = 'email'" />
</template>

<script setup>
import { computed, reactive, ref, watch } from "vue";
import { usePage } from "@inertiajs/vue3";
import PhoneLoginModal from "@/components/PhoneLoginModal.vue";
import VerifyCodeModal from "@/components/VerifyCodeModal.vue";
import LoginModal from "@/components/LoginModal.vue";
import RegisterModal from "@/components/RegisterModal.vue";

const emit = defineEmits(["authenticated"]);

const page = usePage();
const current = ref(null);
const phone = reactive({ lada: "52", telefono: "", canal: "whatsapp" });
const signedIn = computed(() => Boolean(page.props.customer));

watch(signedIn, (value, previous) => {
    if (value && !previous) {
        current.value = null;
        emit("authenticated");
    }
});

function open(mode = "phone") {
    current.value = mode;
}

function close() {
    current.value = null;
}

function codeSent(data) {
    Object.assign(phone, data);
    current.value = "verify";
}

defineExpose({ open, close });
</script>

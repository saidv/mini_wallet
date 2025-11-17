<script setup lang="ts">
import { onMounted } from 'vue';
import { useAuthStore } from '@/stores/auth';
import ToastNotification from '@/components/shared/ToastNotification.vue';

const authStore = useAuthStore();

onMounted(() => {
  authStore.initialize();
});
</script>

<template>
  <v-app>
    <ToastNotification />
    <router-view />
  </v-app>
</template>

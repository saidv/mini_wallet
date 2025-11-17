<script setup lang="ts">
import { useToast } from '@/composables/useToast'

const { toasts, remove } = useToast()

const getIcon = (type: string) => {
  switch (type) {
    case 'success': return 'mdi-check-circle'
    case 'error': return 'mdi-alert-circle'
    case 'warning': return 'mdi-alert'
    case 'info': return 'mdi-information'
    default: return 'mdi-information'
  }
}

const getColor = (type: string) => {
  switch (type) {
    case 'success': return 'success'
    case 'error': return 'error'
    case 'warning': return 'warning'
    case 'info': return 'info'
    default: return 'info'
  }
}
</script>

<template>
  <div class="toast-container">
    <transition-group name="toast" tag="div">
      <v-alert
        v-for="toast in toasts"
        :key="toast.id"
        :type="getColor(toast.type)"
        :icon="getIcon(toast.type)"
        variant="elevated"
        closable
        class="toast-item mb-3"
        elevation="6"
        @click:close="remove(toast.id)"
      >
        <template #title>
          <span class="font-weight-bold">{{ toast.title }}</span>
        </template>
        {{ toast.message }}
      </v-alert>
    </transition-group>
  </div>
</template>

<style scoped>
.toast-container {
  position: fixed;
  top: 80px;
  right: 20px;
  z-index: 9999;
  max-width: 400px;
  width: 100%;
}

.toast-item {
  margin-bottom: 12px;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
}

/* Transition animations */
.toast-enter-active,
.toast-leave-active {
  transition: all 0.3s ease;
}

.toast-enter-from {
  opacity: 0;
  transform: translateX(100%);
}

.toast-leave-to {
  opacity: 0;
  transform: translateX(100%);
}
</style>

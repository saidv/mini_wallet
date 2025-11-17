import { ref } from 'vue'

export interface Toast {
  id: number
  type: 'success' | 'error' | 'info' | 'warning'
  title: string
  message: string
  duration?: number
}

const toasts = ref<Toast[]>([])
let nextId = 1

export function useToast() {
  const show = (toast: Omit<Toast, 'id'>) => {
    const id = nextId++
    const duration = toast.duration || 5000

    toasts.value.push({
      ...toast,
      id,
      duration
    })

    // Auto-remove after duration
    setTimeout(() => {
      remove(id)
    }, duration)
  }

  const success = (title: string, message: string) => {
    show({ type: 'success', title, message })
  }

  const error = (title: string, message: string) => {
    show({ type: 'error', title, message })
  }

  const info = (title: string, message: string) => {
    show({ type: 'info', title, message })
  }

  const warning = (title: string, message: string) => {
    show({ type: 'warning', title, message })
  }

  const remove = (id: number) => {
    const index = toasts.value.findIndex(t => t.id === id)
    if (index > -1) {
      toasts.value.splice(index, 1)
    }
  }

  return {
    toasts,
    show,
    success,
    error,
    info,
    warning,
    remove
  }
}

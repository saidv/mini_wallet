<script setup lang="ts">
import { computed } from 'vue'
import { useAuthStore } from '@/stores/auth'
import type { Transaction } from '@/api/transactions'

interface Props {
  transactions: Transaction[]
  loading?: boolean
  showActions?: boolean
  compact?: boolean
}

const props = withDefaults(defineProps<Props>(), {
  loading: false,
  showActions: false,
  compact: false
})

const authStore = useAuthStore()

const getTransactionDirection = (transaction: Transaction) => {
  return transaction.sender_id === authStore.user?.id ? 'sent' : 'received'
}

const getTransactionOtherParty = (transaction: Transaction) => {
  if (transaction.sender_id === authStore.user?.id) {
    return transaction.receiver
  }
  return transaction.sender
}

const getStatusColor = (status: string) => {
  switch (status) {
    case 'completed':
      return 'success'
    case 'failed':
      return 'error'
    case 'pending':
      return 'warning'
    default:
      return 'secondary'
  }
}

const getDirectionIcon = (transaction: Transaction) => {
  return getTransactionDirection(transaction) === 'sent' 
    ? 'mdi-arrow-up' 
    : 'mdi-arrow-down'
}

const getDirectionColor = (transaction: Transaction) => {
  return getTransactionDirection(transaction) === 'sent' 
    ? 'error' 
    : 'success'
}

const formatAmount = (amount: number) => {
  return (amount / 100).toFixed(2)
}

const formatDate = (dateString: string) => {
  const date = new Date(dateString)
  return new Intl.DateTimeFormat('en-US', {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
    hour: '2-digit',
    minute: '2-digit'
  }).format(date)
}

const formatDateCompact = (dateString: string) => {
  const date = new Date(dateString)
  return new Intl.DateTimeFormat('en-US', {
    month: 'short',
    day: 'numeric',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit'
  }).format(date)
}

const copyToClipboard = async (text: string) => {
  try {
    await navigator.clipboard.writeText(text)
  } catch (error) {
    console.error('Failed to copy:', error)
  }
}
</script>

<template>
    <v-table class="bordered-table" hover density="comfortable">
        <thead class="bg-containerBg">
        <tr>
            <th class="text-left text-caption font-weight-bold text-uppercase">Date & Time</th>
            <th class="text-left text-caption font-weight-bold text-uppercase">Transaction</th>
            <th class="text-left text-caption font-weight-bold text-uppercase">Party</th>
            <th class="text-right text-caption font-weight-bold text-uppercase">Amount</th>
            <th class="text-center text-caption font-weight-bold text-uppercase">Status</th>
            <th v-if="showActions" class="text-center text-caption font-weight-bold text-uppercase">Actions</th>
        </tr>
        </thead>
        <tbody>
        <tr v-for="transaction in transactions" :key="transaction.uuid" class="py-4">
            <!-- Date -->
            <td class="text-body-2">
            <div>{{ compact ? formatDateCompact(transaction.created_at) : formatDate(transaction.created_at) }}</div>
            </td>

            <!-- Transaction Type -->
            <td>
            <div v-if="compact">
                <v-chip
                :color="getDirectionColor(transaction)"
                variant="text"
                size="small"
                class="px-0"
                >
                <v-icon size="16" class="mr-1">
                    {{ getDirectionIcon(transaction) }}
                </v-icon>
                {{ getTransactionDirection(transaction) === 'sent' ? 'Sent' : 'Received' }}
                </v-chip>
                <div class="text-caption text-medium-emphasis">
                {{ transaction.uuid.substring(0, 8) }}...
                </div>
            </div>
            <div v-else class="d-flex align-center">
                <v-avatar
                size="32"
                :color="getDirectionColor(transaction)"
                class="mr-3"
                >
                <v-icon size="20" color="white">
                    {{ getDirectionIcon(transaction) }}
                </v-icon>
                </v-avatar>
                <div>
                <div class="text-body-2 font-weight-medium">
                    {{ getTransactionDirection(transaction) === 'sent' ? 'Sent Money' : 'Received Money' }}
                </div>
                <div class="text-caption text-medium-emphasis">
                    {{ transaction.uuid.substring(0, 8) }}...
                </div>
                </div>
            </div>
            </td>

            <!-- Other Party -->
            <td>
            <div class="text-body-2">
                {{ getTransactionOtherParty(transaction)?.name || 'Unknown' }}
            </div>
            <div v-if="!compact" class="text-caption text-medium-emphasis">
                {{ getTransactionOtherParty(transaction)?.email || 'N/A' }}
            </div>
            </td>

            <!-- Amount -->
            <td class="text-right">
            <div
                class="font-weight-medium"
                :class="[
                getTransactionDirection(transaction) === 'sent' ? 'text-error' : 'text-success',
                compact ? 'text-body-2' : 'text-body-1'
                ]"
            >
                {{ getTransactionDirection(transaction) === 'sent' ? '-' : '+' }}
                ${{ formatAmount(transaction.amount) }}
            </div>
            <div
                v-if="getTransactionDirection(transaction) === 'sent' && transaction.commission"
                class="text-caption text-medium-emphasis"
            >
                Fee: ${{ formatAmount(transaction.commission) }}
            </div>
            </td>

            <!-- Status -->
            <td class="text-center">
            <v-chip
                v-if="compact"
                variant="text"
                size="small"
                class="px-0"
            >
                <v-avatar 
                size="8" 
                :color="getStatusColor(transaction.status)" 
                variant="flat" 
                class="mr-2"
                />
                <span class="font-weight-bold">
                {{ transaction.status.charAt(0).toUpperCase() + transaction.status.slice(1) }}
                </span>
            </v-chip>
            <v-chip
                v-else
                :color="getStatusColor(transaction.status)"
                size="small"
                variant="flat"
            >
                {{ transaction.status }}
            </v-chip>
            </td>

            <!-- Actions -->
            <td v-if="showActions" class="text-center">
            <v-btn
                icon
                size="small"
                variant="text"
                @click="copyToClipboard(transaction.uuid)"
            >
                <v-icon size="20">mdi-content-copy</v-icon>
                <v-tooltip activator="parent" location="top">
                Copy Transaction ID
                </v-tooltip>
            </v-btn>
            </td>
        </tr>
        </tbody>
    </v-table>
</template>

<style scoped>
.bordered-table :deep(tbody tr:hover) {
  background-color: rgba(var(--v-theme-primary), 0.05);
}
</style>

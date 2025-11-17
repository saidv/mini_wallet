<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { transactionsApi } from '@/api/transactions'

const loading = ref(false)
const stats = ref({
  totalReceived: 0,
  totalSent: 0,
  totalTransactions: 0,
  totalCommission: 0
})

const formatCurrency = (cents: number): string => {
  return `$${(cents / 100).toFixed(2)}`
}

const loadStats = async () => {
  loading.value = true
  try {
    const data = await transactionsApi.getStats()

    stats.value = {
      totalReceived: data.total_received,
      totalSent: data.total_sent,
      totalTransactions: data.total_transactions,
      totalCommission: data.total_commission
    }
  } catch (error) {
    console.error('Error loading stats:', error)
  } finally {
    loading.value = false
  }
}

onMounted(() => {
  loadStats()
})

defineExpose({ loadStats })
</script>

<template>
  <v-row class="my-0 mt-14" dense>
    <!-- Received Card -->
    <v-col cols="12" sm="6" lg="6">
      <v-card elevation="0" variant="outlined">
        <v-card-text>
          <div class="d-flex align-items-center justify-space-between">
            <div class="flex-grow-1">
              <h6 class="text-subtitle-2 text-medium-emphasis mb-1">Total Received</h6>
              <h4 class="text-h4 font-weight-bold mb-0">
                <v-skeleton-loader v-if="loading" type="text" width="100" />
                <span v-else>{{ formatCurrency(stats.totalReceived) }}</span>
              </h4>
              <v-chip
                v-if="!loading && stats.totalReceived > 0"
                color="success"
                variant="tonal"
                size="x-small"
                class="mt-2"
                prepend-icon="mdi-arrow-down"
              >
                Income
              </v-chip>
            </div>
            <v-avatar color="lightsuccess" size="48" class="ml-3">
              <v-icon color="success" size="24">mdi-arrow-down-bold</v-icon>
            </v-avatar>
          </div>
        </v-card-text>
      </v-card>
    </v-col>

    <!-- Sent Card -->
    <v-col cols="12" sm="6" lg="6">
      <v-card elevation="0" variant="outlined">
        <v-card-text>
          <div class="d-flex align-items-center justify-space-between">
            <div class="flex-grow-1">
              <h6 class="text-subtitle-2 text-medium-emphasis mb-1">Total Sent</h6>
              <h4 class="text-h4 font-weight-bold mb-0">
                <v-skeleton-loader v-if="loading" type="text" width="100" />
                <span v-else>{{ formatCurrency(stats.totalSent) }}</span>
              </h4>
              <v-chip
                v-if="!loading && stats.totalSent > 0"
                color="error"
                variant="tonal"
                size="x-small"
                class="mt-2"
                prepend-icon="mdi-arrow-up"
              >
                Expense
              </v-chip>
            </div>
            <v-avatar color="lighterror" size="48" class="ml-3">
              <v-icon color="error" size="24">mdi-arrow-up-bold</v-icon>
            </v-avatar>
          </div>
        </v-card-text>
      </v-card>
    </v-col>

    <!-- Transactions Card -->
    <v-col cols="12" sm="6" lg="6">
      <v-card elevation="0" variant="outlined">
        <v-card-text>
          <div class="d-flex align-items-center justify-space-between">
            <div class="flex-grow-1">
              <h6 class="text-subtitle-2 text-medium-emphasis mb-1">Total Transactions</h6>
              <h4 class="text-h4 font-weight-bold mb-0">
                <v-skeleton-loader v-if="loading" type="text" width="60" />
                <span v-else>{{ stats.totalTransactions }}</span>
              </h4>
              <v-chip
                v-if="!loading && stats.totalTransactions > 0"
                color="warning"
                variant="tonal"
                size="x-small"
                class="mt-2"
                prepend-icon="mdi-swap-horizontal"
              >
                Completed
              </v-chip>
            </div>
            <v-avatar color="lightwarning" size="48" class="ml-3">
              <v-icon color="warning" size="24">mdi-receipt-text</v-icon>
            </v-avatar>
          </div>
        </v-card-text>
      </v-card>
    </v-col>

    <!-- Commission Card -->
    <v-col cols="12" sm="6" lg="6">
      <v-card elevation="0" variant="outlined">
        <v-card-text>
          <div class="d-flex align-items-center justify-space-between">
            <div class="flex-grow-1">
              <h6 class="text-subtitle-2 text-medium-emphasis mb-1">Total Commission</h6>
              <h4 class="text-h4 font-weight-bold mb-0">
                <v-skeleton-loader v-if="loading" type="text" width="100" />
                <span v-else>{{ formatCurrency(stats.totalCommission) }}</span>
              </h4>
              <v-chip
                v-if="!loading && stats.totalCommission > 0"
                color="info"
                variant="tonal"
                size="x-small"
                class="mt-2"
                prepend-icon="mdi-percent"
              >
                1.5% Fee
              </v-chip>
            </div>
            <v-avatar color="lightinfo" size="48" class="ml-3">
              <v-icon color="info" size="24">mdi-cash-multiple</v-icon>
            </v-avatar>
          </div>
        </v-card-text>
      </v-card>
    </v-col>
  </v-row>
</template>

<style scoped>
.v-skeleton-loader {
  background: transparent;
}
</style>

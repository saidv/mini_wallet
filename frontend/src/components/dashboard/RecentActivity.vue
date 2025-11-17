<script setup lang="ts">
import { ref, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import { transactionsApi, type Transaction } from '@/api/transactions';
import UiTitleCard from '@/components/shared/UiTitleCard.vue';
import TransactionTable from '@/components/transactions/TransactionTable.vue';

const router = useRouter();
const transactions = ref<Transaction[]>([]);
const loading = ref(false);
const error = ref<string | null>(null);

const loadTransactions = async () => {
  loading.value = true;
  error.value = null;
  try {
    const response = await transactionsApi.getTransactions(1, 10);
    transactions.value = response.data;
  } catch (err: unknown) {
    const errorMessage = err instanceof Error ? err.message : 'Failed to load transactions';
    error.value = errorMessage;
    console.error('Error loading transactions:', err);
  } finally {
    loading.value = false;
  }
};

const goToAllTransactions = () => {
  router.push('/transactions');
};

onMounted(() => {
  loadTransactions();
});
</script>

<template>
  <UiTitleCard title="Recent Activity" class-name="px-0 pb-0 rounded-md">
    <v-progress-linear v-if="loading" indeterminate color="primary" />

    <v-alert v-else-if="error" type="error" variant="tonal" class="ma-4">
      {{ error }}
    </v-alert>

    <div v-else-if="transactions.length === 0" class="text-center pa-8">
      <v-icon size="64" color="grey-lighten-1" class="mb-4"> mdi-inbox </v-icon>
      <h4 class="text-h6 text-medium-emphasis mb-2">No transactions yet</h4>
      <p class="text-body-2 text-medium-emphasis mb-4">Start by sending money to another user</p>
      <v-btn to="/transfer" color="primary" prepend-icon="mdi-send"> Send Money </v-btn>
    </div>

    <div v-else>
      <TransactionTable :transactions="transactions" :loading="loading" compact />

      <v-divider />

      <v-card-actions class="pa-4 justify-center">
        <v-btn
          variant="text"
          color="primary"
          prepend-icon="mdi-history"
          @click="goToAllTransactions"
        >
          View All Transactions
        </v-btn>
      </v-card-actions>
    </div>
  </UiTitleCard>
</template>

<style scoped></style>

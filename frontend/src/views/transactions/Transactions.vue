<script setup lang="ts" name="TransactionsView">
import { ref, onMounted, computed, watch } from 'vue';
import { useAuthStore } from '@/stores/auth';
import { transactionsApi, type Transaction } from '@/api/transactions';
import UiTitleCard from '@/components/shared/UiTitleCard.vue';
import TransactionTable from '@/components/transactions/TransactionTable.vue';

const authStore = useAuthStore();

// State
const transactions = ref<Transaction[]>([]);
const loading = ref(false);
const currentPage = ref(1);
const lastPage = ref(1);
const total = ref(0);
const perPage = ref(15);
const filterStatus = ref<string>('all');
const filterDirection = ref<string>('all');
const searchQuery = ref('');

// Computed
const filteredTransactions = computed(() => {
  let filtered = [...transactions.value];

  // Filter by status
  if (filterStatus.value !== 'all') {
    filtered = filtered.filter(t => t.status === filterStatus.value);
  }

  // Filter by direction
  if (filterDirection.value !== 'all') {
    const userId = authStore.user?.id;
    if (filterDirection.value === 'sent') {
      filtered = filtered.filter(t => t.sender_id === userId);
    } else if (filterDirection.value === 'received') {
      filtered = filtered.filter(t => t.receiver_id === userId);
    }
  }

  // Search filter
  if (searchQuery.value) {
    const query = searchQuery.value.toLowerCase();
    filtered = filtered.filter(
      t =>
        t.uuid.toLowerCase().includes(query) ||
        t.sender?.name.toLowerCase().includes(query) ||
        t.sender?.email.toLowerCase().includes(query) ||
        t.receiver?.name.toLowerCase().includes(query) ||
        t.receiver?.email.toLowerCase().includes(query)
    );
  }

  return filtered;
});

const hasActiveFilters = computed(() => {
  return (
    filterStatus.value !== 'all' || filterDirection.value !== 'all' || searchQuery.value.length > 0
  );
});

const displayLastPage = computed(() => {
  return hasActiveFilters.value ? 1 : lastPage.value;
});

const displayTotal = computed(() => {
  return hasActiveFilters.value ? filteredTransactions.value.length : total.value;
});

// Methods
const fetchTransactions = async () => {
  loading.value = true;
  try {
    const response = await transactionsApi.getTransactions(currentPage.value, perPage.value);
    transactions.value = response.data;
    lastPage.value = response.last_page;
    total.value = response.total;
  } catch (error: unknown) {
    console.error('Error loading transactions:', error);
  } finally {
    loading.value = false;
  }
};

const resetFilters = () => {
  filterStatus.value = 'all';
  filterDirection.value = 'all';
  searchQuery.value = '';
  currentPage.value = 1;
};

// Watchers - reset pagination when filters change
watch([filterStatus, filterDirection, searchQuery], () => {
  currentPage.value = 1;
});

// Lifecycle
onMounted(() => {
  fetchTransactions();
});
</script>

<template>
  <div>
    <v-row>
      <v-col cols="12">
        <UiTitleCard title="Transaction History">
          <!-- Filters -->
          <v-row class="mb-4">
            <v-col cols="12" md="4">
              <v-text-field
                v-model="searchQuery"
                density="compact"
                variant="outlined"
                placeholder="Search by UUID, name or email..."
                prepend-inner-icon="mdi-magnify"
                clearable
                hide-details
              />
            </v-col>
            <v-col cols="12" sm="6" md="3">
              <v-select
                v-model="filterDirection"
                density="compact"
                variant="outlined"
                label="Direction"
                :items="[
                  { title: 'All Transactions', value: 'all' },
                  { title: 'Sent', value: 'sent' },
                  { title: 'Received', value: 'received' },
                ]"
                hide-details
              />
            </v-col>
            <v-col cols="12" sm="6" md="3">
              <v-select
                v-model="filterStatus"
                density="compact"
                variant="outlined"
                label="Status"
                :items="[
                  { title: 'All Statuses', value: 'all' },
                  { title: 'Completed', value: 'completed' },
                  { title: 'Pending', value: 'pending' },
                  { title: 'Failed', value: 'failed' },
                ]"
                hide-details
              />
            </v-col>
            <v-col cols="12" md="2">
              <v-btn color="secondary" variant="outlined" block @click="resetFilters">
                Reset
              </v-btn>
            </v-col>
          </v-row>

          <!-- Loading State -->
          <v-card v-if="loading" flat class="text-center pa-8">
            <v-progress-circular indeterminate color="primary" size="64" />
            <p class="text-body-1 text-medium-emphasis mt-4">Loading transactions...</p>
          </v-card>

          <!-- Empty State -->
          <v-card v-else-if="filteredTransactions.length === 0" flat class="text-center pa-8">
            <v-icon size="64" color="secondary" class="mb-4"> mdi-inbox-outline </v-icon>
            <h3 class="text-h5 mb-2">No Transactions Found</h3>
            <p class="text-body-1 text-medium-emphasis mb-4">
              {{
                searchQuery || filterStatus !== 'all' || filterDirection !== 'all'
                  ? 'Try adjusting your filters'
                  : 'Start by sending your first transaction'
              }}
            </p>
            <v-btn
              v-if="searchQuery || filterStatus !== 'all' || filterDirection !== 'all'"
              color="primary"
              variant="outlined"
              @click="resetFilters"
            >
              Clear Filters
            </v-btn>
          </v-card>

          <!-- Transactions Table -->
          <div v-else>
            <TransactionTable
              :transactions="filteredTransactions"
              :loading="loading"
              show-actions
            />

            <!-- Pagination -->
            <div class="d-flex justify-space-between align-center mt-4">
              <div class="text-body-2 text-medium-emphasis">
                Showing {{ filteredTransactions.length }} of {{ displayTotal }} transactions
              </div>
              <v-pagination
                v-if="!hasActiveFilters"
                v-model="currentPage"
                :length="displayLastPage"
                :total-visible="5"
                density="comfortable"
                @update:model-value="fetchTransactions"
              />
            </div>
          </div>
        </UiTitleCard>
      </v-col>
    </v-row>
  </div>
</template>

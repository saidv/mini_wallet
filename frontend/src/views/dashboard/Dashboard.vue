<script setup lang="ts" name="DashboardView">
import { ref, onMounted } from 'vue';
import { useAuthStore } from '@/stores/auth';
import UiTitleCard from '@/components/shared/UiTitleCard.vue';
import RecentActivity from '@/components/dashboard/RecentActivity.vue';
import StatsCards from '@/components/dashboard/StatsCards.vue';

const authStore = useAuthStore();
const loading = ref(false);

const quickActions = [
  {
    title: 'Send Money',
    subtitle: 'Transfer to another user',
    icon: 'mdi-send',
    color: 'primary',
    to: '/transfer',
  },
  {
    title: 'Transaction History',
    subtitle: 'View all transactions',
    icon: 'mdi-history',
    color: 'secondary',
    to: '/transactions',
  },
  {
    title: 'Account Settings',
    subtitle: 'Manage your account',
    icon: 'mdi-cog',
    color: 'info',
    to: '/#',
  },
];

onMounted(async () => {
  loading.value = true;
  try {
    await authStore.fetchUser();
  } finally {
    loading.value = false;
  }
});
</script>

<template>
  <div>
    <!-- Welcome Section -->
    <v-row>
      <v-col cols="12">
        <v-card color="primary" variant="flat">
          <v-card-text class="pa-6">
            <h2 class="text-h4 font-weight-bold text-white mb-2">
              Welcome back, {{ authStore.user?.name }}! 👋
            </h2>
            <p class="text-subtitle-1 text-white opacity-90">
              Your wallet is ready for transactions
            </p>
          </v-card-text>
        </v-card>
      </v-col>
    </v-row>

    <!-- Balance Card -->
    <v-row class="mt-4">
      <v-col cols="12" md="4">
        <UiTitleCard title="Current Balance">
          <v-card flat color="gray100">
            <v-card-text class="text-center pa-8">
              <v-icon size="48" color="primary" class="mb-4"> mdi-wallet </v-icon>
              <h1 class="text-h2 font-weight-bold text-primary mb-2">
                ${{ authStore.currentBalanceInDollars.toFixed(2) }}
              </h1>
              <p class="text-subtitle-2 text-medium-emphasis">
                {{ authStore.currentBalance }} cents
              </p>

              <v-chip color="success" variant="flat" prepend-icon="mdi-check-circle" class="mt-4">
                Account Active
              </v-chip>
            </v-card-text>
          </v-card>
        </UiTitleCard>
      </v-col>

      <!-- Stats Cards -->
      <v-col cols="12" md="8">
        <StatsCards />
      </v-col>
    </v-row>

    <!-- Quick Actions -->
    <v-row class="mt-4">
      <v-col cols="12">
        <UiTitleCard title="Quick Actions">
          <v-row>
            <v-col v-for="action in quickActions" :key="action.title" cols="12" sm="6" md="4">
              <v-card :to="action.to" hover class="text-center pa-4">
                <v-avatar :color="action.color" size="64" class="mb-4">
                  <v-icon size="32" color="white">
                    {{ action.icon }}
                  </v-icon>
                </v-avatar>
                <h4 class="text-h6 font-weight-medium mb-2">
                  {{ action.title }}
                </h4>
                <p class="text-caption text-medium-emphasis">
                  {{ action.subtitle }}
                </p>
              </v-card>
            </v-col>
          </v-row>
        </UiTitleCard>
      </v-col>
    </v-row>

    <!-- Recent Activity -->
    <v-row class="mt-4">
      <v-col cols="12">
        <RecentActivity />
      </v-col>
    </v-row>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted, onUnmounted } from 'vue';
import { RouterView, useRouter } from 'vue-router';
import { useAuthStore } from '@/stores/auth';
import { usePusher } from '@/composables/usePusher';
import { useToast } from '@/composables/useToast';
import Logo from '@/components/shared/Logo.vue';

interface MoneyReceivedEvent {
  transaction_uuid: string;
  amount: number;
  direction: 'in' | 'out';
  new_balance: number;
  message?: string;
  sender?: {
    name: string;
  };
}

const router = useRouter();
const authStore = useAuthStore();
const { subscribeToUserChannel, disconnect } = usePusher();
const { success } = useToast();

const drawer = ref(true);
const rail = ref(false);

const menuItems = [
  {
    title: 'Dashboard',
    icon: 'mdi-view-dashboard',
    to: '/dashboard',
    exact: true,
  },
  {
    title: 'Send Money',
    icon: 'mdi-send',
    to: '/transfer',
  },
  {
    title: 'Transactions',
    icon: 'mdi-history',
    to: '/transactions',
  },
];

async function handleLogout() {
  await authStore.logout();
  router.push('/');
}

// Subscribe to Pusher on mount
onMounted(() => {
  subscribeToUserChannel((event: MoneyReceivedEvent) => {
    console.log('💰 Money received event:', event);

    // Update balance in store
    if (event.new_balance !== undefined) {
      authStore.user!.balance = event.new_balance;
    }

    // Show success notification (only receivers get notifications)
    success(
      'Money Received! 💰',
      event.message || `You received $${(event.amount / 100).toFixed(2)} from ${event.sender?.name}`
    );

    // Trigger transaction list refresh
    window.dispatchEvent(new CustomEvent('transaction-updated'));
  });
});

onUnmounted(() => {
  disconnect();
});
</script>

<template>
  <v-app>
    <!-- Navigation Drawer -->
    <v-navigation-drawer v-model="drawer" :rail="rail" permanent color="surface" class="border-e">
      <template #prepend>
        <div class="pa-4">
          <Logo :size="rail ? 'small' : 'default'" />
        </div>
      </template>

      <v-list density="compact" nav>
        <v-list-item
          v-for="item in menuItems"
          :key="item.title"
          :to="item.to"
          :exact="item.exact"
          :prepend-icon="item.icon"
          :title="item.title"
          rounded="md"
          class="mx-2 mb-1"
        />
      </v-list>

      <template #append>
        <div class="pa-2">
          <v-btn
            block
            :prepend-icon="rail ? undefined : 'mdi-logout'"
            :icon="rail ? 'mdi-logout' : undefined"
            color="error"
            variant="tonal"
            @click="handleLogout"
          >
            <span v-if="!rail">Logout</span>
          </v-btn>
        </div>
      </template>
    </v-navigation-drawer>

    <!-- App Bar -->
    <v-app-bar elevation="0" class="border-b">
      <v-app-bar-nav-icon @click="rail = !rail" />

      <v-app-bar-title>
        <span class="text-subtitle-1 font-weight-medium">
          {{ $route.meta.title || 'Dashboard' }}
        </span>
      </v-app-bar-title>

      <v-spacer />

      <!-- Balance Display -->
      <v-chip
        v-if="authStore.user"
        color="primary"
        variant="tonal"
        prepend-icon="mdi-wallet"
        class="mr-4"
      >
        <span class="font-weight-bold"> ${{ authStore.currentBalanceInDollars.toFixed(2) }} </span>
      </v-chip>

      <!-- User Menu -->
      <v-menu>
        <template #activator="{ props }">
          <v-btn icon v-bind="props">
            <v-avatar color="primary" size="36">
              <span class="text-white text-body-2">
                {{ authStore.user?.name?.charAt(0).toUpperCase() }}
              </span>
            </v-avatar>
          </v-btn>
        </template>

        <v-list>
          <v-list-item>
            <v-list-item-title class="font-weight-medium">
              {{ authStore.user?.name }}
            </v-list-item-title>
            <v-list-item-subtitle>
              {{ authStore.user?.email }}
            </v-list-item-subtitle>
          </v-list-item>

          <v-divider />

          <v-list-item @click="handleLogout">
            <template #prepend>
              <v-icon>mdi-logout</v-icon>
            </template>
            <v-list-item-title>Logout</v-list-item-title>
          </v-list-item>
        </v-list>
      </v-menu>
    </v-app-bar>

    <!-- Main Content -->
    <v-main>
      <v-container fluid>
        <RouterView />
      </v-container>
    </v-main>
  </v-app>
</template>

<style scoped lang="scss">
.v-navigation-drawer {
  border-right: 1px solid rgb(var(--v-theme-borderColor));
}

.v-app-bar {
  border-bottom: 1px solid rgb(var(--v-theme-borderColor));
}

.v-list-item {
  &.v-list-item--active {
    background: rgb(var(--v-theme-lightprimary));

    .v-icon,
    .v-list-item-title {
      color: rgb(var(--v-theme-primary));
    }
  }
}
</style>

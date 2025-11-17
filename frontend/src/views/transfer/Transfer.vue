<script setup lang="ts" name="TransferView">
import { ref, computed, watch } from 'vue';
import { useRouter } from 'vue-router';
import { useAuthStore } from '@/stores/auth';
import { transactionsApi, type TransferResponse } from '@/api/transactions';
import UiTitleCard from '@/components/shared/UiTitleCard.vue';

interface AxiosError {
  response?: {
    data?: {
      message?: string;
    };
  };
}

interface User {
  id: number;
  name: string;
  email: string;
}

const router = useRouter();
const authStore = useAuthStore();

// Form state
const receiverEmail = ref('');
const amountInDollars = ref('');
const loading = ref(false);
const validatingEmail = ref(false);
const transferSuccess = ref(false);
const transferResult = ref<TransferResponse['data'] | null>(null);

// Receiver validation
const receiverUser = ref<User | null>(null);
const receiverError = ref('');
const emailTouched = ref(false);

// Debounce timer
let validationTimer: ReturnType<typeof setTimeout> | null = null;

// Validation
const form = ref<{ validate: () => Promise<{ valid: boolean }>; reset: () => void } | null>(null);
const emailRules = [
  (v: string) => !!v || 'Email is required',
  (v: string) => /.+@.+\..+/.test(v) || 'Email must be valid',
  (v: string) => v !== authStore.user?.email || 'Cannot send money to yourself',
];

const amountRules = [
  (v: string) => !!v || 'Amount is required',
  (v: string) => !isNaN(Number(v)) || 'Amount must be a number',
  (v: string) => Number(v) > 0 || 'Amount must be greater than 0',
  (v: string) =>
    Number(v) <= authStore.currentBalanceInDollars - calculateCommission(Number(v)) ||
    'Insufficient balance including commission',
];

// Computed
const amountInCents = computed(() => {
  return Math.round(Number(amountInDollars.value) * 100);
});

const commission = computed(() => {
  return calculateCommission(Number(amountInDollars.value));
});

const totalDebit = computed(() => {
  return Number(amountInDollars.value) + commission.value;
});

const remainingBalance = computed(() => {
  return authStore.currentBalanceInDollars - totalDebit.value;
});

const canSubmit = computed(() => {
  return (
    receiverUser.value &&
    amountInDollars.value &&
    Number(amountInDollars.value) > 0 &&
    remainingBalance.value >= 0 &&
    !loading.value &&
    !receiverError.value
  );
});

// Methods
function calculateCommission(amount: number): number {
  return Math.round(amount * 0.015 * 100) / 100;
}

const validateReceiver = async () => {
  if (!receiverEmail.value) {
    receiverUser.value = null;
    receiverError.value = '';
    return;
  }

  // Basic validation
  if (receiverEmail.value === authStore.user?.email) {
    receiverError.value = 'Cannot send money to yourself';
    receiverUser.value = null;
    return;
  }

  if (!/.+@.+\..+/.test(receiverEmail.value)) {
    receiverError.value = 'Invalid email format';
    receiverUser.value = null;
    return;
  }

  validatingEmail.value = true;
  receiverError.value = '';
  receiverUser.value = null;

  try {
    const response = await transactionsApi.validateReceiver(receiverEmail.value);

    console.log('Validation response:', response.data);

    // API response is already unwrapped: { valid: true, user: {...} }
    const data = response.data;

    if (data.valid && data.user) {
      receiverUser.value = data.user;
      receiverError.value = '';
    } else {
      receiverError.value = data.message || 'User not found';
      receiverUser.value = null;
    }
  } catch (error: unknown) {
    console.error('Validation error:', error);
    // Check if it's a validation error (400/404) with message
    if (error instanceof Error && 'response' in error) {
      const axiosError = error as AxiosError;
      if (axiosError.response?.data) {
        receiverError.value = axiosError.response.data.message || 'User not found';
      } else {
        receiverError.value = 'Unable to validate email. Please try again.';
      }
    } else {
      receiverError.value = 'Unable to validate email. Please try again.';
    }
    receiverUser.value = null;
  } finally {
    validatingEmail.value = false;
  }
};

// Debounced validation (waits 500ms after user stops typing)
const debouncedValidateReceiver = () => {
  // Clear previous timer
  if (validationTimer) {
    clearTimeout(validationTimer);
  }

  // Reset state while typing
  receiverUser.value = null;
  receiverError.value = '';

  // Set new timer - validate after 500ms of inactivity
  validationTimer = setTimeout(() => {
    validateReceiver();
  }, 500); // 500ms delay
};

const handleEmailBlur = () => {
  emailTouched.value = true;
  // Clear any pending validation
  if (validationTimer) {
    clearTimeout(validationTimer);
  }
  // Validate immediately on blur
  validateReceiver();
};

const handleSubmit = async () => {
  const { valid } = await form.value.validate();
  if (!valid || !receiverUser.value) return;

  loading.value = true;
  try {
    const result = await transactionsApi.transfer({
      receiver_email: receiverEmail.value,
      amount: amountInCents.value,
    });

    transferResult.value = result.data;
    transferSuccess.value = true;

    // Refresh user balance
    await authStore.fetchUser();
  } catch (error: unknown) {
    console.error('Transfer error:', error);
    const errorMessage =
      error instanceof Error && 'response' in error
        ? (error as AxiosError).response?.data?.message || 'Transfer failed'
        : 'Transfer failed';
    alert(errorMessage);
  } finally {
    loading.value = false;
  }
};

const resetForm = () => {
  receiverEmail.value = '';
  amountInDollars.value = '';
  receiverUser.value = null;
  receiverError.value = '';
  emailTouched.value = false;
  transferSuccess.value = false;
  transferResult.value = null;
  form.value?.reset();

  // Clear any pending validation timers
  if (validationTimer) {
    clearTimeout(validationTimer);
  }
};

const goToTransactions = () => {
  router.push('/transactions');
};

// Watch email changes for debounced validation
watch(receiverEmail, newValue => {
  if (newValue && newValue.length > 3) {
    // Only start validating after 3+ characters
    debouncedValidateReceiver();
  } else {
    // Clear validation if too short
    receiverUser.value = null;
    receiverError.value = '';
    if (validationTimer) {
      clearTimeout(validationTimer);
    }
  }
});
</script>

<template>
  <div>
    <v-row>
      <!-- Transfer Form -->
      <v-col cols="12" lg="8">
        <UiTitleCard title="Send Money">
          <!-- Success State -->
          <v-card v-if="transferSuccess" flat color="lightsuccess" class="pa-6 mb-6">
            <div class="text-center">
              <v-icon size="64" color="success" class="mb-4"> mdi-check-circle </v-icon>
              <h3 class="text-h5 font-weight-bold mb-2">Transfer Successful!</h3>
              <p class="text-body-1 text-medium-emphasis mb-4">
                Your money has been sent successfully
              </p>

              <v-divider class="my-4" />

              <div class="text-left">
                <v-row>
                  <v-col cols="6">
                    <p class="text-caption text-medium-emphasis mb-1">Amount Sent</p>
                    <p class="text-h6 font-weight-bold text-success">
                      ${{ ((transferResult?.amount ?? 0) / 100).toFixed(2) }}
                    </p>
                  </v-col>
                  <v-col cols="6">
                    <p class="text-caption text-medium-emphasis mb-1">Commission Fee</p>
                    <p class="text-h6 font-weight-bold">
                      ${{ ((transferResult?.commission ?? 0) / 100).toFixed(2) }}
                    </p>
                  </v-col>
                  <v-col cols="6">
                    <p class="text-caption text-medium-emphasis mb-1">Total Debited</p>
                    <p class="text-h6 font-weight-bold text-error">
                      ${{ ((transferResult?.total_debited ?? 0) / 100).toFixed(2) }}
                    </p>
                  </v-col>
                  <v-col cols="6">
                    <p class="text-caption text-medium-emphasis mb-1">New Balance</p>
                    <p class="text-h6 font-weight-bold text-primary">
                      ${{ ((transferResult?.sender_balance ?? 0) / 100).toFixed(2) }}
                    </p>
                  </v-col>
                </v-row>
              </div>

              <v-divider class="my-4" />

              <div class="d-flex gap-2 space-around">
                <v-btn color="primary" variant="flat" prepend-icon="mdi-send" @click="resetForm">
                  Send Another
                </v-btn>
                <v-btn
                  color="secondary"
                  variant="outlined"
                  prepend-icon="mdi-history"
                  @click="goToTransactions"
                >
                  View Transactions
                </v-btn>
              </div>
            </div>
          </v-card>

          <!-- Transfer Form -->
          <v-form v-else ref="form" @submit.prevent="handleSubmit">
            <!-- Receiver Selection -->
            <v-card flat color="lightprimary" class="pa-4 mb-4">
              <h4 class="text-h6 font-weight-medium mb-4">Recipient Details</h4>

              <v-text-field
                v-model="receiverEmail"
                :rules="emailRules"
                :loading="validatingEmail"
                :error-messages="receiverError"
                type="email"
                label="Recipient Email"
                placeholder="Enter recipient email address"
                variant="outlined"
                prepend-inner-icon="mdi-email"
                clearable
                @blur="handleEmailBlur"
              >
                <template v-if="receiverUser" #append-inner>
                  <v-icon color="success">mdi-check-circle</v-icon>
                </template>
              </v-text-field>

              <!-- Receiver Info Card (shown after validation) -->
              <v-expand-transition>
                <v-card v-if="receiverUser" flat color="white" class="pa-4 mt-2">
                  <div class="d-flex align-center">
                    <v-avatar color="primary" size="48" class="mr-4">
                      <span class="text-h6 text-white">{{
                        receiverUser.name.charAt(0).toUpperCase()
                      }}</span>
                    </v-avatar>
                    <div class="flex-grow-1">
                      <h5 class="text-body-1 font-weight-medium">{{ receiverUser.name }}</h5>
                      <p class="text-body-2 text-medium-emphasis mb-0">{{ receiverUser.email }}</p>
                    </div>
                    <v-chip color="success" size="small" variant="flat">
                      <v-icon size="16" class="mr-1">mdi-check-circle</v-icon>
                      Verified
                    </v-chip>
                  </div>
                </v-card>
              </v-expand-transition>
            </v-card>

            <!-- Amount Input -->
            <v-card flat color="lightprimary" class="pa-4 mb-4">
              <h4 class="text-h6 font-weight-medium mb-4">Transfer Amount</h4>

              <v-text-field
                v-model="amountInDollars"
                :rules="amountRules"
                :disabled="!receiverUser"
                type="number"
                step="0.01"
                label="Amount"
                placeholder="0.00"
                variant="outlined"
                prepend-inner-icon="mdi-currency-usd"
                suffix="USD"
              />
            </v-card>

            <!-- Transfer Summary -->
            <v-expand-transition>
              <v-card v-if="amountInDollars && Number(amountInDollars) > 0" flat class="pa-4 mb-4">
                <h4 class="text-h6 font-weight-medium mb-4">Transfer Summary</h4>

                <v-list lines="two" class="bg-transparent">
                  <v-list-item>
                    <v-list-item-title>Amount to Send</v-list-item-title>
                    <template #append>
                      <span class="text-h6 font-weight-bold"
                        >${{ Number(amountInDollars).toFixed(2) }}</span
                      >
                    </template>
                  </v-list-item>

                  <v-list-item>
                    <v-list-item-title>Commission Fee (1.5%)</v-list-item-title>
                    <template #append>
                      <span class="text-h6 font-weight-bold">${{ commission.toFixed(2) }}</span>
                    </template>
                  </v-list-item>

                  <v-divider class="my-2" />

                  <v-list-item>
                    <v-list-item-title class="font-weight-bold">Total to Debit</v-list-item-title>
                    <template #append>
                      <span class="text-h5 font-weight-bold text-error"
                        >${{ totalDebit.toFixed(2) }}</span
                      >
                    </template>
                  </v-list-item>

                  <v-list-item>
                    <v-list-item-title class="font-weight-bold"
                      >Remaining Balance</v-list-item-title
                    >
                    <template #append>
                      <span
                        class="text-h6 font-weight-bold"
                        :class="remainingBalance >= 0 ? 'text-success' : 'text-error'"
                      >
                        ${{ remainingBalance.toFixed(2) }}
                      </span>
                    </template>
                  </v-list-item>
                </v-list>
              </v-card>
            </v-expand-transition>

            <!-- Actions -->
            <div class="d-flex gap-2">
              <v-btn
                type="submit"
                color="primary"
                size="large"
                :loading="loading"
                :disabled="!canSubmit"
                block
              >
                <v-icon class="mr-2">mdi-send</v-icon>
                Send Money
              </v-btn>
            </div>
          </v-form>
        </UiTitleCard>
      </v-col>

      <!-- Balance & Info Sidebar -->
      <v-col cols="12" lg="4">
        <!-- Current Balance -->
        <UiTitleCard title="Current Balance">
          <v-card flat color="lightprimary">
            <v-card-text class="text-center pa-6">
              <v-icon size="48" color="primary" class="mb-3"> mdi-wallet </v-icon>
              <h2 class="text-h3 font-weight-bold text-primary mb-2">
                ${{ authStore.currentBalanceInDollars.toFixed(2) }}
              </h2>
              <p class="text-caption text-medium-emphasis">{{ authStore.currentBalance }} cents</p>
            </v-card-text>
          </v-card>
        </UiTitleCard>

        <!-- Transfer Info -->
        <UiTitleCard title="Transfer Information" class="mt-4">
          <v-list lines="two" class="bg-transparent">
            <v-list-item>
              <template #prepend>
                <v-icon color="primary">mdi-information</v-icon>
              </template>
              <v-list-item-title>Commission Rate</v-list-item-title>
              <v-list-item-subtitle>1.5% per transaction</v-list-item-subtitle>
            </v-list-item>

            <v-list-item>
              <template #prepend>
                <v-icon color="success">mdi-clock-fast</v-icon>
              </template>
              <v-list-item-title>Processing Time</v-list-item-title>
              <v-list-item-subtitle>Instant transfer</v-list-item-subtitle>
            </v-list-item>

            <v-list-item>
              <template #prepend>
                <v-icon color="info">mdi-shield-check</v-icon>
              </template>
              <v-list-item-title>Security</v-list-item-title>
              <v-list-item-subtitle>All transactions are encrypted</v-list-item-subtitle>
            </v-list-item>
          </v-list>
        </UiTitleCard>
      </v-col>
    </v-row>
  </div>
</template>

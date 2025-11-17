<script setup lang="ts" name="AuthRegister">
import { reactive, ref } from 'vue';
import { useRouter } from 'vue-router';
import { useAuthStore } from '@/stores/auth';
import Logo from '@/components/shared/Logo.vue';

interface AxiosError {
  response?: {
    data?: {
      errors?: Record<string, string[]>;
    };
  };
}

const router = useRouter();
const authStore = useAuthStore();

const form = reactive({
  name: '',
  email: '',
  password: '',
  password_confirmation: '',
});

const showPassword = ref(false);
const showPasswordConfirm = ref(false);
const valid = ref(false);
const validationErrors = ref<string[]>([]);

const nameRules = [
  (v: string) => !!v || 'Name is required',
  (v: string) => v.length >= 2 || 'Name must be at least 2 characters',
];

const emailRules = [
  (v: string) => !!v || 'Email is required',
  (v: string) => /.+@.+\..+/.test(v) || 'Email must be valid',
];

const passwordRules = [
  (v: string) => !!v || 'Password is required',
  (v: string) => v.length >= 8 || 'Password must be at least 8 characters',
];

const confirmPasswordRules = [
  (v: string) => !!v || 'Please confirm your password',
  (v: string) => v === form.password || 'Passwords do not match',
];

async function handleSubmit() {
  if (!valid.value) return;

  validationErrors.value = [];

  try {
    await authStore.register({
      name: form.name,
      email: form.email,
      password: form.password,
      password_confirmation: form.password_confirmation,
    });
    router.push('/dashboard');
  } catch (error: unknown) {
    if (error instanceof Error && 'response' in error) {
      const axiosError = error as AxiosError;
      if (axiosError.response?.data?.errors) {
        const errors = axiosError.response.data.errors;
        validationErrors.value = Object.values(errors).flat() as string[];
      }
    }
    console.error('Registration error:', error);
  }
}
</script>

<template>
  <div class="auth-wrapper d-flex align-center justify-center">
    <v-container>
      <v-row justify="center">
        <v-col cols="12" sm="10" md="8" lg="6" xl="4">
          <!-- Back Button -->
          <v-btn to="/" variant="text" prepend-icon="mdi-arrow-left" class="mb-4">
            Back to Home
          </v-btn>

          <v-card elevation="10" class="pa-4 pa-sm-8">
            <!-- Logo -->
            <div class="d-flex justify-center mb-6">
              <Logo size="large" />
            </div>

            <!-- Title -->
            <div class="text-center mb-6">
              <h2 class="text-h4 font-weight-bold mb-2">Create Account</h2>
              <p class="text-subtitle-1 text-medium-emphasis">Start your wallet journey today</p>
            </div>

            <!-- Error Alert -->
            <v-alert
              v-if="authStore.error"
              type="error"
              variant="tonal"
              closable
              class="mb-4"
              @click:close="authStore.error = null"
            >
              {{ authStore.error }}
            </v-alert>

            <!-- Validation Errors -->
            <v-alert v-if="validationErrors.length > 0" type="error" variant="tonal" class="mb-4">
              <ul class="pl-4">
                <li v-for="error in validationErrors" :key="error">
                  {{ error }}
                </li>
              </ul>
            </v-alert>

            <!-- Register Form -->
            <v-form v-model="valid" @submit.prevent="handleSubmit">
              <v-text-field
                v-model="form.name"
                :rules="nameRules"
                label="Full Name"
                prepend-inner-icon="mdi-account-outline"
                required
                class="mb-3"
              />

              <v-text-field
                v-model="form.email"
                :rules="emailRules"
                label="Email Address"
                type="email"
                prepend-inner-icon="mdi-email-outline"
                required
                class="mb-3"
              />

              <v-text-field
                v-model="form.password"
                :rules="passwordRules"
                :type="showPassword ? 'text' : 'password'"
                label="Password"
                prepend-inner-icon="mdi-lock-outline"
                :append-inner-icon="showPassword ? 'mdi-eye' : 'mdi-eye-off'"
                required
                class="mb-3"
                @click:append-inner="showPassword = !showPassword"
              />

              <v-text-field
                v-model="form.password_confirmation"
                :rules="confirmPasswordRules"
                :type="showPasswordConfirm ? 'text' : 'password'"
                label="Confirm Password"
                prepend-inner-icon="mdi-lock-check-outline"
                :append-inner-icon="showPasswordConfirm ? 'mdi-eye' : 'mdi-eye-off'"
                required
                class="mb-4"
                @click:append-inner="showPasswordConfirm = !showPasswordConfirm"
              />

              <v-btn
                type="submit"
                color="primary"
                size="large"
                block
                :loading="authStore.isLoading"
                :disabled="!valid"
                class="mb-4"
              >
                Create Account
              </v-btn>

              <v-divider class="my-6" />

              <div class="text-center">
                <span class="text-medium-emphasis">Already have an account?</span>
                <router-link
                  to="/"
                  class="text-primary text-decoration-none ml-1 font-weight-medium"
                >
                  Sign In
                </router-link>
              </div>
            </v-form>
          </v-card>
        </v-col>
      </v-row>
    </v-container>
  </div>
</template>

<style scoped lang="scss">
.auth-wrapper {
  min-height: 100vh;
  background: linear-gradient(
    135deg,
    rgb(var(--v-theme-lightprimary)) 0%,
    rgb(var(--v-theme-lightsecondary)) 100%
  );
}
</style>

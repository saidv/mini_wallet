<script setup lang="ts" name="AuthLogin">
import { reactive, ref } from 'vue';
import { useRouter } from 'vue-router';
import { useAuthStore } from '@/stores/auth';
import Logo from '@/components/shared/Logo.vue';

const router = useRouter();
const authStore = useAuthStore();

const form = reactive({
  email: '',
  password: '',
  rememberMe: false,
});

const showPassword = ref(false);
const valid = ref(false);

const emailRules = [
  (v: string) => !!v || 'Email is required',
  (v: string) => /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v) || 'Email must be valid',
];

const passwordRules = [
  (v: string) => !!v || 'Password is required',
  (v: string) => v.length >= 8 || 'Password must be at least 8 characters',
];

async function handleSubmit() {
  if (!valid.value) return;

  try {
    await authStore.login({
      email: form.email,
      password: form.password,
    });
    router.push('/dashboard');
  } catch (error) {
    console.error('Login error:', error);
  }
}
</script>

<template>
  <div class="auth-wrapper d-flex align-center justify-center">
    <v-container>
      <v-row justify="center">
        <v-col cols="12" sm="10" md="8" lg="6" xl="4">
          <v-card elevation="10" class="pa-4 pa-sm-8">
            <!-- Logo -->
            <div class="d-flex justify-center mb-6">
              <Logo size="large" />
            </div>

            <!-- Title -->
            <div class="text-center mb-6">
              <h2 class="text-h4 font-weight-bold mb-2">Welcome Back</h2>
              <p class="text-subtitle-1 text-medium-emphasis">Sign in to access your wallet</p>
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

            <!-- Login Form -->
            <v-form v-model="valid" @submit.prevent="handleSubmit">
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

              <div class="d-flex align-center justify-space-between mb-4">
                <v-checkbox
                  v-model="form.rememberMe"
                  label="Remember me"
                  hide-details
                  density="compact"
                />
                <router-link to="/forgot-password" class="text-primary text-decoration-none">
                  Forgot Password?
                </router-link>
              </div>

              <v-btn
                type="submit"
                color="primary"
                size="large"
                block
                :loading="authStore.isLoading"
                :disabled="!valid"
                class="mb-4"
              >
                Sign In
              </v-btn>

              <v-divider class="my-6" />

              <div class="text-center">
                <span class="text-medium-emphasis">Don't have an account?</span>
                <router-link
                  to="/register"
                  class="text-primary text-decoration-none ml-1 font-weight-medium"
                >
                  Create Account
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

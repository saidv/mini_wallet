<script setup lang="ts">
import { ref } from 'vue'
import Logo from '@/components/shared/Logo.vue'

const email = ref('')
const isLoading = ref(false)
const emailSent = ref(false)

async function handleSubmit() {
  isLoading.value = true
  
  // TODO: Implement password reset API call
  // For now, simulate API call
  await new Promise(resolve => setTimeout(resolve, 1500))
  
  emailSent.value = true
  isLoading.value = false
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
              <router-link to="/">
                <Logo size="large" />
              </router-link>
            </div>

            <!-- Title -->
            <div class="text-center mb-6">
              <h2 class="text-h4 font-weight-bold mb-2">Reset Password</h2>
              <p class="text-subtitle-1 text-medium-emphasis">
                Enter your email address and we'll send you a link
              </p>
            </div>

            <!-- Success Message -->
            <v-alert
              v-if="emailSent"
              type="success"
              variant="tonal"
              prominent
              class="mb-4"
            >
              <div class="text-subtitle-1 font-weight-medium">
                Password reset link sent!
              </div>
              <div class="text-body-2">
                Check your email for instructions
              </div>
            </v-alert>

            <!-- Form -->
            <v-form v-else @submit.prevent="handleSubmit">
              <v-text-field
                v-model="email"
                label="Email Address"
                type="email"
                prepend-inner-icon="mdi-email-outline"
                required
                class="mb-4"
              />

              <v-btn
                type="submit"
                color="primary"
                size="large"
                block
                :loading="isLoading"
                class="mb-4"
              >
                Send Reset Link
              </v-btn>

              <div class="text-center">
                <router-link
                  to="/"
                  class="text-primary text-decoration-none"
                >
                  <v-icon size="small" class="mr-1">mdi-arrow-left</v-icon>
                  Back to login
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
  background: linear-gradient(135deg, rgb(var(--v-theme-lightprimary)) 0%, rgb(var(--v-theme-lightsecondary)) 100%);
}
</style>

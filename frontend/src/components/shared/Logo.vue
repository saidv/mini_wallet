<script setup lang="ts" name="AppLogo">
defineProps({
  size: {
    type: String,
    default: 'default',
  },
});
</script>

<template>
  <div class="logo-wrapper">
    <div :class="['logo', `logo--${size}`]">
      <span class="logo-icon">🏦</span>
      <span class="logo-text">Mini Wallet</span>
    </div>
  </div>
</template>

<style scoped lang="scss">
.logo {
  display: flex;
  align-items: center;
  gap: 12px;

  &-icon {
    font-size: 32px;
    line-height: 1;
  }

  &-text {
    font-size: 24px;
    font-weight: 700;
    background: linear-gradient(
      135deg,
      rgb(var(--v-theme-primary)) 0%,
      rgb(var(--v-theme-secondary)) 100%
    );
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
  }

  &--small {
    .logo-icon {
      font-size: 24px;
    }

    .logo-text {
      font-size: 18px;
    }
  }

  &--large {
    .logo-icon {
      font-size: 48px;
    }

    .logo-text {
      font-size: 32px;
    }
  }
}
</style>

<!-- src/components/common/AppMain.vue -->
<template>
  <div class="app-main">
    <router-view v-slot="{ Component }">
      <keep-alive :include="cachedViews">
        <component :is="Component" />
      </keep-alive>
    </router-view>
  </div>
</template>

<script setup>
import { computed } from 'vue'
import { useAppStore } from '@/store/modules/app'

const appStore = useAppStore()
const cachedViews = computed(() => appStore.cachedTabs || [])
</script>

<style scoped>
.app-main {
  min-height: calc(100vh - 96px);
  background-color: #f7f8fa;
  padding: 16px;
}
</style>
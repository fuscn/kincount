<template>
  <router-view v-slot="{ Component }">
    <keep-alive :include="cachedViews">
      <component :is="Component" />
    </keep-alive>
  </router-view>
</template>

<script setup>
import { computed } from 'vue'
import { useAppStore } from '@/store/modules/app'

const appStore = useAppStore()
// 后期结合多页签动态生成
const cachedViews = computed(() => (appStore.cachedTabs || []))
</script>

<style lang="scss">
html,
body,
#app {
  height: 100%;
  margin: 0;
  font-family: 'Helvetica Neue', Helvetica, 'PingFang SC', 'Hiragino Sans GB',
    'Microsoft YaHei', '微软雅黑', Arial, sans-serif;
  -webkit-font-smoothing: antialiased;
  -moz-osx-font-smoothing: grayscale;
}
</style>
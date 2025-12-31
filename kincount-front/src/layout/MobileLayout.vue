<!-- src/layout/MobileLayout.vue -->
<template>
  <div class="mobile-layout">
    <!-- 添加条件判断，某些页面不显示导航栏 移除placeholder -->
    <van-nav-bar v-if="showLayoutNavBar" :title="currentTitle" fixed  safe-area-inset-top>
      <template #left>
        <van-icon v-if="showBack" name="arrow-left" @click="handleBack" />
      </template>
      <template #right>
        <van-icon name="user-o" @click="showUserPanel = true" />
      </template>
    </van-nav-bar>

    <main class="main-content" :style="contentStyle">
      <router-view v-slot="{ Component }">
        <keep-alive :include="cachedViews">
          <component :is="Component" />
        </keep-alive>
      </router-view>
    </main>

    <!-- 添加条件判断，某些页面不显示底部导航 -->
    <!-- <van-tabbar v-if="showLayoutTabbar" v-model="activeTab" fixed placeholder safe-area-inset-bottom>
      <van-tabbar-item v-for="tab in tabs" :key="tab.name" :to="tab.path" :name="tab.name" :icon="tab.icon">
        {{ tab.title }}
      </van-tabbar-item>
    </van-tabbar> -->

    <van-action-sheet v-model:show="showUserPanel" :actions="userActions" @select="onUserAction" />

    <!-- 全局悬浮菜单 -->
    <FloatingMenu />
  </div>
</template>

<script setup>
import { ref, computed, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAppStore } from '@/store/modules/app'
import { useAuthStore } from '@/store/modules/auth'

const route = useRoute()
const router = useRouter()
const appStore = useAppStore()
const authStore = useAuthStore()

const activeTab = ref('dashboard')
const showUserPanel = ref(false)

const tabs = [
  { name: 'dashboard', title: '首页', icon: 'home-o', path: '/dashboard' },
  { name: 'product', title: '商品', icon: 'apps-o', path: '/product' },
  { name: 'stock', title: '库存', icon: 'bag-o', path: '/stock' },
  { name: 'sale', title: '销售', icon: 'balance-o', path: '/sale/order' },
  { name: 'financial', title: '财务', icon: 'balance-list-o', path: '/financial/record' }
]

const userActions = [
  { name: '修改密码', key: 'password' },
  { name: '退出登录', key: 'logout', color: '#ee0a24' }
]

const currentTitle = computed(() => route.meta?.title || '简库存')
const cachedViews = computed(() => appStore.cachedTabs || [])

// 添加条件判断
const showLayoutNavBar = computed(() => {
  return route.meta?.showLayoutNavBar !== false
})

const showLayoutTabbar = computed(() => {
  return route.meta?.showTabbar !== false
})

const showBack = computed(() => {
  return !tabs.some(tab => route.path === tab.path) && route.path !== '/dashboard'
})

const contentStyle = computed(() => ({
  paddingBottom: showLayoutTabbar.value ? '50px' : '0'
}))

watch(
  () => route.path,
  (newPath) => {
    const currentTab = tabs.find(tab => newPath.startsWith(tab.path))
    if (currentTab) {
      activeTab.value = currentTab.name
    }
  },
  { immediate: true }
)

function handleBack() {
  if (window.history.state.back) {
    router.back()
  } else {
    router.push('/dashboard')
  }
}

function onUserAction(action) {
  showUserPanel.value = false
  if (action.key === 'logout') {
    authStore.logout()
  } else if (action.key === 'password') {
    router.push('/system/user/password')
  }
}
</script>

<style scoped lang="scss">
.mobile-layout {
  min-height: 100vh;
  background: #f7f8fa;
}

.main-content {
  min-height: calc(100vh - 100px);
  padding: 0;
  box-sizing: border-box;
}

@media (max-width: 768px) {
  .main-content {
    padding: 0;
  }
}
</style>
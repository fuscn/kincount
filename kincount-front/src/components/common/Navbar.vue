<template>
  <NavBar
    :title="title"
    fixed
    placeholder
    safe-area-inset-top
  >
    <template #left>
      <Icon name="arrow-left" v-if="showBack" @click="goBack" />
    </template>
    <template #right>
      <Icon name="user-o" @click="showUser = true" />
    </template>
  </NavBar>

  <!-- 右侧用户面板 -->
  <ActionSheet v-model:show="showUser" :actions="userActions" @select="onSelect" />
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { NavBar, Icon, ActionSheet, Toast } from 'vant'
import { useAuthStore } from '@/store/modules/auth'

const route = useRoute()
const router = useRouter()
const auth = useAuthStore()
const title = computed(() => route.meta?.title || '简库存')
const showUser = ref(false)
const showBack = computed(() => !!route.meta?.showBack)
const userActions = [
  { name: '修改密码', key: 'pwd' },
  { name: '退出登录', key: 'logout', color: '#ee0a24' }
]

function goBack() { router.back() }

async function onSelect({ key }) {
  showUser.value = false
  if (key === 'logout') {
    await auth.logout()
    Toast.success('已退出')
  } else if (key === 'pwd') {
    router.push('/system/user/password')
  }
}

</script>
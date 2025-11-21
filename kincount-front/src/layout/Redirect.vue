<!-- src/layout/Redirect.vue -->
<template>
  <div class="redirect-loading">
    <van-loading size="24px">正在跳转...</van-loading>
  </div>
</template>

<script setup>
import { onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'

const route = useRoute()
const router = useRouter()

onMounted(() => {
  const { path } = route.params
  if (path && path !== 'undefined') {
    router.replace('/' + path)
  } else {
    router.replace('/dashboard')
  }
})
</script>

<style scoped>
.redirect-loading {
  height: 100vh;
  display: flex;
  align-items: center;
  justify-content: center;
}
</style>
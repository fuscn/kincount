<template>
  <div class="err-404">
    <van-empty description="页面不存在" />
   <van-button type="primary" @click="goHome">返回首页</van-button>
  </div>
</template>
<script setup>
import { useRouter } from 'vue-router'

const router = useRouter()
function goHome() {
  router.replace('/')   // ✅ replace 不会触发重复守卫
}
</script>
<style scoped>
.err-404 {
  height: 100vh;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
}
</style>
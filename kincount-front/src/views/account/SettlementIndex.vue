<script setup>
import { showToast } from 'vant'

const testToast = () => {
  showToast({
    message: '测试 Toast',
    className: 'test-toast', // 添加自定义类名
    style: {
      background: 'rgba(0,0,0,0.7)',
      color: '#fff'
    }
  })
}
</script>

<template>
  <button @click="testToast">测试 Toast</button>
</template>

<!-- <style scoped>
/* 测试样式 */
.test-toast {
  background: rgba(0, 0, 0, 0.7) !important;
  color: #fff !important;
}
</style> -->
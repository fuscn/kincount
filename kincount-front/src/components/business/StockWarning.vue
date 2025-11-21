<!-- src/components/business/StockWarning.vue -->
<template>
  <Cell
    :title="`低库存预警 (${count})`"
    :value="count > 0 ? '去补货' : ''"
    :label="count > 0 ? `已有 ${count} 种商品低于安全库存` : '暂无预警'"
    :style="{ color: count ? '#ee0a24' : '#07c160' }"
    @click="handleClick"
    :loading="loading"
  >
    <template #icon>
      <Icon name="warning-o" class="icon" />
    </template>
  </Cell>
</template>

<script setup>
import { computed, onMounted, ref, onUnmounted } from 'vue'
import { useRouter } from 'vue-router'
import { Cell, Icon, Toast } from 'vant'
import { useStockStore } from '@/store/modules/stock'

const router = useRouter()
const stockStore = useStockStore()
const loading = ref(false)
const hasLoaded = ref(false)
const isMounted = ref(false)

const count = computed(() => stockStore.warningTotal || 0)

const loadWarningData = async () => {
  // 防止重复加载和组件卸载后的加载
  if (loading.value || hasLoaded.value || !isMounted.value) return
  
  console.log('🔄 StockWarning 开始加载预警数据')
  loading.value = true
  
  try {
    await stockStore.loadWarning({ page: 1, limit: 1 })
    hasLoaded.value = true
    console.log('✅ StockWarning 预警数据加载完成')
  } catch (error) {
    console.error('❌ StockWarning 加载预警数据失败:', error.message)
    // 出错时也标记为已加载，避免重复尝试
    hasLoaded.value = true
  } finally {
    loading.value = false
  }
}

const handleClick = () => {
  if (count.value > 0) {
    router.push('/stock/warning')
  } else {
    Toast('暂无库存预警')
  }
}

onMounted(() => {
  isMounted.value = true
  // 延迟加载，避免与其他组件冲突
  setTimeout(() => {
    loadWarningData()
  }, 500)
})

onUnmounted(() => {
  isMounted.value = false
})
</script>

<style scoped>
.icon {
  margin-right: 8px;
  font-size: 20px;
}
</style>
// src/utils/performance.js
export const performanceMonitor = {
  componentRenders: new Map(),
  
  startRender(componentName) {
    const key = `${componentName}_${Date.now()}`
    this.componentRenders.set(key, {
      name: componentName,
      startTime: performance.now(),
      timestamp: Date.now()
    })
    
    console.log(`🔄 ${componentName} 开始渲染:`, key)
    
    return key
  },
  
  endRender(key) {
    const renderInfo = this.componentRenders.get(key)
    if (renderInfo) {
      const duration = performance.now() - renderInfo.startTime
      console.log(`✅ ${renderInfo.name} 渲染完成:`, `${duration.toFixed(2)}ms`)
      this.componentRenders.delete(key)
    }
  },
  
  logRenders() {
    console.log('📊 当前渲染中的组件:', Array.from(this.componentRenders.values()))
  }
}

// 在 ProductCard.vue 中使用
import { performanceMonitor } from '@/utils/performance'

const renderKey = ref('')

onMounted(() => {
  renderKey.value = performanceMonitor.startRender('ProductCard')
})

onBeforeUnmount(() => {
  if (renderKey.value) {
    performanceMonitor.endRender(renderKey.value)
  }
})
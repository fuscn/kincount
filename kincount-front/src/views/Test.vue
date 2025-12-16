<template>
  <div class="brand-select-test">
    <van-nav-bar 
      title="BrandSelect 组件测试" 
      left-text="返回" 
      left-arrow 
      @click-left="onBack" 
    />
    
    <div class="test-container">
      <!-- 测试1：基础使用 -->
      <div class="test-section">
        <h3>1. 基础使用（绑定ID）</h3>
        <BrandSelect v-model="brandId1" placeholder="请选择品牌" />
        <div class="test-result">
          选中的品牌ID: <strong>{{ brandId1 || '未选择' }}</strong>
        </div>
        <van-button size="small" type="primary" @click="getSelectedBrand1">
          获取选中品牌
        </van-button>
      </div>
      
      <!-- 测试2：返回对象形式 -->
      <div class="test-section">
        <h3>2. 返回对象形式</h3>
        <BrandSelect 
          v-model="brandObject" 
          :return-object="true"
          placeholder="选择品牌（返回对象）" 
        />
        <div class="test-result">
          选中的品牌对象: 
          <pre v-if="brandObject">{{ JSON.stringify(brandObject, null, 2) }}</pre>
          <span v-else>未选择</span>
        </div>
      </div>
      
      <!-- 测试3：显示操作按钮 -->
      <div class="test-section">
        <h3>3. 显示操作按钮</h3>
        <BrandSelect 
          v-model="brandId2" 
          :show-actions="true"
          placeholder="点击选择（带确认按钮）"
          @confirm="onConfirm"
          @cancel="onCancel"
        />
        <div class="test-result">
          选中的品牌ID: <strong>{{ brandId2 || '未选择' }}</strong>
        </div>
      </div>
      
      <!-- 测试4：自定义触发器 -->
      <div class="test-section">
        <h3>4. 自定义触发器</h3>
        <BrandSelect v-model="brandId3">
          <template #trigger="{ selected, open }">
            <div class="custom-trigger" @click="open">
              <van-icon name="shop-o" />
              <span>{{ selected ? `已选: ${selected.name}` : '自定义触发按钮' }}</span>
              <van-icon name="arrow-down" />
            </div>
          </template>
        </BrandSelect>
        <div class="test-result">
          选中的品牌ID: <strong>{{ brandId3 || '未选择' }}</strong>
        </div>
      </div>
      
      <!-- 测试5：禁用状态 -->
      <div class="test-section">
        <h3>5. 禁用状态</h3>
        <BrandSelect 
          v-model="brandId4" 
          :disabled="true"
          placeholder="已禁用的选择器" 
        />
        <div class="test-result">
          选中的品牌ID: <strong>{{ brandId4 || '未选择' }}</strong>
        </div>
      </div>
      
      <!-- 测试6：允许选择已禁用的品牌 -->
      <div class="test-section">
        <h3>6. 允许选择已禁用的品牌</h3>
        <BrandSelect 
          v-model="brandId5" 
          :only-enabled="false"
          :allow-disabled="true"
          placeholder="可选择已禁用品牌" 
        />
        <div class="test-result">
          选中的品牌ID: <strong>{{ brandId5 || '未选择' }}</strong>
        </div>
      </div>
      
      <!-- 测试7：通过ref调用方法 -->
      <div class="test-section">
        <h3>7. 通过ref调用方法</h3>
        <div class="button-group">
          <van-button size="small" @click="openPicker">打开选择器</van-button>
          <van-button size="small" @click="closePicker">关闭选择器</van-button>
          <van-button size="small" @click="loadBrands">加载品牌数据</van-button>
          <van-button size="small" @click="clearSelection">清空选择</van-button>
          <van-button size="small" @click="refreshBrands">刷新品牌列表</van-button>
        </div>
        <BrandSelect 
          ref="brandSelectRef" 
          v-model="brandId6" 
          placeholder="通过ref控制"
          :show-actions="true"
        />
        <div class="test-result">
          选中的品牌ID: <strong>{{ brandId6 || '未选择' }}</strong>
        </div>
      </div>
      
      <!-- 测试8：自定义按钮样式 -->
      <div class="test-section">
        <h3>8. 自定义按钮样式</h3>
        <BrandSelect 
          v-model="brandId7" 
          placeholder="自定义按钮样式"
          :trigger-button-type="'primary'"
          :trigger-button-size="'small'"
          :trigger-button-block="false"
          :show-trigger-icon="false"
        />
        <div class="test-result">
          选中的品牌ID: <strong>{{ brandId7 || '未选择' }}</strong>
        </div>
      </div>
      
      <!-- 测试9：搜索事件 -->
      <div class="test-section">
        <h3>9. 搜索事件</h3>
        <BrandSelect 
          v-model="brandId8" 
          placeholder="测试搜索功能"
          @search="onSearch"
        />
        <div class="test-result">
          选中的品牌ID: <strong>{{ brandId8 || '未选择' }}</strong>
        </div>
      </div>
      
      <!-- 控制台 -->
      <div class="console-section">
        <h3>控制台输出</h3>
        <div class="console">
          <div v-for="(log, index) in consoleLogs" :key="index" class="log-item">
            <span class="time">[{{ log.time }}]</span>
            <span class="message">{{ log.message }}</span>
          </div>
        </div>
        <van-button size="small" @click="clearConsole">清空控制台</van-button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { showToast } from 'vant'
import BrandSelect from '@/components/business/BrandSelect.vue'

const router = useRouter()

// 定义不同的测试变量
const brandId1 = ref(null)
const brandObject = ref(null)
const brandId2 = ref(null)
const brandId3 = ref(null)
const brandId4 = ref(null)
const brandId5 = ref(null)
const brandId6 = ref(null)
const brandId7 = ref(null)
const brandId8 = ref(null)

const brandSelectRef = ref(null)

// 控制台日志
const consoleLogs = ref([])

// 记录日志
const log = (message) => {
  const time = new Date().toLocaleTimeString()
  consoleLogs.value.unshift({ time, message })
  console.log(`[${time}] ${message}`)
}

// 获取选中品牌
const getSelectedBrand1 = () => {
  if (brandSelectRef.value) {
    const brand = brandSelectRef.value.getSelectedBrand?.()
    if (brand) {
      showToast(`选中品牌: ${brand.name}`)
      log(`选中品牌: ${brand.name} (ID: ${brand.id})`)
    } else {
      showToast('未选择品牌')
    }
  }
}

// 确认事件
const onConfirm = (value) => {
  log(`确认选择: ${JSON.stringify(value)}`)
  showToast('已确认选择')
}

// 取消事件
const onCancel = () => {
  log('取消选择')
  showToast('已取消选择')
}

// 搜索事件
const onSearch = (keyword) => {
  log(`搜索关键词: ${keyword}`)
}

// 通过ref调用的方法
const openPicker = () => {
  if (brandSelectRef.value) {
    brandSelectRef.value.openPicker()
    log('通过ref打开选择器')
  }
}

const closePicker = () => {
  if (brandSelectRef.value) {
    brandSelectRef.value.closePicker()
    log('通过ref关闭选择器')
  }
}

const loadBrands = () => {
  if (brandSelectRef.value) {
    brandSelectRef.value.loadBrands()
    log('通过ref加载品牌数据')
  }
}

const clearSelection = () => {
  brandId6.value = null
  if (brandSelectRef.value) {
    brandSelectRef.value.clear()
    log('清空选择')
    showToast('已清空选择')
  }
}

const refreshBrands = () => {
  if (brandSelectRef.value) {
    brandSelectRef.value.refreshBrands()
    log('刷新品牌列表')
    showToast('正在刷新品牌列表...')
  }
}

const clearConsole = () => {
  consoleLogs.value = []
}

const onBack = () => {
  router.back()
}
</script>

<style scoped lang="scss">
.brand-select-test {
  background: #f7f8fa;
  min-height: 100vh;
}

.test-container {
  padding: 16px;
}

.test-section {
  margin-bottom: 24px;
  padding: 16px;
  background: #fff;
  border-radius: 8px;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
  
  h3 {
    margin: 0 0 12px 0;
    font-size: 16px;
    color: #333;
    font-weight: 500;
  }
  
  .test-result {
    margin: 12px 0;
    padding: 8px;
    background: #f5f5f5;
    border-radius: 4px;
    font-size: 14px;
    color: #666;
    
    pre {
      margin: 8px 0 0 0;
      padding: 8px;
      background: #fff;
      border-radius: 4px;
      font-size: 12px;
      overflow: auto;
      max-height: 120px;
    }
  }
  
  .button-group {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    margin-bottom: 12px;
    
    .van-button {
      flex: 1;
      min-width: 100px;
    }
  }
}

.custom-trigger {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 10px 16px;
  background: #f0f9ff;
  border: 1px solid #1890ff;
  border-radius: 8px;
  cursor: pointer;
  
  span {
    flex: 1;
    margin: 0 8px;
    font-size: 14px;
    color: #1890ff;
  }
  
  .van-icon {
    color: #1890ff;
  }
}

.console-section {
  margin-top: 32px;
  padding: 16px;
  background: #1e1e1e;
  border-radius: 8px;
  color: #fff;
  
  h3 {
    margin: 0 0 12px 0;
    color: #fff;
    font-weight: 500;
  }
  
  .console {
    height: 200px;
    overflow-y: auto;
    background: #000;
    border-radius: 4px;
    padding: 8px;
    margin-bottom: 12px;
    font-family: 'Monaco', 'Menlo', 'Ubuntu Mono', monospace;
    font-size: 12px;
    
    .log-item {
      padding: 4px 0;
      border-bottom: 1px solid #333;
      
      .time {
        color: #00ff00;
        margin-right: 8px;
      }
      
      .message {
        color: #fff;
      }
    }
  }
  
  .van-button {
    background: #333;
    color: #fff;
    border: none;
  }
}

/* 响应式调整 */
@media (max-width: 768px) {
  .test-container {
    padding: 12px;
  }
  
  .test-section {
    padding: 12px;
    
    h3 {
      font-size: 15px;
    }
  }
  
  .button-group {
    .van-button {
      min-width: 80px;
      font-size: 12px;
    }
  }
}
</style>
<!-- src/views/test/WarehouseSelectTest.vue -->
<template>
  <div class="warehouse-select-test">
    <van-nav-bar
      title="仓库选择组件测试"
      left-text="返回"
      left-arrow
      @click-left="router.back()"
    />
    
    <div class="test-container">
      <!-- 测试说明 -->
      <div class="test-description">
        <h3>仓库选择组件测试</h3>
        <p>本页面演示WarehouseSelect组件的各种使用场景，请确保后端服务已启动。</p>
        <p>当前仓库选项数量: {{ warehouseOptionsCount }}</p>
      </div>
      
      <!-- 基本用法 -->
      <div class="test-section">
        <h4>1. 基本用法</h4>
        <WarehouseSelect
          v-model="form1.warehouseId"
          label="仓库"
          placeholder="请选择仓库"
          required
        />
        <div class="test-result">
          选中值: {{ form1.warehouseId !== null ? form1.warehouseId : '未选择' }}
        </div>
      </div>
      
      <!-- 显示全部选项 -->
      <div class="test-section">
        <h4>2. 显示"全部"选项</h4>
        <WarehouseSelect
          v-model="form2.warehouseId"
          label="仓库筛选"
          :show-all-option="true"
        />
        <div class="test-result">
          选中值: {{ form2.warehouseId === 0 ? '全部仓库' : (form2.warehouseId || '未选择') }}
        </div>
      </div>
      
      <!-- 禁用状态 -->
      <div class="test-section">
        <h4>3. 禁用状态</h4>
        <div class="test-controls">
          <van-switch v-model="disabled" size="20px" />
          <span>禁用选择</span>
        </div>
        <WarehouseSelect
          v-model="form3.warehouseId"
          label="仓库"
          :disabled="disabled"
        />
        <div class="test-result">
          选中值: {{ form3.warehouseId || '未选择' }}
        </div>
      </div>
      
      <!-- 带错误提示 -->
      <div class="test-section">
        <h4>4. 带错误提示</h4>
        <div class="test-controls">
          <van-button size="small" @click="toggleError">
            {{ showError ? '隐藏错误' : '显示错误' }}
          </van-button>
        </div>
        <WarehouseSelect
          v-model="form4.warehouseId"
          label="仓库"
          :error-message="showError ? '请选择有效的仓库' : ''"
          required
        />
        <div class="test-result">
          选中值: {{ form4.warehouseId || '未选择' }}
        </div>
      </div>
      
      <!-- 排除特定仓库 -->
      <div class="test-section">
        <h4>5. 排除特定仓库</h4>
        <WarehouseSelect
          v-model="form5.warehouseId"
          label="目标仓库"
          :exclude-ids="excludedWarehouseIds"
          placeholder="请选择调拨目标仓库"
        />
        <div class="test-result">
          选中值: {{ form5.warehouseId || '未选择' }}<br>
          排除的仓库ID: {{ excludedWarehouseIds.join(', ') || '无' }}
        </div>
      </div>
      
      <!-- 监听change事件 -->
      <div class="test-section">
        <h4>6. 监听change事件</h4>
        <WarehouseSelect
          v-model="form6.warehouseId"
          label="仓库"
          @change="handleWarehouseChange"
        />
        <div class="test-log">
          <p>最近一次change事件:</p>
          <div class="log-content" v-if="lastChangeLog">
            时间: {{ lastChangeLog.timestamp }}<br>
            ID: {{ lastChangeLog.id }}<br>
            名称: {{ lastChangeLog.name }}
          </div>
          <div class="log-content" v-else>
            暂无选择记录
          </div>
        </div>
      </div>
      
      <!-- 手动控制加载 -->
      <div class="test-section">
        <h4>7. 手动控制加载</h4>
        <WarehouseSelect
          ref="manualWarehouseSelectRef"
          v-model="form7.warehouseId"
          label="仓库"
          :auto-load="false"
        />
        <div class="test-controls">
          <van-button type="primary" size="small" @click="loadOptionsManually">
            手动加载选项
          </van-button>
          <van-button type="default" size="small" @click="resetManualSelect">
            重置选择
          </van-button>
          <van-button type="warning" size="small" @click="setDefaultValue">
            设置默认值
          </van-button>
        </div>
        <div class="test-result">
          选中值: {{ form7.warehouseId || '未选择' }}
        </div>
      </div>
      
      <!-- 综合表单示例 -->
      <div class="test-section">
        <h4>8. 综合表单示例</h4>
        <div class="form-example">
          <van-cell-group inset>
            <van-field
              v-model="comprehensiveForm.orderNo"
              label="订单编号"
              placeholder="请输入订单编号"
            />
            <WarehouseSelect
              v-model="comprehensiveForm.warehouseId"
              label="仓库"
              required
              :error-message="comprehensiveFormErrors.warehouseId"
            />
            <van-field
              v-model="comprehensiveForm.remark"
              label="备注"
              type="textarea"
              placeholder="请输入备注"
              rows="2"
              autosize
            />
          </van-cell-group>
          <div class="form-actions">
            <van-button type="primary" @click="submitComprehensiveForm">
              提交表单
            </van-button>
            <van-button type="default" @click="resetComprehensiveForm">
              重置
            </van-button>
          </div>
        </div>
      </div>
      
      <!-- 当前状态显示 -->
      <div class="status-section">
        <h4>当前所有表单状态</h4>
        <div class="status-list">
          <div v-for="(form, index) in allFormData" :key="index">
            表单{{ index + 1 }}: {{ form.warehouseId !== null ? form.warehouseId : '未选择' }}
          </div>
        </div>
      </div>
    </div>
    
    <!-- 加载提示 -->
    <van-overlay :show="loading">
      <div class="loading-overlay">
        <van-loading type="spinner" color="#1989fa" />
        <div class="loading-text">加载中...</div>
      </div>
    </van-overlay>
  </div>
</template>

<script>
// 使用局部引入
import WarehouseSelect from '@/components/business/WarehouseSelect.vue'
import { ref, reactive, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { showToast, showDialog, showNotify } from 'vant'
import { useWarehouseStore } from '@/store/modules/warehouse'

export default {
  name: 'WarehouseSelectTest',
  
  // 局部注册组件
  components: {
    WarehouseSelect
  },
  
  setup() {
    const router = useRouter()
    const warehouseStore = useWarehouseStore()
    
    // 表单数据初始化
    const form1 = reactive({ warehouseId: null })
    const form2 = reactive({ warehouseId: null })
    const form3 = reactive({ warehouseId: null })
    const form4 = reactive({ warehouseId: null })
    const form5 = reactive({ warehouseId: null })
    const form6 = reactive({ warehouseId: null })
    const form7 = reactive({ warehouseId: null })
    
    const disabled = ref(false)
    const showError = ref(false)
    const excludedWarehouseIds = ref([1, 2])
    const lastChangeLog = ref(null)
    const manualWarehouseSelectRef = ref(null)
    const loading = ref(false)
    
    const comprehensiveForm = reactive({
      orderNo: '',
      warehouseId: null,
      remark: ''
    })
    
    const comprehensiveFormErrors = reactive({
      warehouseId: ''
    })
    
    const toggleError = () => {
      showError.value = !showError.value
      if (showError.value && !form4.warehouseId) {
        showNotify({ type: 'warning', message: '请选择一个仓库以清除错误提示' })
      }
    }
    
    const handleWarehouseChange = (id, name) => {
      lastChangeLog.value = {
        id,
        name,
        timestamp: new Date().toLocaleTimeString()
      }
      
      showToast({
        message: `选择了仓库: ${name} (ID: ${id})`,
        position: 'top'
      })
    }
    
    const loadOptionsManually = async () => {
      try {
        if (manualWarehouseSelectRef.value) {
          await manualWarehouseSelectRef.value.loadWarehouseOptions()
          showToast('仓库选项加载成功')
        }
      } catch (error) {
        showToast('加载失败: ' + (error.message || '未知错误'))
      }
    }
    
    const resetManualSelect = () => {
      form7.warehouseId = null
      showToast('已重置选择')
    }
    
    const setDefaultValue = () => {
      form7.warehouseId = 1
      showToast('已设置默认值: 1')
    }
    
    const validateComprehensiveForm = () => {
      let isValid = true
      
      comprehensiveFormErrors.warehouseId = ''
      
      if (comprehensiveForm.warehouseId === null || comprehensiveForm.warehouseId === undefined) {
        comprehensiveFormErrors.warehouseId = '请选择仓库'
        isValid = false
      }
      
      return isValid
    }
    
    const submitComprehensiveForm = () => {
      if (validateComprehensiveForm()) {
        showDialog({
          title: '表单提交',
          message: `订单编号: ${comprehensiveForm.orderNo || '无'}
                    仓库ID: ${comprehensiveForm.warehouseId}
                    备注: ${comprehensiveForm.remark || '无'}`,
          theme: 'round-button'
        }).then(() => {
          showToast('表单提交成功')
        })
      } else {
        showToast('请检查表单错误', { type: 'fail' })
      }
    }
    
    const resetComprehensiveForm = () => {
      comprehensiveForm.orderNo = ''
      comprehensiveForm.warehouseId = null
      comprehensiveForm.remark = ''
      comprehensiveFormErrors.warehouseId = ''
      showToast('表单已重置')
    }
    
    const allFormData = computed(() => {
      return [
        { warehouseId: form1.warehouseId },
        { warehouseId: form2.warehouseId },
        { warehouseId: form3.warehouseId },
        { warehouseId: form4.warehouseId },
        { warehouseId: form5.warehouseId },
        { warehouseId: form6.warehouseId },
        { warehouseId: form7.warehouseId },
        { warehouseId: comprehensiveForm.warehouseId }
      ]
    })
    
    const warehouseOptionsCount = computed(() => {
      return warehouseStore.options.length
    })
    
    const simulateLoading = () => {
      loading.value = true
      setTimeout(() => {
        loading.value = false
      }, 1000)
    }
    
    onMounted(() => {
      simulateLoading()
    })
    
    return {
      router,
      form1,
      form2,
      form3,
      form4,
      form5,
      form6,
      form7,
      comprehensiveForm,
      comprehensiveFormErrors,
      disabled,
      showError,
      excludedWarehouseIds,
      lastChangeLog,
      manualWarehouseSelectRef,
      loading,
      allFormData,
      warehouseOptionsCount,
      toggleError,
      handleWarehouseChange,
      loadOptionsManually,
      resetManualSelect,
      setDefaultValue,
      submitComprehensiveForm,
      resetComprehensiveForm
    }
  }
}
</script>

<style scoped>
.warehouse-select-test {
  min-height: 100vh;
  background-color: #f5f5f5;
}

.test-container {
  padding: 16px;
}

.test-description {
  background: white;
  border-radius: 8px;
  padding: 16px;
  margin-bottom: 16px;
  box-shadow: 0 2px 12px rgba(0, 0, 0, 0.05);
}

.test-description h3 {
  margin-top: 0;
  color: #323233;
}

.test-description p {
  color: #646566;
  margin-bottom: 0;
}

.test-section {
  background: white;
  border-radius: 8px;
  padding: 16px;
  margin-bottom: 16px;
  box-shadow: 0 2px 12px rgba(0, 0, 0, 0.05);
}

.test-section h4 {
  margin-top: 0;
  margin-bottom: 12px;
  color: #1989fa;
  font-size: 16px;
}

.test-result {
  margin-top: 12px;
  padding: 8px 12px;
  background-color: #f7f8fa;
  border-radius: 4px;
  color: #646566;
  font-size: 14px;
}

.test-controls {
  display: flex;
  align-items: center;
  gap: 12px;
  margin-bottom: 12px;
  flex-wrap: wrap;
}

.test-log {
  margin-top: 12px;
  padding: 12px;
  background-color: #f7f8fa;
  border-radius: 4px;
  border-left: 3px solid #1989fa;
}

.test-log p {
  margin: 0 0 8px 0;
  font-weight: 500;
  color: #323233;
}

.log-content {
  padding: 8px;
  background: white;
  border-radius: 4px;
  color: #646566;
  font-size: 14px;
}

.form-example {
  background-color: #f7f8fa;
  border-radius: 8px;
  overflow: hidden;
}

.form-actions {
  display: flex;
  gap: 12px;
  padding: 16px;
  background: white;
  margin-top: 1px;
}

.status-section {
  background: white;
  border-radius: 8px;
  padding: 16px;
  margin-top: 16px;
  box-shadow: 0 2px 12px rgba(0, 0, 0, 0.05);
}

.status-list {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 8px;
  margin-top: 12px;
}

.status-list > div {
  padding: 8px 12px;
  background-color: #f7f8fa;
  border-radius: 4px;
  font-size: 14px;
  color: #646566;
}

.loading-overlay {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  height: 100%;
  background-color: rgba(255, 255, 255, 0.8);
}

.loading-text {
  margin-top: 12px;
  color: #1989fa;
  font-size: 16px;
}
</style>
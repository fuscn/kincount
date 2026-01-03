<template>
  <div class="purchase-order-form">
    <van-nav-bar 
      :title="isEdit ? '编辑采购订单' : '新增采购订单'" 
      fixed 
      placeholder 
      left-text="取消" 
      right-text="保存"
      @click-left="handleBack" 
      @click-right="handleSubmit" 
    />

    <div class="form-container">
      <!-- 订单基本信息 -->
      <van-form ref="formRef" @submit="handleSubmit">
        <!-- 供应商选择 -->
        <van-field
          readonly
          clickable
          label="供应商"
          :placeholder="form.supplier_name || '请选择供应商'"
          :rules="[{ required: true, message: '请选择供应商' }]"
          @click="openSupplierSelect"
        >
          <template #input>
            {{ form.supplier_name || '' }}
          </template>
          <template #right-icon>
            <van-icon name="arrow" />
          </template>
        </van-field>
        
        <!-- 仓库选择 -->
        <van-field
          readonly
          clickable
          label="入库仓库"
          :placeholder="form.warehouse_name || '请选择仓库'"
          :rules="[{ required: true, message: '请选择仓库' }]"
          @click="openWarehouseSelect"
        >
          <template #input>
            {{ form.warehouse_name || '' }}
          </template>
          <template #right-icon>
            <van-icon name="arrow" />
          </template>
        </van-field>
        
        <!-- 预计到货日期 -->
        <van-field
          v-model="form.expected_date"
          readonly
          clickable
          label="预计到货"
          :placeholder="form.expected_date || '请选择日期'"
          :rules="[{ required: true, message: '请选择预计到货日期' }]"
          @click="showDatePicker = true"
        >
          <template #right-icon>
            <van-icon name="arrow" />
          </template>
        </van-field>
        
        <!-- 备注 -->
        <van-field 
          v-model="form.remark" 
          name="remark" 
          label="备注" 
          type="textarea" 
          placeholder="请输入备注信息" 
          rows="3" 
        />
        
        <!-- SKU明细选择区域 -->
        <div class="sku-section">
          <div class="section-title">
            <span>采购商品明细</span>
            <van-button size="small" type="primary" @click="showSkuSelect = true" icon="plus">
              添加商品
            </van-button>
          </div>
          
          <!-- SKU列表 -->
          <van-empty v-if="form.items.length === 0" description="请添加采购商品" />
          <van-cell-group v-else class="sku-list">
              <!-- SKU 明细项 -->
            <van-swipe-cell
              v-for="(item, index) in form.items"
              :key="item.sku_id || index"
              :disabled="false"
              class="sku-item"
            >
              <van-cell class="sku-cell">
                <div class="product-grid">
                  <!-- 第一行：商品名     数量 -->
                  <div class="grid-row first-row">
                    <div class="left-column">
                      <span class="product-name">{{ getProductDisplayName(item) }}</span>
                    </div>
                    <div class="right-column">
                      <!-- 数量输入 -->
                      <van-field
                        v-model="item.quantity"
                        type="number"
                        placeholder="0"
                        class="editable-field compact-field quantity-field"
                        @blur="validateQuantity(item)"
                        :error-message="item.quantityError"
                      >
                        <template #extra>{{ item.unit || '个' }}</template>
                      </van-field>
                    </div>
                  </div>

                  <!-- 第二行：规格文本  单位        单价 -->
                  <div class="grid-row second-row">
                    <div class="left-column">
                      <span class="spec-text-inline" v-if="getItemSpecText(item)">规格: {{ getItemSpecText(item) }}</span>
                      <span class="unit-text">单位: {{ item.unit || '个' }}</span>
                    </div>
                    <div class="right-column">
                      <!-- 价格输入 -->
                      <van-field
                        v-model="item.price"
                        type="number"
                        placeholder="0.00"
                        class="editable-field compact-field price-field"
                        @blur="validatePrice(item)"
                        :error-message="item.priceError"
                      >
                        <template #extra>元</template>
                      </van-field>
                    </div>
                  </div>

                  <!-- 第三行：可用库存               金额小计 -->
                  <div class="grid-row third-row">
                    <div class="left-column">
                      <span class="stock-info" v-if="item.available_stock !== undefined">
                        可用库存: {{ item.available_stock }} {{ item.unit || '个' }}
                      </span>
                    </div>
                    <div class="right-column">
                      <!-- 总金额显示 -->
                      <div class="total-amount">¥{{ ((item.price || 0) * (item.quantity || 0)).toFixed(2) }}</div>
                    </div>
                  </div>
                </div>
              </van-cell>
              <template #right>
                <van-button square type="danger" text="删除" class="delete-btn" @click="deleteSku(index)" />
              </template>
            </van-swipe-cell>
          </van-cell-group>
        </div>
        
        <!-- 合计金额 -->
        <div class="total-section" v-if="form.items.length > 0">
          <span>订单总金额：</span>
          <span class="amount">¥{{ totalAmount.toFixed(2) }}</span>
        </div>
      </van-form>
    </div>

    <!-- 供应商选择器组件 - 简化属性 -->
    <SupplierSelect
      ref="supplierSelectRef"
      v-model="form.supplier_id"
      :popup-title="'选择供应商'"
      :search-placeholder="'搜索供应商'"
      :only-enabled="true"
      :hide-trigger="true"
      @change="onSupplierSelectChange"
      @confirm="onSupplierConfirm"
    />
    
    <!-- 仓库选择器组件 - 简化属性 -->
    <WarehouseSelect
      ref="warehouseSelectRef"
      v-model="form.warehouse_id"
      :popup-title="'选择仓库'"
      :search-placeholder="'搜索仓库'"
      :only-enabled="true"
      :hide-trigger="true"
      @change="onWarehouseSelectChange"
      @confirm="onWarehouseConfirm"
    />
    
    <!-- 日期选择器 -->
    <van-popup v-model:show="showDatePicker" position="bottom" :close-on-click-overlay="true">
      <van-date-picker 
        v-model="currentDate" 
        title="选择预计到货日期" 
        :min-date="minDate" 
        :max-date="maxDate"
        @confirm="onDateConfirm" 
        @cancel="closeDatePicker" 
      />
    </van-popup>
    
    <!-- SKU选择弹窗 -->
    <van-popup v-model:show="showSkuSelect" position="bottom" :style="{ height: '80%' }" :close-on-click-overlay="true">
      <SkuSelect 
        ref="skuSelectRef" 
        v-model="selectedSkuIds" 
        :show-header="true" 
        :show-footer="false"
        header-title="选择采购商品" 
        @confirm="handleSkuSelectConfirm" 
        @cancel="closeSkuPicker" 
      />
    </van-popup>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted, nextTick } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { showToast, showConfirmDialog, showSuccessToast, showFailToast } from 'vant'
import { usePurchaseStore } from '@/store/modules/purchase'
import SupplierSelect from '@/components/business/SupplierSelect.vue'
import WarehouseSelect from '@/components/business/WarehouseSelect.vue'
import { SkuSelect } from '@/components'

// 路由相关
const route = useRoute()
const router = useRouter()
const isEdit = !!route.params.id

// 状态管理
const purchaseStore = usePurchaseStore()

// 组件引用
const formRef = ref(null)
const supplierSelectRef = ref(null)
const warehouseSelectRef = ref(null)
const skuSelectRef = ref(null)
const submitting = ref(false)

// 递归更新防护
let recursionGuard = false

// 安全更新函数
const safeUpdate = (callback) => {
  if (recursionGuard) return
  recursionGuard = true
  try {
    callback()
  } finally {
    setTimeout(() => {
      recursionGuard = false
    }, 0)
  }
}

// 获取明天的日期
const getTomorrowDate = () => {
  const tomorrow = new Date()
  tomorrow.setDate(tomorrow.getDate() + 1)
  return tomorrow
}

// 格式化日期为 YYYY-MM-DD
const formatDate = (date) => {
  const year = date.getFullYear()
  const month = String(date.getMonth() + 1).padStart(2, '0')
  const day = String(date.getDate()).padStart(2, '0')
  return `${year}-${month}-${day}`
}

// 表单数据
const form = reactive({
  supplier_id: '',
  supplier_name: '',
  warehouse_id: '',
  warehouse_name: '',
  expected_date: '',
  remark: '',
  items: [] // 存储选中的SKU明细
})

// 选择器状态
const showDatePicker = ref(false)
const showSkuSelect = ref(false)

// SKU选择相关
const selectedSkuIds = ref([])

// 日期相关
const currentDate = ref([])
const minDate = new Date()
const maxDate = new Date(new Date().setFullYear(new Date().getFullYear() + 1))

// 计算订单总金额
const totalAmount = computed(() => {
  return form.items.reduce((sum, item) => {
    const price = Number(item.price) || 0
    const quantity = Number(item.quantity) || 0
    return sum + (price * quantity)
  }, 0)
})

// 获取商品显示名称
const getProductDisplayName = (item) => {
  if (item.product && item.product.name) {
    return item.product.name
  }
  if (item.product_name) {
    return item.product_name
  }
  if (item.sku_name) {
    return item.sku_name
  }
  return item.name || '未知商品'
}

// 获取商品规格文本
const getItemSpecText = (item) => {
  if (item.spec_text) {
    return item.spec_text
  }
  if (item.spec && typeof item.spec === 'object') {
    return Object.entries(item.spec)
      .map(([key, value]) => `${key}:${value}`)
      .join(' ')
  }
  if (item.product && item.product.spec) {
    if (typeof item.product.spec === 'object') {
      return Object.entries(item.product.spec)
        .map(([key, value]) => `${key}:${value}`)
        .join(' ')
    }
    return String(item.product.spec)
  }
  return ''
}

// 从SKU数据获取商品名称
const getSkuProductName = (sku) => {
  if (sku.product && sku.product.name) {
    return sku.product.name
  }
  if (sku.product_name) {
    return sku.product_name
  }
  return sku.name || '未知商品'
}

// 初始化表单数据
const initForm = async () => {
  if (isEdit) {
    // 编辑模式：加载订单详情
    const id = route.params.id
    try {
      const orderDetail = await purchaseStore.loadOrderDetail(id)
      console.log('订单详情数据:', orderDetail)
      
      if (orderDetail) {
        form.supplier_id = orderDetail.supplier_id
        form.supplier_name = orderDetail.supplier?.name || ''
        form.warehouse_id = orderDetail.warehouse_id
        form.warehouse_name = orderDetail.warehouse?.name || ''
        form.expected_date = orderDetail.expected_date
        form.remark = orderDetail.remark || ''
        
        // 处理订单项数据
        form.items = (orderDetail.items || []).map(item => {
          const product = item.product || {}
          console.log('订单项数据:', item)
          
          // 构建规格文本
          let specText = ''
          if (product.spec) {
            if (typeof product.spec === 'object') {
              specText = Object.entries(product.spec)
                .map(([key, value]) => `${key}:${value}`)
                .join(' ')
            } else {
              specText = String(product.spec)
            }
          }
          
          return {
            id: item.id,
            sku_id: item.sku_id,
            product_id: item.product_id,
            sku_code: product.product_no || '',
            product: product,
            product_name: product.name || '',
            sku_name: product.name || '',
            spec_text: specText,
            spec: product.spec || {},
            unit: product.unit || '个',
            price: item.price || 0,
            quantity: item.quantity || 0,
            priceError: '',
            quantityError: ''
          }
        })
        
        // 设置选中的SKU ID
        selectedSkuIds.value = form.items.map(item => item.sku_id).filter(Boolean)
        console.log('表单items数据:', form.items)
        console.log('选中的SKU IDs:', selectedSkuIds.value)
        
        // 设置日期选择器的当前值
        if (orderDetail.expected_date) {
          const date = new Date(orderDetail.expected_date)
          currentDate.value = [date.getFullYear(), date.getMonth() + 1, date.getDate()]
        }
      } else {
        showFailToast('加载订单详情失败')
        router.back()
      }
    } catch (error) {
      console.error('加载订单详情失败:', error)
      showFailToast('加载订单详情失败')
      router.back()
    }
  } else {
    // 新增模式：设置默认日期为明天
    const tomorrow = getTomorrowDate()
    form.expected_date = formatDate(tomorrow)
    currentDate.value = [tomorrow.getFullYear(), tomorrow.getMonth() + 1, tomorrow.getDate()]
  }
}

// 供应商选择器事件处理
const onSupplierSelectChange = (id, name) => {
  console.log('供应商选择变化:', id, name)
  if (id) {
    form.supplier_id = id
    form.supplier_name = name
    console.log('选择的供应商:', name, 'ID:', id)
  } else {
    form.supplier_id = ''
    form.supplier_name = ''
    console.log('已清空供应商选择')
  }
}

// 供应商确认事件
const onSupplierConfirm = (id, supplier) => {
  console.log('供应商确认:', id, supplier)
  if (id) {
    form.supplier_id = id
    form.supplier_name = supplier?.name || ''
  }
}

// 仓库选择器事件处理
const onWarehouseSelectChange = (id, name) => {
  console.log('仓库选择变化:', id, name)
  if (id) {
    form.warehouse_id = id
    form.warehouse_name = name
    console.log('选择的仓库:', name, 'ID:', id)
  } else {
    form.warehouse_id = ''
    form.warehouse_name = ''
    console.log('已清空仓库选择')
  }
}

// 仓库确认事件
const onWarehouseConfirm = (id, warehouse) => {
  console.log('仓库确认:', id, warehouse)
  if (id) {
    form.warehouse_id = id
    form.warehouse_name = warehouse?.name || ''
  }
}

// 手动触发供应商选择器打开
const openSupplierSelect = () => {
  supplierSelectRef.value?.openPicker?.()
}

// 手动触发仓库选择器打开
const openWarehouseSelect = () => {
  warehouseSelectRef.value?.openPicker?.()
}

// 日期确认
const onDateConfirm = ({ selectedValues }) => {
  safeUpdate(() => {
    const [year, month, day] = selectedValues
    form.expected_date = `${year}-${String(month).padStart(2, '0')}-${String(day).padStart(2, '0')}`
    showDatePicker.value = false
  })
}

// 关闭日期选择器
const closeDatePicker = () => {
  safeUpdate(() => {
    showDatePicker.value = false
  })
}

// SKU选择结果处理
const handleSkuSelectConfirm = async (result) => {
  safeUpdate(async () => {
    const { selectedIds, selectedData } = result

    if (!selectedData || selectedData.length === 0) {
      showToast('未选择任何商品')
      return
    }

    let addedCount = 0
    // 获取SKU详细信息并添加到表单
    for (const sku of selectedData) {
      if (!sku) continue

      // 检查该SKU是否已经存在于表单中
      const isExist = form.items.some(item => item.sku_id === sku.id)
      if (isExist) {
        continue // 已经存在，跳过，既不累加也不添加
      }

      try {
        const newItem = {
          sku_id: sku.id,
          product_id: sku.product_id,
          sku_code: sku.sku_code || '',
          product: sku.product || null,
          product_name: getSkuProductName(sku),
          sku_name: sku.name || '',
          spec_text: sku.spec_text || '',
          spec: sku.spec || {},
          unit: sku.unit || '个',
          price: sku.cost_price || 0,
          quantity: 1,
          priceError: '',
          quantityError: ''
        }
        form.items.push(newItem)
        addedCount++
      } catch (error) {
        console.error('添加SKU失败:', error)
      }
    }

    showSkuSelect.value = false
    if (addedCount > 0) {
      showSuccessToast(`已添加 ${addedCount} 个商品`)
    } else {
      showToast('所选商品已全部存在于列表中')
    }
  })
}

// 关闭SKU选择器
const closeSkuPicker = () => {
  safeUpdate(() => {
    showSkuSelect.value = false
  })
}

// 删除SKU
const deleteSku = (index) => {
  safeUpdate(() => {
    form.items.splice(index, 1)
    // 同步更新选中的SKU ID
    selectedSkuIds.value = form.items.map(item => item.sku_id)
  })
}

// 验证价格
const validatePrice = (item) => {
  setTimeout(() => {
    // 使用 Number() 显式转换，避免 0 被判断为 false
    const price = item.price === null || item.price === undefined ? NaN : Number(item.price)
    if (isNaN(price) || price < 0) {
      item.priceError = '单价必须大于等于0'
      return false
    }
    item.priceError = ''
    return true
  }, 0)
}

// 验证数量
const validateQuantity = (item) => {
  setTimeout(() => {
    // 使用 Number() 显式转换，避免 0 被判断为 false
    const quantity = item.quantity === null || item.quantity === undefined ? NaN : Number(item.quantity)
    if (isNaN(quantity) || quantity <= 0) {
      item.quantityError = '数量必须大于0'
      return false
    }
    item.quantityError = ''
    return true
  }, 0)
}

// 验证表单
const validateForm = () => {
  if (!form.supplier_id) {
    showToast('请选择供应商')
    return false
  }
  if (!form.warehouse_id) {
    showToast('请选择入库仓库')
    return false
  }
  if (!form.expected_date) {
    showToast('请选择预计到货日期')
    return false
  }
  if (form.items.length === 0) {
    showToast('请至少添加一个采购商品')
    return false
  }
  // 验证每个SKU的价格和数量
  for (const item of form.items) {
    // 使用 Number() 显式转换，避免 0 被判断为 false
    const price = item.price === null || item.price === undefined ? NaN : Number(item.price)
    const quantity = item.quantity === null || item.quantity === undefined ? NaN : Number(item.quantity)
    if (isNaN(price) || price < 0) {
      showToast(`请检查商品"${getProductDisplayName(item)}"的单价`)
      return false
    }
    if (isNaN(quantity) || quantity <= 0) {
      showToast(`请检查商品"${getProductDisplayName(item)}"的数量`)
      return false
    }
  }
  return true
}

// 表单提交
const handleSubmit = async () => {
  if (!validateForm()) {
    return
  }
  submitting.value = true
  try {
    // 准备提交数据
    const submitData = {
      supplier_id: form.supplier_id,
      warehouse_id: form.warehouse_id,
      expected_date: form.expected_date,
      remark: form.remark,
      items: form.items.map(item => {
        const productId = item.product_id || (item.product && item.product.id)
        if (!productId) {
          throw new Error(`商品"${getProductDisplayName(item)}"缺少商品ID`)
        }
        if (!item.sku_id) {
          throw new Error(`商品"${getProductDisplayName(item)}"缺少SKU ID`)
        }
        const itemData = {
          product_id: productId,
          sku_id: item.sku_id,
          // 使用 Number() 显式转换，确保 0 值能正确传递
          quantity: item.quantity === null || item.quantity === undefined ? 0 : Number(item.quantity),
          price: item.price === null || item.price === undefined ? 0 : Number(item.price)
        }
        
        // 编辑模式下，如果有订单项ID，则包含它
        if (isEdit && item.id) {
          itemData.id = item.id
        }
        
        return itemData
      })
    }

    console.log('提交数据:', submitData)    
    console.log('商品明细详情:', form.items)    
    console.log('处理后的商品明细:', submitData.items)

    let result
    if (isEdit) {
      // 编辑订单
      result = await purchaseStore.updateOrder(route.params.id, submitData)
      showSuccessToast('更新成功')
    } else {
      // 创建新订单
      result = await purchaseStore.addOrder(submitData)
      showSuccessToast('创建成功')
      if (result?.id) {
        // 创建成功后跳转到详情页
        router.push(`/purchase/order/detail/${result.id}`)
        return
      }
    }
    // 操作成功后返回列表页
    router.push('/purchase/order')
  } catch (error) {
    console.error('提交订单失败:', error)
    showFailToast(error.message || '操作失败')
  } finally {
    submitting.value = false
  }
}

// 返回上一页
const handleBack = () => {
  safeUpdate(() => {
    if (form.items.length > 0 || form.supplier_id || form.warehouse_id || form.remark) {
      showConfirmDialog({
        title: '提示',
        message: '表单内容已修改，是否放弃保存？'
      }).then(() => {
        router.back()
      }).catch(() => {
        // 取消返回
      })
    } else {
      router.back()
    }
  })
}

onMounted(async () => {
  await initForm()
})
</script>

<style scoped lang="scss">
.purchase-order-form {
  background-color: #f7f8fa;
  min-height: 100vh;
  padding-top: 46px; // 为固定导航栏留出空间
}

.form-container {
  padding: 0;
  background-color: #f7f8fa;
}

.sku-section {
  margin: 16px 0;
  background-color: #fff;
  border-radius: 8px;
  overflow: hidden;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
}

.section-title {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 12px 16px;
  border-bottom: 1px solid #f0f0f0;
  background-color: #fafafa;
  
  span {
    font-size: 15px;
    font-weight: 600;
    color: #323233;
  }
  
  .van-button {
    border-radius: 6px;
    font-weight: 500;
    height: 32px;
    font-size: 13px;
    
    :deep(.van-icon) {
      margin-right: 4px;
    }
  }
}

// 商品列表样式优化
.sku-list {
  .sku-item {
    margin-bottom: 8px;
    border-radius: 8px;
    overflow: hidden;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
    
    &:last-child {
      margin-bottom: 0;
    }
  }
  
  .sku-cell {
    padding: 10px 16px;
    background-color: #fff;
    
    &:after {
      display: none;
    }
  }
}

.product-grid {
  display: flex;
  flex-direction: column;
  gap: 4px;
  width: 100%;
}

.grid-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 12px;
  
  .left-column {
    flex: 1;
    display: flex;
    align-items: center;
    gap: 8px;
    min-width: 0;
  }
  
  .right-column {
    flex-shrink: 0;
    display: flex;
    align-items: center;
    justify-content: flex-end;
    min-width: 100px;
  }
}

.first-row {
  .left-column {
    display: flex;
    flex-direction: column;
    align-items: flex-start;
    gap: 2px;
    flex: 1;
    min-width: 0;
    
    .product-name {
      font-weight: bold;
      color: #323233;
      font-size: 14px;
      white-space: normal;
      word-wrap: break-word;
      line-height: 1.3;
    }
    
    .spec-text-inline {
      font-size: 12px;
      color: #969799;
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
    }
  }
}

.second-row {
  .left-column {
    display: flex;
    align-items: flex-start;
    gap: 8px;
    flex: 1;
    min-width: 0;
    
    .spec-text-inline {
      font-size: 12px;
      color: #969799;
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
      text-align: left;
    }
    
    .unit-text {
      font-size: 12px;
      color: #969799;
    }
  }
}

.third-row {
  .left-column {
    .stock-info {
      font-size: 12px;
      color: #1890ff;
      white-space: nowrap;
    }
  }
  
  .right-column {
    flex-direction: column;
    align-items: flex-end;
    gap: 4px;
  }
}

// 紧凑字段样式
.compact-field {
  border: 1px solid #e0e0e0;
  border-radius: 4px;
  background: #fff;
  transition: all 0.2s;
  height: 22px;
  width: 85px;
  overflow: hidden;
  vertical-align: top;
  padding: 0 !important;
  box-sizing: border-box;
  
  &:deep(.van-field__body) {
    min-height: auto;
    height: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 0 !important;
    margin: 0;
    box-sizing: border-box;
  }
  
  &:deep(.van-field__control) {
    font-size: 12px !important;
    font-weight: 500;
    color: #323233;
    text-align: center;
    padding: 0 !important;
    height: 100% !important;
    line-height: 22px !important;
    margin: 0;
    border: none;
    outline: none;
    background: transparent;
    display: block;
    max-height: 22px;
  }
  
  &:deep(.van-field__extra) {
    color: #969799;
    font-size: 10px;
    padding-left: 2px;
    flex-shrink: 0;
    line-height: 22px;
    height: 100%;
    display: flex;
    align-items: center;
    margin: 0;
  }
  
  &:focus-within {
    border-color: #1989fa;
    box-shadow: 0 0 0 2px rgba(25, 137, 250, 0.1);
  }
}

.total-amount {
  color: #f53f3f;
  font-weight: bold;
  font-size: 13px;
  line-height: 1.3;
  text-align: right;
}

.stock-warning {
  display: flex;
  align-items: center;
  gap: 2px;
  color: #f53f3f;
  font-size: 11px;
  
  span {
    line-height: 1;
  }
}

.total-section {
  padding: 16px;
  background-color: #f8f9fa;
  border-radius: 8px;
  margin: 16px 0;
  border: 1px solid #e9ecef;
  
  .total-line {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 8px;
    font-size: 14px;
    
    &:last-child {
      margin-bottom: 0;
      padding-top: 8px;
      border-top: 1px solid #e9ecef;
    }
    
    .amount {
      color: #323233;
      font-weight: 500;
      
      &.discount {
        color: #52c41a;
      }
      
      &.total {
        color: #f53f3f;
        font-weight: bold;
        font-size: 16px;
      }
    }
  }
}

// 滑动单元格样式优化
:deep(.van-swipe-cell) {
  .van-swipe-cell__wrapper {
    padding: 0;
  }
  
  .delete-btn {
    height: 100%;
    border-radius: 0;
  }
}

// 修改字段样式，使其更突出
:deep(.van-field) {
  &.van-field--readonly {
    .van-field__control {
      color: #323233;
      font-weight: 500;
    }
  }
  
  .van-field__label {
    color: #646566;
    font-weight: 500;
  }
}

// 修改空状态样式
:deep(.van-empty) {
  padding: 30px 0;
  
  .van-empty__image {
    width: 100px;
    height: 100px;
  }
  
  .van-empty__description {
    color: #969799;
    font-size: 13px;
  }
}
</style>
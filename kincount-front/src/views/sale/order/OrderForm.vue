<template>
  <div class="sale-order-form">
    <van-nav-bar 
      :title="isEdit ? '编辑销售订单' : '新增销售订单'" 
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
        <!-- 客户选择 -->
        <van-field
          readonly
          clickable
          label="客户"
          :placeholder="form.customer_name || '请选择客户'"
          :rules="[{ required: true, message: '请选择客户' }]"
          @click="openCustomerSelect"
        >
          <template #input>
            {{ form.customer_name || '' }}
          </template>
          <template #right-icon>
            <van-icon name="arrow" />
          </template>
        </van-field>
        
        <!-- 仓库选择 -->
        <van-field
          readonly
          clickable
          label="出库仓库"
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
        
        <!-- 预计发货日期 -->
        <van-field
          v-model="form.expected_date"
          readonly
          clickable
          label="预计发货"
          :placeholder="form.expected_date || '请选择日期'"
          :rules="[{ required: true, message: '请选择预计发货日期' }]"
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
            <span>销售商品明细</span>
            <van-button 
              size="small" 
              type="primary" 
              @click="openSkuSelector" 
              icon="plus"
              :disabled="!form.warehouse_id"
            >
              添加商品
            </van-button>
          </div>
          
          <!-- SKU列表 -->
          <van-empty v-if="form.items.length === 0" description="请添加销售商品" />
          <van-cell-group v-else class="sku-list">
            <van-swipe-cell v-for="(item, index) in form.items" :key="item.sku_id + '_' + index" class="sku-item">
              <van-cell class="sku-cell">
                <template #title>
                  <div class="product-title">
                    <span class="product-name">{{ getProductDisplayName(item) }}</span>
                    <span class="sku-code">{{ item.sku_code }}</span>
                  </div>
                </template>
                <template #label>
                  <div class="product-label">
                    <div class="spec-text" v-if="getItemSpecText(item)">规格: {{ getItemSpecText(item) }}</div>
                    <div class="unit-text">单位: {{ item.unit || '个' }}</div>
                    <div class="stock-info" v-if="item.available_stock !== undefined">
                      可用库存: {{ item.available_stock }} {{ item.unit || '个' }}
                    </div>
                  </div>
                </template>
                <template #default>
                  <div class="item-details">
                    <div class="price-quantity">
                      <!-- 价格输入框 -->
                      <div class="input-field price-field">
                        <van-field 
                          v-model.number="item.price" 
                          type="number" 
                          placeholder="0.00" 
                          class="editable-field compact-field"
                          @blur="validatePrice(item)" 
                          :error-message="item.priceError"
                        >
                          <template #extra>元</template>
                        </van-field>
                      </div>
                      <!-- 数量输入框 -->
                      <div class="input-field quantity-field">
                        <van-field 
                          v-model.number="item.quantity" 
                          type="number" 
                          placeholder="0" 
                          class="editable-field compact-field"
                          @blur="validateQuantity(item)" 
                          :error-message="item.quantityError"
                        >
                          <template #extra>{{ item.unit || '个' }}</template>
                        </van-field>
                      </div>
                    </div>
                    <div class="item-total">
                      <div class="total-amount">¥{{ ((item.price || 0) * (item.quantity || 0)).toFixed(2) }}</div>
                      <div class="stock-warning" v-if="showStockWarning(item)">
                        <van-icon name="warning" color="#f53f3f" size="12" />
                        <span>库存不足</span>
                      </div>
                    </div>
                  </div>
                </template>
              </van-cell>
              <template #right>
                <van-button square type="danger" text="删除" class="delete-btn" @click="deleteSku(index)" />
              </template>
            </van-swipe-cell>
          </van-cell-group>
        </div>
        
        <!-- 合计金额 -->
        <div class="total-section" v-if="form.items.length > 0">
          <div class="total-line">
            <span>商品总金额：</span>
            <span class="amount">¥{{ totalAmount.toFixed(2) }}</span>
          </div>
          <div class="total-line" v-if="discountAmount > 0">
            <span>优惠金额：</span>
            <span class="amount discount">-¥{{ discountAmount.toFixed(2) }}</span>
          </div>
          <div class="total-line">
            <span>订单总金额：</span>
            <span class="amount total">¥{{ (totalAmount - discountAmount).toFixed(2) }}</span>
          </div>
        </div>
      </van-form>
    </div>

    <!-- 客户选择器组件 -->
    <CustomerSelect
      ref="customerSelectRef"
      v-model="form.customer_id"
      :popup-title="'选择客户'"
      :search-placeholder="'搜索客户'"
      :only-enabled="true"
      :show-arrears="true"
      :hide-trigger="true"
      @change="onCustomerSelect"
      @confirm="onCustomerConfirm"
    />
    
    <!-- 仓库选择器组件 -->
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
        title="选择预计发货日期" 
        :min-date="minDate" 
        :max-date="maxDate"
        @confirm="onDateConfirm" 
        @cancel="closeDatePicker" 
      />
    </van-popup>
    
    <!-- SKU选择弹窗 -->
    <van-popup v-model:show="showSkuSelect" position="bottom" :style="{ height: '80%' }" :close-on-click-overlay="true">
      <div class="sku-select-popup">
        <div class="popup-header">
          <van-button type="default" size="small" @click="closeSkuPicker">取消</van-button>
          <div class="popup-title">选择销售商品</div>
          <van-button type="primary" size="small" @click="confirmSkuSelection">确定</van-button>
        </div>
        <div class="popup-content">
          <SkuStockList 
            ref="skuStockListRef" 
            :warehouse-id="form.warehouse_id"
            :selectable="true"
            :selected-ids="tempSelectedIds"
            :show-filters="true"
            :enable-category-filter="true"
            :enable-brand-filter="true"
            @click-card="handleSkuCardClick"
          />
        </div>
      </div>
    </van-popup>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted, watch, nextTick } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { showToast, showConfirmDialog, showSuccessToast, showFailToast } from 'vant'
import { useSaleStore } from '@/store/modules/sale'
import CustomerSelect from '@/components/business/CustomerSelect.vue'
import WarehouseSelect from '@/components/business/WarehouseSelect.vue'
import SkuStockList from '@/components/business/SkuStockList.vue'

// 路由相关
const route = useRoute()
const router = useRouter()
const isEdit = !!route.params.id

// 状态管理
const saleStore = useSaleStore()

// 组件引用
const formRef = ref(null)
const customerSelectRef = ref(null)
const warehouseSelectRef = ref(null)
const skuStockListRef = ref(null)
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
  customer_id: '',
  customer_name: '',
  warehouse_id: '',
  warehouse_name: '',
  expected_date: '',
  remark: '',
  discount_amount: 0,
  items: [] // 存储选中的SKU明细
})

// 选择器状态
const showDatePicker = ref(false)
const showSkuSelect = ref(false)

// SKU选择相关
const selectedSkuIds = ref([])
const tempSelectedIds = ref([]) // 临时选择，用于弹窗中的选择状态
const selectedSkus = ref([]) // 存储选中的完整SKU数据

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

// 优惠金额
const discountAmount = computed(() => {
  return Number(form.discount_amount) || 0
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

// 检查库存警告
const showStockWarning = (item) => {
  if (item.available_stock === undefined || item.available_stock === null) {
    return false
  }
  return Number(item.quantity) > Number(item.available_stock)
}

// 初始化表单数据
const initForm = async () => {
  if (isEdit) {
    // 编辑模式：加载订单详情
    const id = route.params.id
    try {
      const orderDetail = await saleStore.loadOrderDetail(id)

      
      if (orderDetail) {
        form.customer_id = orderDetail.customer_id
        form.customer_name = orderDetail.customer?.name || ''
        form.warehouse_id = orderDetail.warehouse_id
        form.warehouse_name = orderDetail.warehouse?.name || ''
        form.expected_date = orderDetail.expected_date
        form.remark = orderDetail.remark || ''
        form.discount_amount = orderDetail.discount_amount || 0
        
        // 处理订单项数据
        form.items = (orderDetail.items || []).map(item => {
          const product = item.product || {}

          
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
            available_stock: item.available_stock || 0,
            priceError: '',
            quantityError: ''
          }
        })
        
        // 设置选中的SKU ID
        selectedSkuIds.value = form.items.map(item => item.sku_id).filter(Boolean)

        
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
      console.error('加载销售订单详情失败:', error)
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

// 客户选择器事件处理 - 使用 change 事件
const onCustomerSelect = (value, text) => {

  if (value) {
    form.customer_id = value
    form.customer_name = text || ''

  } else {
    form.customer_id = ''
    form.customer_name = ''

  }
}

// 客户确认事件
const onCustomerConfirm = (id, customer) => {

  if (id) {
    form.customer_id = id
    form.customer_name = customer?.name || ''
  }
}

// 仓库选择器事件处理
const onWarehouseSelectChange = (id, name) => {

  if (id) {
    form.warehouse_id = id
    form.warehouse_name = name

  } else {
    form.warehouse_id = ''
    form.warehouse_name = ''

  }
}

// 仓库确认事件
const onWarehouseConfirm = (id, warehouse) => {

  if (id) {
    form.warehouse_id = id
    form.warehouse_name = warehouse?.name || ''
  }
}

// 手动触发客户选择器打开
const openCustomerSelect = () => {
  customerSelectRef.value?.openPicker?.()
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

// 打开SKU选择器
const openSkuSelector = () => {
  // 检查是否已选择仓库
  if (!form.warehouse_id) {
    showToast('请先选择仓库')
    return
  }
  
  // 初始化临时选择的ID为当前已选择的SKU
  tempSelectedIds.value = [...selectedSkuIds.value]

  
  // 清空selectedSkus，重新开始选择
  selectedSkus.value = []
  
  // 打开弹窗
  showSkuSelect.value = true
  
  // SkuStockList组件会通过watch监听器自动监听warehouseId变化并刷新数据
  // 不需要手动调用onRefresh，避免重复请求
}

// 处理SKU卡片点击
const handleSkuCardClick = (sku) => {
  const skuId = sku.id
  const index = tempSelectedIds.value.indexOf(skuId)
  
  if (index > -1) {
    // 已选择，则取消选择
    tempSelectedIds.value.splice(index, 1)
    selectedSkus.value = selectedSkus.value.filter(item => item.id !== skuId)
  } else {
    // 未选择，则添加选择
    tempSelectedIds.value.push(skuId)
    selectedSkus.value.push(sku)
  }
}

// 确认SKU选择
const confirmSkuSelection = async () => {
  try {
    // 使用selectedSkus中存储的完整SKU数据
    const selectedData = [...selectedSkus.value]
    

    
    if (selectedData.length === 0) {
      showToast('未选择任何商品')
      return
    }

    // 获取SKU详细信息并添加到表单
    for (const sku of selectedData) {
      if (!sku) continue

      try {
        // 检查是否已存在相同SKU
        const existingIndex = form.items.findIndex(item => item.sku_id === sku.id)
        if (existingIndex > -1) {
          // 已存在，更新数量（+1）
          form.items[existingIndex].quantity = (Number(form.items[existingIndex].quantity) || 0) + 1
        } else {
          // 新增SKU
          const newItem = {
            sku_id: sku.id,
            product_id: sku.product_id || sku.sku?.product_id,
            sku_code: sku.sku_code || sku.sku?.sku_code || '',
            product: sku.product || sku.sku?.product || null,
            product_name: getSkuProductName(sku),
            sku_name: sku.name || sku.sku?.name || '',
            spec_text: sku.spec_text || getSpecText(sku),
            spec: sku.spec || sku.sku?.spec || {},
            unit: sku.unit || '个',
            price: sku.sale_price || 0, // 销售订单使用销售价
            quantity: 1,
            available_stock: sku.quantity || 0,
            priceError: '',
            quantityError: ''
          }
          form.items.push(newItem)
        }
      } catch (error) {
        console.error('添加SKU失败:', error)
      }
    }

    // 更新已选择的SKU ID
    selectedSkuIds.value = [...tempSelectedIds.value]
    showSkuSelect.value = false
    showSuccessToast(`已添加 ${selectedData.length} 个商品`)
  } catch (error) {
    console.error('确认SKU选择失败:', error)
    showFailToast('添加商品失败')
  }
}

// 获取规格文本
const getSpecText = (sku) => {
  const spec = sku.spec || sku.sku?.spec
  if (!spec) return '无规格'
  
  if (typeof spec === 'string') {
    try {
      const parsedSpec = JSON.parse(spec)
      return Object.values(parsedSpec).join(' / ')
    } catch {
      return spec
    }
  }
  
  if (typeof spec === 'object') {
    return Object.values(spec).join(' / ')
  }
  
  return '无规格'
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
    const price = Number(item.price)
    if (isNaN(price) || price <= 0) {
      item.priceError = '单价必须大于0'
      return false
    }
    item.priceError = ''
    return true
  }, 0)
}

// 验证数量
const validateQuantity = (item) => {
  setTimeout(() => {
    const quantity = Number(item.quantity)
    if (isNaN(quantity) || quantity <= 0) {
      item.quantityError = '数量必须大于0'
      return false
    }
    
    // 检查库存
    if (item.available_stock !== undefined && quantity > item.available_stock) {
      item.quantityError = `库存不足，可用: ${item.available_stock}`
      return false
    }
    
    item.quantityError = ''
    return true
  }, 0)
}

// 验证表单
const validateForm = () => {
  if (!form.customer_id) {
    showToast('请选择客户')
    return false
  }
  if (!form.warehouse_id) {
    showToast('请选择出库仓库')
    return false
  }
  if (!form.expected_date) {
    showToast('请选择预计发货日期')
    return false
  }
  if (form.items.length === 0) {
    showToast('请至少添加一个销售商品')
    return false
  }
  // 验证每个SKU的价格和数量
  for (const item of form.items) {
    const price = Number(item.price)
    const quantity = Number(item.quantity)
    if (isNaN(price) || price <= 0) {
      showToast(`请检查商品"${getProductDisplayName(item)}"的单价`)
      return false
    }
    if (isNaN(quantity) || quantity <= 0) {
      showToast(`请检查商品"${getProductDisplayName(item)}"的数量`)
      return false
    }
    // 检查库存
    if (item.available_stock !== undefined && quantity > item.available_stock) {
      showToast(`商品"${getProductDisplayName(item)}"库存不足，可用: ${item.available_stock}`)
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
      customer_id: form.customer_id,
      warehouse_id: form.warehouse_id,
      expected_date: form.expected_date,
      remark: form.remark,
      discount_amount: discountAmount.value,
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
          quantity: Number(item.quantity),
          price: Number(item.price)
        }
        
        // 编辑模式下，如果有订单项ID，则包含它
        if (isEdit && item.id) {
          itemData.id = item.id
        }
        
        return itemData
      })
    }



    let result
    if (isEdit) {
      // 编辑订单
      result = await saleStore.updateOrder(route.params.id, submitData)
      showSuccessToast('更新成功')
    } else {
      // 创建新订单
      result = await saleStore.addOrder(submitData)
      showSuccessToast('创建成功')
      if (result?.id) {
        // 创建成功后跳转到详情页
        router.push(`/sale/order/detail/${result.id}`)
        return
      }
    }
    // 操作成功后返回列表页
    router.push('/sale/order')
  } catch (error) {
    console.error('提交销售订单失败:', error)
    showFailToast(error.message || '操作失败')
  } finally {
    submitting.value = false
  }
}

// 返回上一页
const handleBack = () => {
  safeUpdate(() => {
    if (form.items.length > 0 || form.customer_id || form.warehouse_id || form.remark) {
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

// 监听仓库变化，更新商品库存信息
watch(() => form.warehouse_id, (newWarehouseId) => {
  if (newWarehouseId && form.items.length > 0) {
    // 当仓库变化时，可以重新获取商品的库存信息
    // 这里可以添加重新获取库存的逻辑

  }
})

onMounted(async () => {
  await initForm()
})
</script>

<style scoped lang="scss">
.sale-order-form {
  background-color: #f7f8fa;
  min-height: 100vh;
  padding-top: 46px; // 为固定导航栏留出空间
}

.form-container {
  padding: 16px;
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
    margin-bottom: 1px;
    
    &:last-child {
      margin-bottom: 0;
    }
  }
  
  .sku-cell {
    padding: 10px 16px;
    align-items: flex-start;
    
    &:after {
      border-bottom: 1px solid #f5f5f5;
    }
  }
}

.product-title {
  display: flex;
  align-items: center;
  gap: 8px;
  margin-bottom: 4px;
  flex-wrap: wrap;
}

.product-name {
  font-weight: bold;
  color: #323233;
  font-size: 14px;
  line-height: 1.4;
}

.sku-code {
  color: #646566;
  font-size: 12px;
  font-weight: normal;
  background: #f5f5f5;
  padding: 1px 4px;
  border-radius: 3px;
}

.product-label {
  font-size: 12px;
  color: #969799;
  
  .spec-text {
    margin-bottom: 2px;
    color: #646566;
    line-height: 1.3;
  }
  
  .unit-text {
    color: #969799;
    line-height: 1.3;
  }
  
  .stock-info {
    color: #1890ff;
    line-height: 1.3;
    margin-top: 2px;
  }
}

.item-details {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  width: 100%;
  gap: 8px;
}

.price-quantity {
  display: flex;
  gap: 8px;
  align-items: flex-start;
  
  .input-field {
    display: flex;
    flex-direction: column;
    
    .editable-field {
      border: 1px solid #e0e0e0;
      border-radius: 4px;
      background: #fff;
      transition: all 0.2s;
      height: 32px;
      
      &:deep(.van-field__body) {
        min-height: auto;
      }
      
      &:deep(.van-field__control) {
        font-size: 13px;
        font-weight: 500;
        color: #323233;
        text-align: center;
        padding: 0 4px;
      }
      
      &:deep(.van-field__extra) {
        color: #969799;
        font-size: 11px;
        padding-left: 2px;
      }
      
      &:focus-within {
        border-color: #1989fa;
        box-shadow: 0 0 0 2px rgba(25, 137, 250, 0.1);
      }
      
      // 紧凑字段样式
      &.compact-field {
        width: 80px;
        
        &:deep(.van-field__control) {
          font-size: 12px;
        }
      }
    }
    
    &.price-field {
      .editable-field {
        width: 85px;
      }
    }
    
    &.quantity-field {
      .editable-field {
        width: 85px;
      }
    }
  }
}

.item-total {
  display: flex;
  flex-direction: column;
  align-items: flex-end;
  min-width: 70px;
  
  .total-amount {
    color: #f53f3f;
    font-weight: bold;
    font-size: 13px;
    line-height: 1.3;
  }
  
  .stock-warning {
    display: flex;
    align-items: center;
    gap: 2px;
    color: #f53f3f;
    font-size: 10px;
    margin-top: 2px;
    
    .van-icon {
      margin-right: 1px;
    }
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

// SKU选择弹窗样式
.sku-select-popup {
  display: flex;
  flex-direction: column;
  height: 100%;
  
  .popup-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 12px 16px;
    border-bottom: 1px solid #ebedf0;
    background-color: #fff;
    
    .popup-title {
      font-size: 16px;
      font-weight: 500;
      color: #323233;
    }
  }
  
  .popup-content {
    flex: 1;
    overflow: hidden;
    background-color: #f7f8fa;
  }
}
</style>
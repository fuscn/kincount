<template>
  <div class="sale-outbound-create-page">
    <van-nav-bar 
      title="销售出库"
      left-text="取消"
      right-text="保存"
      left-arrow
      @click-left="handleCancel"
      @click-right="handleSubmit"
    />

    <van-form ref="formRef" @submit="handleSubmit" class="form-container">
      <!-- 出库信息 -->
      <van-cell-group title="出库信息">
        <van-field
          v-model="form.outbound_no"
          label="出库单号"
          placeholder="系统自动生成"
          readonly
        />
        <van-field
          v-model="form.order_no"
          label="销售订单"
          placeholder="请选择销售订单"
          readonly
          is-link
          @click="showOrderPicker = true"
        />
        <van-field
          v-model="form.customer_name"
          label="客户"
          placeholder="自动关联客户"
          readonly
        />
        <van-field
          v-model="form.warehouse_name"
          label="出库仓库"
          placeholder="请选择仓库"
          readonly
          is-link
          required
          :rules="[{ required: true, message: '请选择仓库' }]"
          @click="showWarehousePicker = true"
        />
        <van-field
          v-model="form.outbound_date"
          label="出库日期"
          placeholder="请选择出库日期"
          readonly
          is-link
          required
          :rules="[{ required: true, message: '请选择出库日期' }]"
          @click="showDatePicker = true"
        />
      </van-cell-group>

      <!-- 出库说明 -->
      <van-cell-group title="出库说明">
        <van-field
          v-model="form.remark"
          label="备注说明"
          type="textarea"
          placeholder="请输入出库说明（可选）"
          rows="2"
          autosize
          maxlength="200"
          show-word-limit
        />
      </van-cell-group>

      <!-- 出库商品 -->
      <van-cell-group title="出库商品">
        <div class="product-list">
          <div class="product-item" v-for="(item, index) in form.items" :key="index">
            <div class="product-header">
              <span class="product-name">{{ item.product_name }}</span>
              <span class="order-quantity">订单数量: {{ item.order_quantity }}{{ item.unit }}</span>
            </div>
            <div class="product-info">
              <span>编号: {{ item.product_no }}</span>
              <span>规格: {{ item.spec || '无' }}</span>
              <span>已出库: {{ item.delivered_quantity || 0 }}{{ item.unit }}</span>
              <span>单价: ¥{{ item.price }}</span>
            </div>
            <div class="stock-info">
              <span :class="getStockStatusClass(item)">当前库存: {{ item.current_stock || 0 }}{{ item.unit }}</span>
              <span class="available-quantity">可出库: {{ item.available_quantity }}{{ item.unit }}</span>
            </div>
            <div class="outbound-fields">
              <van-field
                v-model.number="item.quantity"
                label="本次出库"
                type="digit"
                placeholder="请输入出库数量"
                required
                :rules="[
                  { required: true, message: '请输入出库数量' },
                  { validator: (value) => validateQuantity(value, item), message: '出库数量不能超过可出库数量' }
                ]"
                @blur="onQuantityBlur(item)"
              >
                <template #extra>{{ item.unit }}</template>
              </van-field>
              <div class="validation-info" v-if="showValidationError(item)">
                <span class="error-text">可出库数量: {{ item.available_quantity }}{{ item.unit }}</span>
              </div>
              <div class="stock-warning" v-if="showStockWarning(item)">
                <van-icon name="warning" color="#ff976a" />
                <span class="warning-text">库存不足，请调整出库数量</span>
              </div>
            </div>
          </div>
        </div>

        <!-- 空状态 -->
        <van-empty
          v-if="form.items.length === 0"
          description="请先选择销售订单"
          image="search"
        />
      </van-cell-group>

      <!-- 订单汇总 -->
      <van-cell-group title="出库汇总" v-if="form.items.length > 0">
        <van-cell title="出库商品数量" :value="totalOutboundQuantity" />
        <van-cell title="出库商品种类" :value="form.items.length" />
        <van-cell title="出库总金额" :value="`¥${totalAmount}`" />
      </van-cell-group>
    </van-form>

    <!-- 销售订单选择器 -->
    <van-popup 
      v-model:show="showOrderPicker" 
      position="bottom" 
      :style="{ height: '70%' }"
    >
      <div class="order-picker">
        <van-nav-bar
          title="选择销售订单"
          left-text="取消"
          right-text="确定"
          @click-left="showOrderPicker = false"
          @click-right="onOrderConfirm"
        />
        <van-search
          v-model="orderSearch"
          placeholder="搜索订单号/客户名称"
          @search="searchOrders"
        />
        <van-radio-group v-model="selectedOrder">
          <van-cell-group>
            <van-cell
              v-for="order in orderList"
              :key="order.id"
              clickable
              @click="selectedOrder = order.id"
            >
              <template #title>
                <div class="order-title">
                  <van-radio :name="order.id" />
                  <span class="order-no">{{ order.order_no }}</span>
                </div>
              </template>
              <template #label>
                <div class="order-label">
                  <span>客户: {{ order.customer?.name || '未知客户' }}</span>
                  <span>金额: ¥{{ order.total_amount }}</span>
                  <span>日期: {{ order.created_at ? formatDate(order.created_at) : '' }}</span>
                  <span>状态: {{ getOrderStatusText(order.status) }}</span>
                </div>
              </template>
            </van-cell>
          </van-cell-group>
        </van-radio-group>
      </div>
    </van-popup>

    <!-- 仓库选择器 -->
    <van-popup v-model:show="showWarehousePicker" position="bottom">
      <van-picker
        :columns="warehouseOptions"
        @confirm="onWarehouseConfirm"
        @cancel="showWarehousePicker = false"
      />
    </van-popup>

    <!-- 日期选择器 -->
    <van-popup v-model:show="showDatePicker" position="bottom">
      <van-date-picker
        :min-date="minDate"
        :max-date="maxDate"
        @confirm="onDateConfirm"
        @cancel="showDatePicker = false"
      />
    </van-popup>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { 
  showToast,
  showConfirmDialog
} from 'vant'
import { generateNumber } from '@/api/utils'
import { getWarehouseOptions } from '@/api/warehouse'
import { getSaleOrderList, getSaleOrderDetail, addSaleStock } from '@/api/sale'
import { getStockList } from '@/api/stock' // 新增：库存查询API
import dayjs from 'dayjs'

const route = useRoute()
const router = useRouter()
const formRef = ref()

// 表单数据
const form = reactive({
  outbound_no: '',
  sale_order_id: '',
  order_no: '',
  customer_id: '',
  customer_name: '',
  warehouse_id: '',
  warehouse_name: '',
  outbound_date: dayjs().format('YYYY-MM-DD'),
  remark: '',
  items: []
})

// 选择器状态
const showOrderPicker = ref(false)
const showWarehousePicker = ref(false)
const showDatePicker = ref(false)

// 选项数据
const warehouseOptions = ref([])
const orderSearch = ref('')
const orderList = ref([])
const selectedOrder = ref('')

// 日期范围
const minDate = new Date(2020, 0, 1)
const maxDate = new Date(2030, 11, 31)

// 计算属性
const totalOutboundQuantity = computed(() => {
  return form.items.reduce((sum, item) => sum + (Number(item.quantity) || 0), 0)
})

const totalAmount = computed(() => {
  return form.items.reduce((sum, item) => {
    const quantity = Number(item.quantity) || 0
    const price = Number(item.price) || 0
    return sum + (quantity * price)
  }, 0).toFixed(2)
})

// 计算可出库数量 - 修复版本：考虑实时库存
const calculateAvailableQuantity = (item) => {
  const orderQty = Number(item.order_quantity) || 0
  const deliveredQty = Number(item.delivered_quantity) || 0
  const currentStock = Number(item.current_stock) || 0
  
  // 可出库数量 = min(订单未出库数量, 当前库存)
  const orderAvailable = orderQty - deliveredQty
  return Math.min(orderAvailable, currentStock)
}

// 加载仓库选项
const loadWarehouseOptions = async () => {
  try {
    const warehouses = await getWarehouseOptions()
    warehouseOptions.value = (warehouses?.data || warehouses || []).map(item => ({
      text: item.name,
      value: item.id
    }))
  } catch (error) {
    console.error('加载仓库列表失败:', error)
    showToast('加载仓库列表失败')
  }
}

// 加载销售订单列表
const loadOrderList = async () => {
  try {
    const params = {
      page: 1,
      limit: 50,
      keyword: orderSearch.value,
      status: 2 // 只显示已审核的订单
    }
    const response = await getSaleOrderList(params)
    
    let orders = []
    if (response?.code === 200 && response.data) {
      orders = response.data.list || response.data || []
    } else {
      orders = response?.list || response || []
    }
    
    // 过滤出有未出库商品的订单
    orderList.value = orders.filter(order => {
      // 如果订单有items，检查是否有未出库的商品
      if (order.items && Array.isArray(order.items)) {
        return order.items.some(item => {
          const orderQty = Number(item.quantity) || 0
          const deliveredQty = Number(item.delivered_quantity) || 0
          return orderQty > deliveredQty
        })
      }
      return true // 如果没有items信息，默认显示
    })
  } catch (error) {
    console.error('加载销售订单失败:', error)
    showToast('加载销售订单失败')
  }
}

// 查询商品库存 - 新增函数
const loadProductStocks = async (productIds, warehouseId) => {
  try {
    if (!warehouseId || !productIds.length) {
      return {}
    }
    
    const params = {
      warehouse_id: warehouseId,
      product_ids: productIds.join(','),
      page: 1,
      limit: 100
    }
    
    const response = await getStockList(params)
    console.log('库存查询响应:', response)
    
    let stockData = {}
    if (response?.code === 200 && response.data) {
      const stockList = response.data.list || response.data || []
      stockList.forEach(item => {
        stockData[item.product_id] = item.quantity || 0
      })
    } else if (response?.list) {
      response.list.forEach(item => {
        stockData[item.product_id] = item.quantity || 0
      })
    }
    
    return stockData
  } catch (error) {
    console.error('查询商品库存失败:', error)
    return {}
  }
}

// 生成出库单号
const generateOutboundNo = async () => {
  try {
    const result = await generateNumber('sale_outbound')
    if (typeof result === 'string') {
      form.outbound_no = result
    } else if (result?.data) {
      form.outbound_no = result.data
    } else if (result?.number) {
      form.outbound_no = result.number
    } else if (result?.outbound_no) {
      form.outbound_no = result.outbound_no
    } else {
      form.outbound_no = `SOUT${dayjs().format('YYYYMMDDHHmmss')}`
    }
  } catch (error) {
    form.outbound_no = `SOUT${dayjs().format('YYYYMMDDHHmmss')}`
  }
}

// 验证出库数量 - 修复版本
const validateQuantity = (value, item) => {
  const quantity = Number(value) || 0
  const available = Number(item.available_quantity) || 0
  // 允许数量在 1 到 available 之间（包含 available）
  return quantity > 0 && quantity <= available
}

// 数量输入框失去焦点时的处理 - 修复版本
const onQuantityBlur = (item) => {
  const quantity = Number(item.quantity) || 0
  const available = Number(item.available_quantity) || 0
  
  console.log('失焦检查:', { quantity, available })
  
  // 如果输入为空、0或负数，让required规则处理，不清空输入
  if (!item.quantity || quantity <= 0) {
    return
  }
  
  // 只有当数量严格大于可出库数量时才调整
  if (quantity > available) {
    console.log('数量超出，自动调整')
    showToast(`出库数量不能超过可出库数量 ${available}${item.unit}`)
    // 自动调整为最大可出库数量
    item.quantity = available > 0 ? available.toString() : ''
  } else {
    console.log('数量合法，无需调整')
    // 数量合法，不做任何处理
  }
}

// 显示验证错误 - 修复版本
const showValidationError = (item) => {
  const quantity = Number(item.quantity) || 0
  const available = Number(item.available_quantity) || 0
  // 只有当数量严格大于可出库数量时才显示错误
  return item.quantity && quantity > available
}

// 显示库存警告 - 新增函数
const showStockWarning = (item) => {
  const currentStock = Number(item.current_stock) || 0
  const orderAvailable = Number(item.order_quantity) - Number(item.delivered_quantity || 0)
  // 如果订单可出库数量大于当前库存，显示警告
  return orderAvailable > currentStock
}

// 获取库存状态样式 - 新增函数
const getStockStatusClass = (item) => {
  const currentStock = Number(item.current_stock) || 0
  const orderAvailable = Number(item.order_quantity) - Number(item.delivered_quantity || 0)
  
  if (currentStock === 0) {
    return 'stock-danger'
  } else if (currentStock < orderAvailable) {
    return 'stock-warning'
  } else {
    return 'stock-normal'
  }
}

// 格式化日期
const formatDate = (dateString) => {
  if (!dateString) return ''
  return dayjs(dateString).format('YYYY-MM-DD')
}

// 获取订单状态文本
const getOrderStatusText = (status) => {
  const statusMap = {
    1: '待审核',
    2: '已审核',
    3: '部分出库',
    4: '已完成',
    5: '已取消'
  }
  return statusMap[status] || '未知状态'
}

// 处理订单详情数据 - 修复版本：添加库存检查
const processOrderDetail = async (order) => {
  form.sale_order_id = order.id
  form.order_no = order.order_no
  form.customer_id = order.customer_id
  form.customer_name = order.customer?.name || ''
  
  // 默认使用订单的仓库
  if (order.warehouse) {
    form.warehouse_id = order.warehouse.id
    form.warehouse_name = order.warehouse.name
  }
  
  // 设置出库商品 - 只显示未完全出库的商品
  const availableItems = (order.items || []).filter(item => {
    const orderQty = Number(item.quantity) || 0
    const deliveredQty = Number(item.delivered_quantity) || 0
    return orderQty > deliveredQty
  })
  
  // 转换商品数据结构，适配后端返回的嵌套格式
  const processedItems = availableItems.map(item => {
    const product = item.product || {}
    const orderAvailable = item.quantity - (item.delivered_quantity || 0)
    
    return {
      product_id: item.product_id,
      product_name: product.name || '',
      product_no: product.product_no || '',
      spec: product.spec || '',
      unit: product.unit || '',
      order_quantity: item.quantity,
      delivered_quantity: item.delivered_quantity || 0,
      quantity: '',
      current_stock: 0, // 初始化为0，后面会更新
      available_quantity: orderAvailable, // 初始化为订单可出库数量
      price: item.price || 0
    }
  })
  
  form.items = processedItems
  
  // 如果有仓库信息，查询商品库存
  if (form.warehouse_id && form.items.length > 0) {
    const productIds = form.items.map(item => item.product_id)
    const stockData = await loadProductStocks(productIds, form.warehouse_id)
    
    // 更新商品库存信息
    form.items.forEach(item => {
      item.current_stock = stockData[item.product_id] || 0
      // 重新计算可出库数量（考虑库存限制）
      item.available_quantity = calculateAvailableQuantity(item)
    })
    
    // 检查是否有库存不足的商品
    const outOfStockItems = form.items.filter(item => item.available_quantity === 0)
    if (outOfStockItems.length > 0) {
      const itemNames = outOfStockItems.map(item => item.product_name).join('、')
      showToast(`${itemNames} 库存不足，无法出库`)
    }
  }
  
  if (form.items.length === 0) {
    showToast('该订单没有可出库的商品')
  } else {
    console.log('处理后的商品数据:', form.items)
  }
}

// 仓库变更时重新查询库存 - 新增函数
const onWarehouseChange = async () => {
  if (form.warehouse_id && form.items.length > 0) {
    const productIds = form.items.map(item => item.product_id)
    const stockData = await loadProductStocks(productIds, form.warehouse_id)
    
    // 更新商品库存信息
    form.items.forEach(item => {
      item.current_stock = stockData[item.product_id] || 0
      // 重新计算可出库数量（考虑库存限制）
      item.available_quantity = calculateAvailableQuantity(item)
      
      // 如果当前输入的数量超过新的可出库数量，自动调整
      const currentQuantity = Number(item.quantity) || 0
      if (currentQuantity > item.available_quantity) {
        item.quantity = item.available_quantity > 0 ? item.available_quantity.toString() : ''
      }
    })
    
    // 检查库存状态
    const outOfStockItems = form.items.filter(item => item.available_quantity === 0)
    if (outOfStockItems.length > 0) {
      const itemNames = outOfStockItems.map(item => item.product_name).join('、')
      showToast(`仓库变更后，${itemNames} 库存不足`)
    }
  }
}

// 事件处理
const handleCancel = () => {
  showConfirmDialog({
    title: '确认取消',
    message: '确定要取消销售出库吗？所有未保存的数据将会丢失。'
  }).then(() => {
    router.back()
  }).catch(() => {
    // 取消操作
  })
}

const handleSubmit = async () => {
  try {
    // 手动验证所有字段
    for (const item of form.items) {
      if (!item.quantity || item.quantity === '') {
        showToast(`请填写商品"${item.product_name}"的出库数量`)
        return
      }
      
      const quantity = Number(item.quantity)
      if (quantity <= 0) {
        showToast(`商品"${item.product_name}"的出库数量必须大于0`)
        return
      }
      
      if (!validateQuantity(item.quantity, item)) {
        showToast(`商品"${item.product_name}"的出库数量不能超过可出库数量 ${item.available_quantity}${item.unit}`)
        return
      }
      
      // 最终库存检查 - 新增
      if (quantity > item.current_stock) {
        showToast(`商品"${item.product_name}"库存不足，当前库存 ${item.current_stock}${item.unit}`)
        return
      }
    }

    // 构建提交数据 - 根据后端要求调整字段名
    const submitData = {
      sale_order_id: Number(form.sale_order_id),
      customer_id: Number(form.customer_id),
      warehouse_id: Number(form.warehouse_id),
      outbound_date: form.outbound_date,
      remark: form.remark || '',
      items: form.items.map(item => ({
        product_id: Number(item.product_id),
        quantity: Number(item.quantity),
        price: Number(item.price)
      }))
    }

    console.log('提交的出库数据:', submitData)

    const result = await addSaleStock(submitData)
    showToast('出库单创建成功')
    
    if (result.id) {
      router.push(`/sale/stock/detail/${result.id}`)
    } else {
      router.back()
    }
  } catch (error) {
    console.error('保存失败，完整错误信息:', error)
    
    let errorMessage = '保存失败'
    
    if (Array.isArray(error)) {
      const firstError = error[0]
      if (firstError && firstError.message) {
        errorMessage = firstError.message
      }
    } else if (error.message) {
      errorMessage = error.message
    } else if (error.msg) {
      errorMessage = error.msg
    } else if (typeof error === 'string') {
      errorMessage = error
    }
    
    console.log('提取的错误消息:', errorMessage)
    showToast(errorMessage)
  }
}

const onOrderConfirm = async () => {
  if (!selectedOrder.value) {
    showToast('请选择销售订单')
    return
  }

  try {
    // 加载订单详情
    const orderDetail = await getSaleOrderDetail(selectedOrder.value)
    console.log('订单详情响应:', orderDetail)
    
    const order = orderDetail?.data || orderDetail
    
    if (!order) {
      showToast('订单数据为空')
      return
    }
    
    await processOrderDetail(order)
    
    showOrderPicker.value = false
    selectedOrder.value = ''
    orderSearch.value = ''
    
  } catch (error) {
    console.error('加载订单详情失败:', error)
    showToast('加载订单详情失败')
  }
}

const onWarehouseConfirm = (value) => {
  form.warehouse_id = value.selectedOptions[0].value
  form.warehouse_name = value.selectedOptions[0].text
  showWarehousePicker.value = false
  
  // 仓库变更时重新查询库存
  onWarehouseChange()
}

const onDateConfirm = (value) => {
  form.outbound_date = dayjs(value.selectedValues.join('-')).format('YYYY-MM-DD')
  showDatePicker.value = false
}

const searchOrders = () => {
  loadOrderList()
}

onMounted(() => {
  generateOutboundNo()
  loadWarehouseOptions()
  loadOrderList()
  
  // 如果有订单ID参数，直接加载该订单
  const orderId = route.query.order_id
  if (orderId) {
    selectedOrder.value = orderId
    onOrderConfirm()
  }
})
</script>

<style scoped lang="scss">
.sale-outbound-create-page {
  background: #f7f8fa;
  min-height: 100vh;
}

.form-container {
  padding: 16px;
}

.product-list {
  .product-item {
    background: white;
    border-radius: 8px;
    padding: 12px;
    margin-bottom: 12px;
    
    .product-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 8px;
      
      .product-name {
        font-weight: 500;
        font-size: 14px;
      }
      
      .order-quantity {
        font-size: 12px;
        color: #969799;
      }
    }
    
    .product-info {
      display: flex;
      flex-wrap: wrap;
      gap: 8px;
      font-size: 12px;
      color: #969799;
      margin-bottom: 8px;
      
      span {
        display: inline-block;
      }
    }
    
    .stock-info {
      display: flex;
      justify-content: space-between;
      font-size: 12px;
      margin-bottom: 12px;
      padding: 4px 0;
      
      .stock-normal {
        color: #07c160;
      }
      
      .stock-warning {
        color: #ff976a;
      }
      
      .stock-danger {
        color: #ee0a24;
      }
      
      .available-quantity {
        color: #1989fa;
        font-weight: 500;
      }
    }
    
    .outbound-fields {
      .van-field {
        padding: 8px 0;
        
        :deep(.van-field__label) {
          width: 80px;
        }
      }
      
      .validation-info {
        margin-top: 4px;
        text-align: right;
        
        .error-text {
          font-size: 12px;
          color: #ee0a24;
        }
      }
      
      .stock-warning {
        display: flex;
        align-items: center;
        gap: 4px;
        margin-top: 4px;
        
        .warning-text {
          font-size: 12px;
          color: #ff976a;
        }
      }
    }
  }
}

.order-picker {
  height: 100%;
  display: flex;
  flex-direction: column;
  
  .van-search {
    flex-shrink: 0;
  }
  
  .van-cell-group {
    flex: 1;
    overflow-y: auto;
  }
  
  .order-title {
    display: flex;
    align-items: center;
    gap: 8px;
    
    .order-no {
      font-weight: 500;
    }
  }
  
  .order-label {
    display: flex;
    flex-direction: column;
    gap: 2px;
    font-size: 12px;
    color: #969799;
  }
}
</style>
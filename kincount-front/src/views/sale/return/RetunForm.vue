<!-- kincount/kincount-front/src/views/sale/return/ReturnForm.vue -->
<template>
  <div class="sale-return-form">
    <van-nav-bar 
      :title="isEdit ? '编辑销售退货' : '新建销售退货'" 
      fixed 
      placeholder 
      left-text="取消" 
      right-text="保存"
      @click-left="handleBack" 
      @click-right="handleSubmit" 
    />
    
    <div class="form-container">
      <!-- 退货单基本信息 -->
      <van-form ref="formRef" @submit="handleSubmit">
        <!-- 销售订单选择 -->
        <van-field 
          v-model="form.order_no" 
          name="order" 
          label="销售订单" 
          placeholder="请选择销售订单" 
          is-link 
          readonly
          @click="showOrderPicker = true" 
          :rules="[{ required: true, message: '请选择销售订单' }]" 
        />
        
        <!-- 退货仓库选择 -->
        <van-field 
          v-model="form.warehouse_name" 
          name="warehouse" 
          label="退货仓库" 
          placeholder="请选择仓库" 
          is-link 
          readonly
          @click="showWarehousePicker = true" 
          :rules="[{ required: true, message: '请选择退货仓库' }]" 
        />
        
        <!-- 退货日期 -->
        <van-field 
          v-model="form.return_date" 
          name="return_date" 
          label="退货日期" 
          placeholder="请选择日期" 
          is-link 
          readonly
          @click="showReturnDatePicker = true" 
          :rules="[{ required: true, message: '请选择退货日期' }]" 
        />
        
        <!-- 退货原因选择字段 -->
        <van-field 
          v-model="form.return_type_text" 
          name="return_type" 
          label="退货原因" 
          placeholder="请选择退货原因" 
          is-link 
          readonly
          @click="showReasonPicker = true" 
          :rules="[{ required: true, message: '请选择退货原因' }]" 
        />
        
        <!-- 备注 -->
        <van-field 
          v-model="form.remark" 
          name="remark" 
          label="备注" 
          type="textarea" 
          placeholder="请输入备注信息" 
          rows="3" 
          maxlength="200"
          show-word-limit
        />
        
        <!-- 退货商品明细区域 -->
        <div class="sku-section">
          <div class="section-title">
            <span>退货商品明细</span>
            <van-button 
              size="small" 
              type="primary" 
              @click="handleAddSku" 
              icon="plus"
              :disabled="!form.order_id"
            >
              添加商品
            </van-button>
          </div>
          
          <!-- 商品列表 -->
          <van-empty v-if="form.items.length === 0" description="请添加退货商品" />
          <div v-else class="sku-list">
            <van-swipe-cell 
              v-for="(item, index) in form.items" 
              :key="getSkuKey(item, index)" 
              class="sku-item"
            >
              <van-cell class="sku-cell">
                <template #title>
                  <div class="product-title">
                    <span class="product-name">{{ item.product_name }}</span>
                    <span class="sku-code">{{ item.product_no }}</span>
                  </div>
                </template>
                <template #label>
                  <div class="product-label">
                    <div class="spec-text" v-if="item.spec">{{ item.spec }}</div>
                    <div class="stock-text">
                      原销售: {{ item.sale_quantity }}{{ item.unit }}
                      <span v-if="isOutOfStock(item)" class="out-of-stock">(超出可退数量)</span>
                    </div>
                  </div>
                </template>
                <template #default>
                  <div class="item-details">
                    <div class="price-quantity">
                      <div class="input-field price-field">
                        <van-field 
                          v-model.number="item.unit_price" 
                          type="number" 
                          placeholder="0.00" 
                          class="editable-field compact-field"
                          @blur="validatePrice(item)" 
                          :error-message="item.priceError"
                          :validate-event="false"
                        >
                          <template #extra>元</template>
                        </van-field>
                      </div>
                      <div class="input-field quantity-field">
                        <van-field 
                          v-model.number="item.return_quantity" 
                          type="number" 
                          placeholder="0" 
                          class="editable-field compact-field"
                          @blur="validateQuantity(item)" 
                          @input="updateItemAmount(item)"
                          :error-message="item.quantityError"
                          :validate-event="false"
                        >
                          <template #extra>{{ item.unit || '个' }}</template>
                        </van-field>
                      </div>
                    </div>
                    <div class="item-total">
                      <div class="total-amount">¥{{ getItemTotalAmount(item) }}</div>
                    </div>
                  </div>
                </template>
              </van-cell>
              <template #right>
                <van-button 
                  square 
                  type="danger" 
                  text="删除" 
                  class="delete-btn" 
                  @click="deleteSku(index)" 
                />
              </template>
            </van-swipe-cell>
          </div>
        </div>
        
        <!-- 合计金额 -->
        <div class="total-section" v-if="form.items.length > 0">
          <div class="total-row">
            <span>退货数量：</span>
            <span class="value">{{ totalQuantity }}</span>
          </div>
          <div class="total-row final-amount">
            <span>退货总金额：</span>
            <span class="value">¥{{ totalAmount.toFixed(2) }}</span>
          </div>
        </div>
      </van-form>
    </div>
    
    <!-- 销售订单选择弹窗 -->
    <van-popup 
      v-model:show="showOrderPicker" 
      position="bottom" 
      :style="{ height: '70%' }"
      :close-on-click-overlay="true"
    >
      <div class="picker-header">
        <van-nav-bar 
          title="选择销售订单" 
          left-text="取消" 
          @click-left="closeOrderPicker" 
        />
        <van-search 
          v-model="orderSearch" 
          placeholder="搜索订单编号或客户名称" 
          @update:model-value="onOrderSearchChange"
        />
      </div>
      <van-list 
        v-model:loading="orderLoading" 
        :finished="orderFinished" 
        finished-text="没有更多了"
        :immediate-check="false"
        @load="loadOrders"
      >
        <van-cell 
          v-for="order in orderList" 
          :key="order.id" 
          :title="`订单编号: ${order.order_no}`"
          :label="`客户: ${order.customer?.name || '无'} | 日期: ${order.order_date || order.expected_date} | 状态: ${getOrderStatusText(order.status)}`"
          @click="selectOrder(order)" 
        />
      </van-list>
    </van-popup>
    
    <!-- 仓库选择弹窗 -->
    <van-popup 
      v-model:show="showWarehousePicker" 
      position="bottom" 
      :style="{ height: '70%' }"
      :close-on-click-overlay="true"
    >
      <div class="picker-header">
        <van-nav-bar 
          title="选择仓库" 
          left-text="取消" 
          @click-left="closeWarehousePicker" 
        />
        <van-search 
          v-model="warehouseSearch" 
          placeholder="搜索仓库名称" 
          @update:model-value="onWarehouseSearchChange"
        />
      </div>
      <van-list 
        v-model:loading="warehouseLoading" 
        :finished="warehouseFinished" 
        finished-text="没有更多了"
        :immediate-check="false"
        @load="loadWarehouses"
      >
        <van-cell 
          v-for="warehouse in warehouseList" 
          :key="warehouse.id" 
          :title="warehouse.name"
          :label="`地址: ${warehouse.address || '无'} | 负责人: ${warehouse.manager || '无'}`"
          @click="selectWarehouse(warehouse)" 
        />
      </van-list>
    </van-popup>
    
    <!-- 退货日期选择器 -->
    <van-popup 
      v-model:show="showReturnDatePicker" 
      position="bottom" 
      :close-on-click-overlay="true"
    >
      <van-date-picker 
        v-model="currentDate" 
        :title="`选择退货日期 (${form.return_date})`"
        :min-date="minDate" 
        :max-date="maxDate"
        @confirm="onReturnDateConfirm" 
        @cancel="closeReturnDatePicker" 
      />
    </van-popup>
    
    <!-- 退货原因选择器弹窗 -->
    <van-popup 
      v-model:show="showReasonPicker" 
      position="bottom" 
      :close-on-click-overlay="true"
    >
      <van-picker 
        :title="`选择退货原因 (${form.return_type_text || '未选择'})`"
        :columns="returnReasonOptions"
        :default-index="getReturnReasonIndex()"
        @confirm="onReasonConfirm"
        @cancel="closeReasonPicker"
      />
    </van-popup>
    
    <!-- 商品选择弹窗 -->
    <van-popup 
      v-model:show="showSkuSelect" 
      position="bottom" 
      :style="{ height: '80%' }" 
      :close-on-click-overlay="true"
    >
      <div class="picker-header">
        <van-nav-bar 
          title="选择退货商品" 
          left-text="取消" 
          @click-left="closeSkuPicker" 
        />
        <van-search 
          v-model="skuSearch" 
          placeholder="搜索商品名称或编码" 
          @update:model-value="onSkuSearchChange"
        />
      </div>
      <van-list 
        v-model:loading="skuLoading" 
        :finished="skuFinished" 
        finished-text="没有更多了"
        :immediate-check="false"
        @load="loadSkuList"
      >
        <van-empty v-if="availableSkuList.length === 0 && !skuLoading" description="暂无商品或已全部添加" />
        <van-cell 
          v-for="item in availableSkuList" 
          :key="`${item.sku_id}_${item.spec || ''}`"
          :title="item.product_name"
          :label="`编号: ${item.product_no} | 规格: ${item.spec || '无'} | 已售: ${item.sale_quantity}${item.unit}`"
          @click="selectSku(item)" 
          :disabled="isSkuSelected(item)"
        />
      </van-list>
    </van-popup>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted, watch, nextTick } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { 
  showToast,
  showConfirmDialog,
  showSuccessToast,
  showFailToast
} from 'vant'
import dayjs from 'dayjs'
import { getSaleOrderList, getSaleOrderDetail, addSaleReturn, updateSaleReturn, getSaleReturnDetail } from '@/api/sale'
import { getWarehouseList } from '@/api/warehouse'

const route = useRoute()
const router = useRouter()
const formRef = ref()

// 判断是否为编辑状态
const isEdit = !!route.params.id

// 销售订单状态常量定义
const ORDER_STATUS = {
  PENDING: 1,      // 待审核
  AUDITED: 2,      // 已审核
  PARTIAL: 3,      // 部分出库
  COMPLETED: 4,    // 已完成
  CANCELLED: 5     // 已取消
}

// 表单数据 - 匹配状态管理和API
const form = reactive({
  id: '',
  order_id: '',
  order_no: '',
  warehouse_id: '',
  warehouse_name: '',
  return_date: dayjs().format('YYYY-MM-DD'),
  return_type: '',
  return_type_text: '',
  remark: '',
  items: [] // 包含sku_id, return_quantity, unit_price等字段
})

// 状态定义
const currentDate = ref([dayjs().year(), dayjs().month(), dayjs().date()])
const minDate = new Date(dayjs().subtract(1, 'year').format('YYYY-MM-DD'))
const maxDate = new Date(dayjs().add(1, 'year').format('YYYY-MM-DD'))

const showOrderPicker = ref(false)
const showWarehousePicker = ref(false)
const showReturnDatePicker = ref(false)
const showReasonPicker = ref(false)
const showSkuSelect = ref(false)

// 销售订单相关
const orderSearch = ref('')
const orderList = ref([])
const orderLoading = ref(false)
const orderFinished = ref(false)
const orderPage = ref(1)
const orderTotal = ref(0)
const orderListLoaded = ref(false)

// 仓库相关
const warehouseSearch = ref('')
const warehouseList = ref([])
const warehouseLoading = ref(false)
const warehouseFinished = ref(false)
const warehousePage = ref(1)
const warehouseTotal = ref(0)

// 商品相关
const skuSearch = ref('')
const availableSkuList = ref([]) // 从订单中获取的可退货商品
const skuLoading = ref(false)
const skuFinished = ref(false)
const skuSearchTimer = ref(null)

// 退货原因选项
const returnReasonOptions = [
  { text: '质量问题', value: 'quality' },
  { text: '客户原因', value: 'customer' },
  { text: '发错货', value: 'wrong_delivery' },
  { text: '其他', value: 'other' }
]

// 计算属性
const totalQuantity = computed(() => {
  return form.items.reduce((sum, item) => sum + (Number(item.return_quantity) || 0), 0)
})

const totalAmount = computed(() => {
  return form.items.reduce((sum, item) => {
    const price = Number(item.unit_price) || 0
    const quantity = Number(item.return_quantity) || 0
    return sum + (price * quantity)
  }, 0)
})

// 工具方法
const getSkuKey = (item, index) => {
  return `${item.sku_id}_${item.spec || 'default'}_${index}`
}

const getItemTotalAmount = (item) => {
  const price = Number(item.unit_price) || 0
  const quantity = Number(item.return_quantity) || 0
  return (price * quantity).toFixed(2)
}

const isOutOfStock = (item) => {
  const returnQty = Number(item.return_quantity) || 0
  const saleQty = Number(item.sale_quantity) || 0
  return returnQty > saleQty
}

const validatePrice = (item) => {
  const price = Number(item.unit_price)
  if (price <= 0) {
    item.priceError = '退货单价必须大于0'
    return false
  }
  if (isNaN(price)) {
    item.priceError = '请输入有效的单价'
    return false
  }
  item.priceError = ''
  return true
}

const validateQuantity = (item) => {
  const quantity = Number(item.return_quantity)
  const saleQuantity = Number(item.sale_quantity) || 0
  
  if (quantity <= 0) {
    item.quantityError = '退货数量必须大于0'
    return false
  }
  
  if (isNaN(quantity)) {
    item.quantityError = '请输入有效的数量'
    return false
  }
  
  if (quantity > saleQuantity) {
    item.quantityError = `退货数量不能超过销售数量(${saleQuantity})`
    return false
  }
  
  item.quantityError = ''
  return true
}

const updateItemAmount = (item) => {
  validatePrice(item)
  validateQuantity(item)
}

const isSkuSelected = (sku) => {
  return form.items.some(item => 
    item.sku_id === sku.sku_id && 
    item.spec === sku.spec
  )
}

// 获取退货原因选项的索引
const getReturnReasonIndex = () => {
  if (!form.return_type) return 0
  const index = returnReasonOptions.findIndex(option => option.value === form.return_type)
  return index >= 0 ? index : 0
}

// 订单状态文本映射
const getOrderStatusText = (status) => {
  const statusMap = {
    [ORDER_STATUS.PENDING]: '待审核',
    [ORDER_STATUS.AUDITED]: '已审核',
    [ORDER_STATUS.PARTIAL]: '部分出库',
    [ORDER_STATUS.COMPLETED]: '已完成',
    [ORDER_STATUS.CANCELLED]: '已取消'
  }
  return statusMap[status] || `未知状态(${status})`
}

const getReturnTypeText = (type) => {
  const typeMap = {
    quality: '质量问题',
    customer: '客户原因',
    wrong_delivery: '发错货',
    other: '其他'
  }
  return typeMap[type] || ''
}

// 订单加载 - 修复API响应结构
const loadOrders = async (reset = false) => {
  if (orderLoading.value || orderFinished.value) return
  
  orderLoading.value = true
  
  try {
    if (reset) {
      orderPage.value = 1
      orderList.value = []
      orderFinished.value = false
    }
    
    const params = {
      page: orderPage.value,
      limit: 15,
      keyword: orderSearch.value.trim(),
      status: ORDER_STATUS.COMPLETED
    }
    
    const result = await getSaleOrderList(params)
    const { list = [], total = 0 } = result.data || {} // 修正：使用list而不是data
    
    console.log('订单列表数据:', list)
    
    // 去重处理
    const newItems = list.filter(item => 
      !orderList.value.some(exist => exist.id === item.id)
    )
    
    if (reset) {
      orderList.value = newItems
    } else {
      orderList.value = [...orderList.value, ...newItems]
    }
    
    orderTotal.value = total
    orderFinished.value = orderList.value.length >= total
    orderPage.value++
    
    orderListLoaded.value = true
  } catch (error) {
    console.error('加载销售订单失败:', error)
    showFailToast(error.message || '加载销售订单失败')
  } finally {
    orderLoading.value = false
  }
}

const onOrderSearchChange = () => {
  if (orderSearchTimer) clearTimeout(orderSearchTimer)
  const orderSearchTimer = setTimeout(() => {
    orderList.value = []
    orderFinished.value = false
    orderPage.value = 1
    loadOrders(true)
  }, 500)
}

const searchOrders = () => {
  orderList.value = []
  orderFinished.value = false
  orderPage.value = 1
  loadOrders(true)
}

const selectOrder = async (order) => {
  form.order_id = order.id
  form.order_no = order.order_no
  
  // 清空商品列表
  form.items = []
  
  // 重置商品列表和分页状态
  availableSkuList.value = []
  skuFinished.value = false
  
  showOrderPicker.value = false
  
  // 获取订单详情，加载可退货商品
  try {
    const result = await getSaleOrderDetail(order.id)
    const orderData = result.data.data || {}
    const orderItems = (orderData.items || []).map(item => ({
      sku_id: item.sku_id || item.id,
      product_id: item.product_id,
      product_name: item.product_name,
      product_no: item.product_no,
      spec: item.spec || item.specification || '',
      unit: item.unit || '个',
      sale_quantity: item.quantity || item.sale_quantity || 0,
      unit_price: item.price || item.unit_price || 0,
      priceError: '',
      quantityError: ''
    }))
    
    console.log('订单商品数据:', orderItems)
    
    // 支持搜索过滤
    availableSkuList.value = skuSearch.value
      ? orderItems.filter(item => 
          item.product_name.includes(skuSearch.value) || 
          item.product_no.includes(skuSearch.value)
        )
      : orderItems
    
    skuFinished.value = true 
    
    showToast('订单商品加载完成')
  } catch (error) {
    console.error('获取订单详情失败:', error)
    showFailToast('获取订单商品失败')
  }
}

// 仓库加载 - 修复API响应结构
const loadWarehouses = async (reset = false) => {
  if (warehouseLoading.value || warehouseFinished.value) return
  
  warehouseLoading.value = true
  
  try {
    if (reset) {
      warehousePage.value = 1
      warehouseList.value = []
      warehouseFinished.value = false
    }
    
    const params = {
      page: warehousePage.value,
      limit: 15,
      keyword: warehouseSearch.value.trim()
    }
    
    const result = await getWarehouseList(params)
    const { list: data = [], total = 0 } = result.data || {}
    
    console.log('仓库列表数据:', data)
    
    // 去重处理
    const newItems = data.filter(item => 
      !warehouseList.value.some(exist => exist.id === item.id)
    )
    
    if (reset) {
      warehouseList.value = newItems
    } else {
      warehouseList.value = [...warehouseList.value, ...newItems]
    }
    
    warehouseTotal.value = total
    warehouseFinished.value = warehouseList.value.length >= total
    warehousePage.value++
  } catch (error) {
    console.error('加载仓库列表失败:', error)
    showFailToast('加载仓库列表失败')
  } finally {
    warehouseLoading.value = false
  }
}

const onWarehouseSearchChange = () => {
  if (warehouseSearchTimer) clearTimeout(warehouseSearchTimer)
  const warehouseSearchTimer = setTimeout(() => {
    warehouseList.value = []
    warehouseFinished.value = false
    warehousePage.value = 1
    loadWarehouses(true)
  }, 500)
}

const searchWarehouses = () => {
  warehouseList.value = []
  warehouseFinished.value = false
  warehousePage.value = 1
  loadWarehouses(true)
}

const selectWarehouse = (warehouse) => {
  form.warehouse_id = warehouse.id
  form.warehouse_name = warehouse.name
  showWarehousePicker.value = false
  showToast(`已选择仓库: ${warehouse.name}`)
}

// 商品加载
const loadSkuList = async (reset = false) => {
  if (skuLoading.value || skuFinished.value || !form.order_id) return
  
  skuLoading.value = true
  
  try {
    if (reset) {
      availableSkuList.value = []
      skuFinished.value = false
    }
    
    // 如果没有订单商品数据，重新获取
    if (availableSkuList.value.length === 0) {
      try {
        const result = await getSaleOrderDetail(form.order_id)
        const orderData = result.data.data || {}
        const orderItems = (orderData.items || []).map(item => ({
          sku_id: item.sku_id || item.id,
          product_id: item.product_id,
          product_name: item.product_name,
          product_no: item.product_no,
          spec: item.spec || item.specification || '',
          unit: item.unit || '个',
          sale_quantity: item.quantity || item.sale_quantity || 0,
          unit_price: item.price || item.unit_price || 0,
          priceError: '',
          quantityError: ''
        }))
        
        console.log('可退货商品:', orderItems)
        
        // 过滤已选择的商品和搜索条件
        const filteredItems = orderItems.filter(item => 
          !isSkuSelected(item) &&
          (!skuSearch.value.trim() || 
           item.product_name.includes(skuSearch.value.trim()) || 
           item.product_no.includes(skuSearch.value.trim()))
        )
        
        availableSkuList.value = filteredItems
        skuFinished.value = true
      } catch (error) {
        console.error('加载商品列表失败:', error)
        showFailToast('加载商品失败')
      }
    } else {
      // 过滤已选择的商品和搜索条件
      const filteredItems = availableSkuList.value.filter(item => 
        !isSkuSelected(item) &&
        (!skuSearch.value.trim() || 
         item.product_name.includes(skuSearch.value.trim()) || 
         item.product_no.includes(skuSearch.value.trim()))
      )
      
      availableSkuList.value = filteredItems
      skuFinished.value = true
    }
  } catch (error) {
    console.error('加载商品列表失败:', error)
    showFailToast('加载商品失败')
  } finally {
    skuLoading.value = false
  }
}

const onSkuSearchChange = () => {
  if (skuSearchTimer.value) {
    clearTimeout(skuSearchTimer.value)
  }
  
  skuSearchTimer.value = setTimeout(() => {
    availableSkuList.value = []
    skuFinished.value = false
    loadSkuList(true)
  }, 300)
}

const searchSku = () => {
  availableSkuList.value = []
  skuFinished.value = false
  loadSkuList(true)
}

const handleAddSku = () => {
  if (!form.order_id) {
    showToast('请先选择销售订单')
    return
  }
  showSkuSelect.value = true
}

const selectSku = (item) => {
  if (isSkuSelected(item)) {
    showToast('该商品已添加')
    return
  }
  
  const newItem = {
    ...item,
    return_quantity: 1,
    priceError: '',
    quantityError: ''
  }
  
  form.items.push(newItem)
  
  // 自动验证初始值
  validatePrice(newItem)
  validateQuantity(newItem)
  
  // 从可选列表中移除
  const index = availableSkuList.value.findIndex(sku => 
    sku.sku_id === item.sku_id && sku.spec === item.spec
  )
  if (index !== -1) {
    availableSkuList.value.splice(index, 1)
  }
  
  showToast('商品添加成功')
}

const deleteSku = async (index) => {
  try {
    await showConfirmDialog({
      title: '提示',
      message: '确定要删除这个商品吗？'
    })
    
    const deletedItem = form.items[index]
    form.items.splice(index, 1)
    
    // 如果商品选择弹窗打开，将商品加回到可选列表
    if (showSkuSelect.value) {
      // 查找原始商品数据（这里需要从订单详情重新获取或缓存）
      const originalItem = {
        ...deletedItem,
        return_quantity: 1,
        priceError: '',
        quantityError: ''
      }
      
      // 删除返回字段，只保留原始数据
      delete originalItem.return_quantity
      delete originalItem.priceError
      delete originalItem.quantityError
      
      if (!availableSkuList.value.some(sku => 
        sku.sku_id === originalItem.sku_id && sku.spec === originalItem.spec)
      ) {
        availableSkuList.value.unshift(originalItem)
      }
    }
    
    showToast('删除成功')
  } catch {
    // 用户取消
  }
}

// 日期选择 - 修复日期选择器
const onReturnDateConfirm = ({ selectedValues }) => {
  if (selectedValues && selectedValues.length === 3) {
    const [year, month, day] = selectedValues
    const selectedDate = new Date(year, month - 1, day)
    form.return_date = dayjs(selectedDate).format('YYYY-MM-DD')
    console.log('选择的日期:', form.return_date)
  }
  showReturnDatePicker.value = false
  showToast(`退货日期: ${form.return_date}`)
}

const closeReturnDatePicker = () => {
  showReturnDatePicker.value = false
}

// 退货原因选择 - 修复选择器
const onReasonConfirm = ({ selectedOptions }) => {
  if (selectedOptions && selectedOptions.length > 0) {
    const selectedReason = selectedOptions[0]
    form.return_type = selectedReason.value
    form.return_type_text = selectedReason.text
    console.log('选择的退货原因:', form.return_type, form.return_type_text)
  }
  showReasonPicker.value = false
  showToast(`退货原因: ${form.return_type_text}`)
}

const closeReasonPicker = () => {
  showReasonPicker.value = false
}

// 弹窗关闭方法
const closeOrderPicker = () => {
  showOrderPicker.value = false
}

const closeWarehousePicker = () => {
  showWarehousePicker.value = false
}

const closeSkuPicker = () => {
  showSkuSelect.value = false
}

// 构建销售退货提交数据 - 匹配API要求
const buildSaleReturnData = () => {
  return {
    id: form.id,
    order_id: form.order_id,
    order_no: form.order_no,
    warehouse_id: form.warehouse_id,
    return_date: form.return_date,
    return_type: form.return_type,
    remark: form.remark,
    items: form.items.map(item => ({
      sku_id: item.sku_id,
      return_quantity: item.return_quantity,
      price: item.unit_price
    }))
  }
}

// 验证表单数据
const validateForm = () => {
  // 验证商品信息
  if (form.items.length === 0) {
    showFailToast('请至少添加一个退货商品')
    return false
  }
  
  // 验证每个商品的价格和数量
  let hasError = false
  form.items.forEach(item => {
    const priceValid = validatePrice(item)
    const quantityValid = validateQuantity(item)
    if (!priceValid || !quantityValid) {
      hasError = true
    }
  })
  
  if (hasError) {
    showFailToast('请修正商品信息中的错误')
    return false
  }
  
  return true
}

// 提交表单
const handleSubmit = async () => {
  try {
    await formRef.value.validate()
    
    if (!validateForm()) {
      return
    }
    
    // 构造提交数据
    const submitData = buildSaleReturnData()
    
    console.log('提交数据:', submitData)
    
    // 提交请求
    try {
      if (isEdit) {
        await updateSaleReturn(form.id, submitData)
        showSuccessToast('编辑成功')
      } else {
        await addSaleReturn(submitData)
        showSuccessToast('创建成功')
      }
      
      router.back()
    } catch (error) {
      console.error('提交失败:', error)
      showFailToast(error.message || '操作失败，请重试')
    }
  } catch (error) {
    if (error.name !== 'ValidateError') {
      console.error('表单验证失败:', error)
    }
  }
}

// 返回
const handleBack = async () => {
  try {
    await showConfirmDialog({
      title: '提示',
      message: '确定要放弃编辑吗？未保存的内容将丢失',
    })
    
    router.back()
  } catch {
    // 用户取消
  }
}

// 监听弹窗显示状态
watch(showOrderPicker, (val) => {
  if (val && !orderListLoaded.value) {
    // 延迟加载以避免与van-list的初始加载冲突
    setTimeout(() => {
      loadOrders(true)
    }, 100)
  }
})

watch(showWarehousePicker, (val) => {
  if (val && warehouseList.value.length === 0) {
    setTimeout(() => {
      loadWarehouses(true)
    }, 100)
  }
})

watch(showSkuSelect, (val) => {
  if (val && form.order_id) {
    // 重置搜索
    skuSearch.value = ''
    availableSkuList.value = []
    skuFinished.value = false
    
    // 延迟加载
    setTimeout(() => {
      loadSkuList(true)
    }, 100)
  }
})

// 初始化
onMounted(async () => {
  // 编辑状态加载详情
  if (isEdit) {
    try {
      const result = await getSaleReturnDetail(route.params.id)
      const detail = result.data.data
      
      console.log('退货单详情:', detail)
      
      // 填充表单数据
      form.id = detail.id
      form.order_id = detail.sale_order_id || detail.order_id
      form.order_no = detail.sale_order_no || detail.order_no
      form.warehouse_id = detail.warehouse_id
      form.warehouse_name = detail.warehouse_name
      form.return_date = detail.return_date || dayjs().format('YYYY-MM-DD')
      form.return_type = detail.return_type
      form.return_type_text = getReturnTypeText(detail.return_type)
      form.remark = detail.remark || ''
      
      // 设置当前日期
      if (detail.return_date) {
        const dateParts = detail.return_date.split('-')
        if (dateParts.length === 3) {
          currentDate.value = [parseInt(dateParts[0]), parseInt(dateParts[1]), parseInt(dateParts[2])]
        }
      }
      
      // 处理商品明细
      form.items = (detail.items || []).map(item => ({
        sku_id: item.sku_id,
        product_id: item.product_id,
        product_name: item.product_name,
        product_no: item.product_no,
        spec: item.spec,
        unit: item.unit,
        sale_quantity: item.sale_quantity || item.quantity || 0,
        unit_price: item.price,
        return_quantity: item.return_quantity,
        priceError: '',
        quantityError: ''
      }))
      
      // 加载订单商品
      if (form.order_id) {
        try {
          const orderResult = await getSaleOrderDetail(form.order_id)
          const orderData = orderResult.data.data || {}
          const orderItems = (orderData.items || []).map(item => ({
            sku_id: item.sku_id || item.id,
            product_id: item.product_id,
            product_name: item.product_name,
            product_no: item.product_no,
            spec: item.spec || item.specification || '',
            unit: item.unit || '个',
            sale_quantity: item.quantity || item.sale_quantity || 0,
            unit_price: item.price || item.unit_price || 0,
            priceError: '',
            quantityError: ''
          }))
          
          availableSkuList.value = orderItems.filter(item => 
            !form.items.some(exist => exist.sku_id === item.sku_id && exist.spec === item.spec)
          )
          skuFinished.value = true
        } catch (error) {
          console.error('加载订单商品失败:', error)
        }
      }
    } catch (error) {
      console.error('加载退货单详情失败:', error)
      showFailToast('加载失败，请重试')
      router.back()
    }
  }
})
</script>

<style scoped>
.sale-return-form {
  min-height: 100vh;
  background-color: #f5f5f5;
}

.form-container {
  padding-top: 46px;
  padding-bottom: 20px;
}

.sku-section {
  margin-top: 16px;
  padding: 0 16px;
}

.section-title {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 12px;
  font-size: 16px;
  font-weight: 500;
  color: #333;
}

.sku-list {
  margin-bottom: 16px;
}

.sku-item {
  margin-bottom: 8px;
  border-radius: 8px;
  overflow: hidden;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
}

.sku-cell {
  padding: 12px;
  background-color: #fff;
}

.product-title {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.product-name {
  font-weight: 500;
  font-size: 15px;
  color: #333;
  line-height: 1.4;
}

.sku-code {
  font-size: 12px;
  color: #666;
}

.product-label {
  display: flex;
  flex-direction: column;
  gap: 4px;
  margin-top: 4px;
}

.spec-text {
  font-size: 12px;
  color: #666;
  background-color: #f0f0f0;
  padding: 2px 6px;
  border-radius: 4px;
  display: inline-block;
}

.stock-text {
  font-size: 12px;
  color: #666;
}

.out-of-stock {
  color: #f53f3f;
  margin-left: 4px;
}

.item-details {
  display: flex;
  flex-direction: column;
  align-items: flex-end;
  gap: 8px;
}

.price-quantity {
  display: flex;
  gap: 12px;
}

.input-field {
  width: 100px;
}

.compact-field {
  --van-field-input-height: 32px;
  --van-field-label-width: 0;
  text-align: right;
  background-color: #f9f9f9;
  border-radius: 4px;
}

.compact-field :deep(.van-field__control) {
  font-size: 14px;
}

.item-total {
  text-align: right;
}

.total-amount {
  font-weight: 500;
  color: #f53f3f;
  font-size: 15px;
}

.delete-btn {
  height: 100%;
}

.total-section {
  padding: 16px;
  background-color: #fff;
  border-top: 1px solid #eee;
  margin-top: 16px;
}

.total-row {
  display: flex;
  justify-content: space-between;
  margin-bottom: 8px;
  font-size: 14px;
  color: #666;
}

.total-row .value {
  color: #333;
  font-weight: 500;
}

.final-amount {
  font-weight: 500;
  color: #f53f3f;
  font-size: 16px;
  margin-top: 4px;
  padding-top: 8px;
  border-top: 1px solid #eee;
}

.picker-header {
  background-color: #fff;
  border-bottom: 1px solid #eee;
}

.picker-header .van-nav-bar {
  background-color: #fff;
}
</style>
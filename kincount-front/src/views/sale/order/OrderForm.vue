<template>
  <div class="sale-order-form">
    <van-nav-bar 
      :title="isEdit ? '编辑销售订单' : '新建销售订单'" 
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
          v-model="form.customer_name" 
          name="customer" 
          label="客户" 
          placeholder="请选择客户" 
          is-link 
          readonly
          @click="showCustomerPicker = true" 
          :rules="[{ required: true, message: '请选择客户' }]" 
        />
        <!-- 仓库选择 -->
        <van-field 
          v-model="form.warehouse_name" 
          name="warehouse" 
          label="发货仓库" 
          placeholder="请选择仓库" 
          is-link 
          readonly
          @click="showWarehousePicker = true" 
          :rules="[{ required: true, message: '请选择仓库' }]" 
        />
        <!-- 删除订单编号字段 -->
        <!-- 订单日期 -->
        <van-field 
          v-model="form.order_date" 
          name="order_date" 
          label="订单日期" 
          placeholder="请选择日期" 
          is-link 
          readonly
          @click="showOrderDatePicker = true" 
          :rules="[{ required: true, message: '请选择订单日期' }]" 
        />
        <!-- 预计交货日期 -->
        <van-field 
          v-model="form.expected_date" 
          name="expected_date" 
          label="预计交货" 
          placeholder="请选择日期" 
          is-link 
          readonly
          @click="showDeliveryDatePicker = true" 
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
        
        <!-- SKU明细选择区域 -->
        <div class="sku-section">
          <div class="section-title">
            <span>销售商品明细</span>
            <van-button 
              size="small" 
              type="primary" 
              @click="showSkuSelect = true" 
              icon="plus"
              :disabled="!form.warehouse_id"
            >
              添加商品
            </van-button>
          </div>
          
          <!-- SKU列表 -->
          <van-empty v-if="form.items.length === 0" description="请添加销售商品" />
          <van-cell-group v-else class="sku-list">
            <van-swipe-cell 
              v-for="(item, index) in form.items" 
              :key="item.sku_id + '_' + index" 
              class="sku-item"
            >
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
                    <div class="stock-text">
                      库存: {{ item.stock_quantity || item.stock || 0 }}{{ item.unit }}
                      <span v-if="isOutOfStock(item)" class="out-of-stock">(超出库存)</span>
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
                          @input="updateItemAmount(item)"
                          :error-message="item.quantityError"
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
          </van-cell-group>
        </div>
        
        <!-- 合计金额 -->
        <div class="total-section" v-if="form.items.length > 0">
          <div class="total-row">
            <span>商品数量：</span>
            <span class="value">{{ totalQuantity }}</span>
          </div>
          <div class="total-row">
            <span>商品金额：</span>
            <span class="value">¥{{ totalAmount.toFixed(2) }}</span>
          </div>
          <div class="total-row">
            <span>折扣金额：</span>
            <van-field 
              v-model.number="form.discount_amount" 
              type="number" 
              placeholder="0.00" 
              class="discount-field"
              @blur="validateDiscount"
              @input="updateTotalAmount"
              :error-message="discountError"
            >
              <template #extra>元</template>
            </van-field>
          </div>
          <div class="total-row final-amount">
            <span>实付金额：</span>
            <span class="value">¥{{ actualAmount.toFixed(2) }}</span>
          </div>
        </div>
      </van-form>
    </div>
    
    <!-- 客户选择弹窗 -->
    <van-popup 
      v-model:show="showCustomerPicker" 
      position="bottom" 
      :style="{ height: '70%' }"
      :close-on-click-overlay="true"
    >
      <div class="picker-header">
        <van-nav-bar 
          title="选择客户" 
          left-text="取消" 
          @click-left="closeCustomerPicker" 
        />
        <van-search 
          v-model="customerSearch" 
          placeholder="搜索客户名称" 
          @update:model-value="searchCustomers" 
        />
      </div>
      <van-list 
        v-model:loading="customerLoading" 
        :finished="customerFinished" 
        finished-text="没有更多了"
        @load="loadMoreCustomers"
      >
        <van-cell 
          v-for="customer in customerList" 
          :key="customer.id" 
          :title="customer.name"
          :label="`联系人: ${customer.contact_person || '无'} | 电话: ${customer.phone || '无'}`"
          @click="selectCustomer(customer)" 
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
          @update:model-value="searchWarehouses" 
        />
      </div>
      <van-list 
        v-model:loading="warehouseLoading" 
        :finished="warehouseFinished" 
        finished-text="没有更多了"
        @load="loadMoreWarehouses"
      >
        <van-cell 
          v-for="warehouse in warehouseList" 
          :key="warehouse.id" 
          :title="warehouse.name"
          :label="`地址: ${warehouse.address || '无'} | 负责人: ${warehouse.manager || '无'} | 电话: ${warehouse.phone || '无'}`"
          @click="selectWarehouse(warehouse)" 
        />
      </van-list>
    </van-popup>
    
    <!-- 订单日期选择器 -->
    <van-popup 
      v-model:show="showOrderDatePicker" 
      position="bottom" 
      :close-on-click-overlay="true"
    >
      <van-date-picker 
        v-model="orderDate" 
        title="选择订单日期" 
        :min-date="minDate" 
        :max-date="maxDate"
        @confirm="onOrderDateConfirm" 
        @cancel="closeOrderDatePicker" 
      />
    </van-popup>
    
    <!-- 交货日期选择器 -->
    <van-popup 
      v-model:show="showDeliveryDatePicker" 
      position="bottom" 
      :close-on-click-overlay="true"
    >
      <van-date-picker 
        v-model="deliveryDate" 
        title="选择预计交货日期" 
        :min-date="minDate" 
        :max-date="maxDeliveryDate"
        @confirm="onDeliveryDateConfirm" 
        @cancel="closeDeliveryDatePicker" 
      />
    </van-popup>
    
    <!-- SKU选择弹窗 -->
    <van-popup 
      v-model:show="showSkuSelect" 
      position="bottom" 
      :style="{ height: '80%' }" 
      :close-on-click-overlay="true"
    >
      <SkuSelect 
        ref="skuSelectRef" 
        v-model="selectedSkuIds" 
        :show-header="true" 
        :show-footer="false"
        header-title="选择销售商品"
        :multiple="true"
        @confirm="handleSkuSelectConfirm" 
        @cancel="closeSkuPicker" 
      />
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
import { useSaleStore } from '@/store/modules/sale'
import { useCustomerStore } from '@/store/modules/customer'
import { useWarehouseStore } from '@/store/modules/warehouse'
import { SkuSelect } from '@/components'

// 路由相关
const route = useRoute()
const router = useRouter()
const isEdit = !!route.params.id

// 状态管理
const saleStore = useSaleStore()
const customerStore = useCustomerStore()
const warehouseStore = useWarehouseStore()

// 组件引用
const formRef = ref(null)
const skuSelectRef = ref(null)
const submitting = ref(false)

// 表单数据 - 移除 order_no 字段
const form = reactive({
  customer_id: '',
  customer_name: '',
  warehouse_id: '',
  warehouse_name: '',
  order_date: dayjs().format('YYYY-MM-DD'),
  expected_date: '',
  remark: '',
  discount_amount: 0,
  items: [] // 存储选中的SKU明细
})

// 选择器状态
const showCustomerPicker = ref(false)
const showWarehousePicker = ref(false)
const showOrderDatePicker = ref(false)
const showDeliveryDatePicker = ref(false)
const showSkuSelect = ref(false)

// 客户选择相关
const customerSearch = ref('')
const customerList = ref([])
const customerLoading = ref(false)
const customerFinished = ref(false)
const customerPage = ref(1)

// 仓库选择相关
const warehouseSearch = ref('')
const warehouseList = ref([])
const warehouseLoading = ref(false)
const warehouseFinished = ref(false)
const warehousePage = ref(1)

// SKU选择相关
const selectedSkuIds = ref([])

// 日期相关
const orderDate = ref([])
const deliveryDate = ref([])
const minDate = new Date(2020, 0, 1)
const maxDate = new Date()
const maxDeliveryDate = new Date(new Date().setFullYear(new Date().getFullYear() + 1))

// 验证错误
const discountError = ref('')

// 计算属性 - 优化计算逻辑
const totalQuantity = computed(() => {
  return form.items.reduce((sum, item) => {
    return sum + (Number(item.quantity) || 0)
  }, 0)
})

const totalAmount = computed(() => {
  return form.items.reduce((sum, item) => {
    const price = Number(item.price) || 0
    const quantity = Number(item.quantity) || 0
    return sum + (price * quantity)
  }, 0)
})

const actualAmount = computed(() => {
  const total = Number(totalAmount.value) || 0
  const discount = Number(form.discount_amount) || 0
  return Math.max(0, total - discount)
})

// 方法 - 获取单个商品的总金额
const getItemTotalAmount = (item) => {
  const price = Number(item.price) || 0
  const quantity = Number(item.quantity) || 0
  return (price * quantity).toFixed(2)
}

// 获取商品显示名称
const getProductDisplayName = (item) => {
  if (item.product && item.product.name) {
    return item.product.name
  }
  if (item.product_name) {
    return item.product_name
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
      .map(([key, value]) => `${key}: ${value}`)
      .join(' / ')
  }
  return ''
}

// 检查是否超出库存
const isOutOfStock = (item) => {
  const stock = item.stock_quantity || item.stock || 0
  const quantity = Number(item.quantity) || 0
  return quantity > stock
}

// 监听商品数量单价变化，更新金额显示
const updateItemAmount = (item) => {
  // 触发响应式更新
  nextTick(() => {
    // 这里不需要额外操作，因为计算属性会自动更新
  })
}

// 更新总金额显示
const updateTotalAmount = () => {
  // 触发响应式更新
  nextTick(() => {
    validateDiscount()
  })
}

// 监听商品列表变化，确保数据格式正确
watch(() => form.items, (newItems) => {
  newItems.forEach(item => {
    // 确保 price 和 quantity 是数字
    if (item.price && typeof item.price !== 'number') {
      item.price = Number(item.price)
    }
    if (item.quantity && typeof item.quantity !== 'number') {
      item.quantity = Number(item.quantity)
    }
  })
}, { deep: true })

// 初始化表单数据 - 移除生成订单编号的逻辑
const initForm = async () => {
  if (isEdit) {
    // 编辑模式：加载订单详情
    const id = route.params.id
    try {
      await saleStore.loadOrderDetail(id)
      const orderDetail = saleStore.currentOrder
      
      if (orderDetail) {
        // 订单编号从后端获取，但不在表单中显示
        // form.order_no = orderDetail.order_no
        form.customer_id = orderDetail.customer_id
        form.customer_name = orderDetail.customer?.name || ''
        form.warehouse_id = orderDetail.warehouse_id
        form.warehouse_name = orderDetail.warehouse?.name || ''
        form.order_date = orderDetail.order_date || dayjs().format('YYYY-MM-DD')
        form.expected_date = orderDetail.expected_date || ''
        form.remark = orderDetail.remark || ''
        form.discount_amount = orderDetail.discount_amount || 0
        
        // 设置SKU明细 - 根据后端返回的数据结构调整
        form.items = (orderDetail.items || []).map(item => {
          const product = item.product || {}
          const sku = item.sku || {}
          
          return {
            id: item.id,
            sku_id: item.sku_id,
            product_id: item.product_id,
            sku_code: sku.sku_code || product.product_no || '',
            product: product,
            product_name: product.name || '',
            spec: sku.spec || {},
            unit: sku.unit || product.unit || '个',
            stock_quantity: sku.stock_quantity || 0,
            price: Number(item.price) || 0,
            quantity: Number(item.quantity) || 0,
            priceError: '',
            quantityError: ''
          }
        })
        
        // 设置选中的SKU ID
        selectedSkuIds.value = form.items.map(item => item.sku_id).filter(Boolean)
        
        // 设置日期选择器的当前值
        if (form.order_date) {
          const date = new Date(form.order_date)
          orderDate.value = [date.getFullYear(), date.getMonth() + 1, date.getDate()]
        }
        if (form.expected_date) {
          const date = new Date(form.expected_date)
          deliveryDate.value = [date.getFullYear(), date.getMonth() + 1, date.getDate()]
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
    // 新增模式：设置默认日期
    form.order_date = dayjs().format('YYYY-MM-DD')
    orderDate.value = [
      new Date().getFullYear(),
      new Date().getMonth() + 1,
      new Date().getDate()
    ]
    
    // 设置默认交货日期为明天
    const tomorrow = dayjs().add(1, 'day')
    form.expected_date = tomorrow.format('YYYY-MM-DD')
    deliveryDate.value = [tomorrow.year(), tomorrow.month() + 1, tomorrow.date()]
    
    // 移除生成订单编号的调用，由后端自动生成
    // await generateOrderNo()
  }
}

// 移除 generateOrderNo 函数

// 加载客户列表
const loadCustomers = async (page = 1, keyword = '') => {
  customerLoading.value = true
  try {
    const params = {
      page,
      limit: 20,
      keyword,
      status: 1
    }
    const res = await customerStore.loadList(params)

    // 适配标准响应结构
    let list = []
    if (res && res.code === 200) {
      if (Array.isArray(res.data)) {
        list = res.data
      } else if (res.data && res.data.list && Array.isArray(res.data.list)) {
        list = res.data.list
      }
    } else if (Array.isArray(res)) {
      list = res
    } else if (res && res.list) {
      list = res.list
    } else {
      list = customerStore.list || []
    }

    if (page === 1) {
      customerList.value = list
    } else {
      customerList.value = [...customerList.value, ...list]
    }
    customerFinished.value = list.length < 20
    return list
  } catch (error) {
    console.error('加载客户失败:', error)
    showFailToast('加载客户失败')
    return []
  } finally {
    customerLoading.value = false
  }
}

// 搜索客户
const searchCustomers = () => {
  customerPage.value = 1
  customerFinished.value = false
  loadCustomers(1, customerSearch.value)
}

// 加载更多客户
const loadMoreCustomers = () => {
  if (customerFinished.value) return
  customerPage.value += 1
  loadCustomers(customerPage.value, customerSearch.value)
}

// 选择客户
const selectCustomer = (customer) => {
  form.customer_id = customer.id
  form.customer_name = customer.name
  showCustomerPicker.value = false
  customerSearch.value = ''
}

// 关闭客户选择器
const closeCustomerPicker = () => {
  showCustomerPicker.value = false
  customerSearch.value = ''
}

// 加载仓库列表
const loadWarehouses = async (page = 1, keyword = '') => {
  warehouseLoading.value = true
  try {
    const params = {
      page,
      limit: 20,
      keyword,
      status: 1
    }
    const res = await warehouseStore.loadList(params)

    // 适配标准响应结构
    let list = []
    if (res && res.code === 200) {
      if (Array.isArray(res.data)) {
        list = res.data
      } else if (res.data && res.data.list && Array.isArray(res.data.list)) {
        list = res.data.list
      }
    } else if (Array.isArray(res)) {
      list = res
    } else if (res && res.list) {
      list = res.list
    } else {
      list = warehouseStore.list || []
    }

    if (page === 1) {
      warehouseList.value = list
    } else {
      warehouseList.value = [...warehouseList.value, ...list]
    }
    warehouseFinished.value = list.length < 20
    return list
  } catch (error) {
    console.error('加载仓库失败:', error)
    showFailToast('加载仓库失败')
    return []
  } finally {
    warehouseLoading.value = false
  }
}

// 搜索仓库
const searchWarehouses = () => {
  warehousePage.value = 1
  warehouseFinished.value = false
  loadWarehouses(1, warehouseSearch.value)
}

// 加载更多仓库
const loadMoreWarehouses = () => {
  if (warehouseFinished.value) return
  warehousePage.value += 1
  loadWarehouses(warehousePage.value, warehouseSearch.value)
}

// 选择仓库
const selectWarehouse = (warehouse) => {
  form.warehouse_id = warehouse.id
  form.warehouse_name = warehouse.name
  showWarehousePicker.value = false
  warehouseSearch.value = ''
}

// 关闭仓库选择器
const closeWarehousePicker = () => {
  showWarehousePicker.value = false
  warehouseSearch.value = ''
}

// 订单日期确认
const onOrderDateConfirm = ({ selectedValues }) => {
  const [year, month, day] = selectedValues
  form.order_date = `${year}-${String(month).padStart(2, '0')}-${String(day).padStart(2, '0')}`
  showOrderDatePicker.value = false
}

// 关闭订单日期选择器
const closeOrderDatePicker = () => {
  showOrderDatePicker.value = false
}

// 交货日期确认
const onDeliveryDateConfirm = ({ selectedValues }) => {
  const [year, month, day] = selectedValues
  form.expected_date = `${year}-${String(month).padStart(2, '0')}-${String(day).padStart(2, '0')}`
  showDeliveryDatePicker.value = false
}

// 关闭交货日期选择器
const closeDeliveryDatePicker = () => {
  showDeliveryDatePicker.value = false
}

// SKU选择结果处理 - 根据新的数据结构优化
const handleSkuSelectConfirm = async (result) => {
  const { selectedIds, selectedData } = result

  if (!selectedData || selectedData.length === 0) {
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
        const currentQuantity = Number(form.items[existingIndex].quantity) || 0
        form.items[existingIndex].quantity = currentQuantity + 1
      } else {
        // 新增SKU - 根据新的数据结构
        const newItem = {
          sku_id: sku.id,
          product_id: sku.product_id,
          product: sku.product || null,
          product_name: sku.product?.name || '未知商品',
          sku_code: sku.sku_code || '',
          spec: sku.spec || {},
          unit: sku.unit || '个',
          stock_quantity: sku.stock_quantity || 0,
          price: Number(sku.sale_price) || 0,
          quantity: 1,
          priceError: '',
          quantityError: ''
        }
        form.items.push(newItem)
      }
    } catch (error) {
      console.error('添加SKU失败:', error)
    }
  }

  showSkuSelect.value = false
  showSuccessToast(`已添加 ${selectedData.length} 个商品`)
}

// 关闭SKU选择器
const closeSkuPicker = () => {
  showSkuSelect.value = false
}

// 删除SKU
const deleteSku = (index) => {
  form.items.splice(index, 1)
  // 同步更新选中的SKU ID
  selectedSkuIds.value = form.items.map(item => item.sku_id)
}

// 验证价格
const validatePrice = (item) => {
  const price = Number(item.price)
  if (isNaN(price) || price <= 0) {
    item.priceError = '单价必须大于0'
    return false
  }
  item.priceError = ''
  return true
}

// 验证数量
const validateQuantity = (item) => {
  const quantity = Number(item.quantity)
  if (isNaN(quantity) || quantity <= 0) {
    item.quantityError = '数量必须大于0'
    return false
  }
  
  // 验证库存（如果有库存信息）
  const stock = item.stock_quantity || item.stock
  if (stock !== undefined && quantity > stock) {
    item.quantityError = `数量不能超过库存(${stock})`
    return false
  }
  
  item.quantityError = ''
  return true
}

// 验证折扣
const validateDiscount = () => {
  const discount = Number(form.discount_amount)
  const total = Number(totalAmount.value) || 0
  
  if (isNaN(discount) || discount < 0) {
    discountError.value = '折扣金额不能为负数'
    return false
  }
  
  if (discount > total) {
    discountError.value = '折扣金额不能超过商品总金额'
    return false
  }
  
  discountError.value = ''
  return true
}

// 验证表单
const validateForm = () => {
  if (!form.customer_id) {
    showToast('请选择客户')
    return false
  }
  if (!form.warehouse_id) {
    showToast('请选择发货仓库')
    return false
  }
  if (!form.order_date) {
    showToast('请选择订单日期')
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
    
    // 库存验证
    const stock = item.stock_quantity || item.stock
    if (stock !== undefined && quantity > stock) {
      showToast(`商品"${getProductDisplayName(item)}"的数量(${quantity})超过库存(${stock})`)
      return false
    }
  }
  
  // 验证折扣
  if (!validateDiscount()) {
    return false
  }
  
  return true
}

// 表单提交 - 移除 order_no 字段
const handleSubmit = async () => {
  if (!validateForm()) {
    return
  }
  
  submitting.value = true
  try {
    // 准备提交数据 - 移除 order_no 字段，由后端自动生成
    const submitData = {
      customer_id: form.customer_id,
      warehouse_id: form.warehouse_id,
      remark: form.remark,
      expected_date: form.expected_date || form.order_date,
      discount_amount: Number(form.discount_amount) || 0,
      items: form.items.map(item => {
        const itemData = {
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

    console.log('提交的销售订单数据:', submitData)

    let result
    if (isEdit) {
      // 编辑订单
      result = await saleStore.updateOrder(route.params.id, submitData)
      showSuccessToast('更新成功')
    } else {
      // 创建新订单
      result = await saleStore.addOrder(submitData)
      showSuccessToast('销售订单创建成功')
      if (result?.data?.id) {
        // 创建成功后跳转到详情页
        router.push(`/sale/order/detail/${result?.data?.id}`)
        return
      }
    }
    // 操作成功后返回列表页
    router.push('/sale/order')
  } catch (error) {
    console.error('保存失败:', error)
    if (error.message !== 'cancel') {
      showFailToast(error.message || '保存失败')
    }
  } finally {
    submitting.value = false
  }
}

// 返回上一页
const handleBack = () => {
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
}

onMounted(async () => {
  await initForm()
  // 预加载客户和仓库列表
  await loadCustomers()
  await loadWarehouses()
})
</script>

<style scoped lang="scss">
.sale-order-form {
  background-color: #f7f8fa;
  min-height: 100vh;
  padding-top: 46px;
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
  
  .stock-text {
    color: #1989fa;
    line-height: 1.3;
    
    .out-of-stock {
      color: #ee0a24;
      font-weight: bold;
    }
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
}

.total-section {
  padding: 12px 16px;
  background-color: #f8f9fa;
  border-radius: 8px;
  margin: 16px 0;
  border: 1px solid #e9ecef;
}

.total-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 8px;
  font-size: 14px;
  
  &:last-child {
    margin-bottom: 0;
  }
  
  .value {
    font-weight: 500;
    color: #323233;
  }
  
  &.final-amount {
    margin-top: 12px;
    padding-top: 12px;
    border-top: 1px solid #e9ecef;
    
    .value {
      color: #f53f3f;
      font-weight: bold;
      font-size: 16px;
    }
  }
}

.discount-field {
  width: 120px;
  padding: 4px 8px;
  border: 1px solid #e0e0e0;
  border-radius: 4px;
  background: #fff;
  
  &:deep(.van-field__control) {
    font-size: 14px;
    text-align: right;
  }
  
  &:deep(.van-field__extra) {
    color: #969799;
    font-size: 12px;
  }
}

.picker-header {
  background: white;

  .van-nav-bar {
    background: white;
  }

  .van-search {
    padding: 10px 12px;
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

// SKU选择组件样式调整
:deep(.sku-select-component) {
  height: calc(100% - 46px);

  .sku-list-container {
    height: calc(100% - 120px);
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
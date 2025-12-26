<template>
  <div class="sale-return-form">
    <van-nav-bar :title="isEdit ? '编辑销售退货单' : '新建销售退货单'" fixed placeholder left-text="取消" right-text="保存"
      @click-left="handleBack" @click-right="handleSubmit" />

    <div class="form-container">
      <van-form ref="formRef" @submit="handleSubmit">
        <!-- 源单信息 -->
        <div class="source-section">
          <div class="section-header">
            <h3>退货源单信息</h3>
          </div>

          <!-- 源单类型选择 -->
          <van-radio-group v-model="sourceType" direction="horizontal" class="source-type-group">
            <van-radio name="order">
              <div class="radio-label">
                销售订单<br>
                <span class="radio-desc">出库前退货</span>
              </div>
            </van-radio>
            <van-radio name="stock">
              <div class="radio-label">
                销售出库单<br>
                <span class="radio-desc">出库后退货</span>
              </div>
            </van-radio>
          </van-radio-group>

          <!-- 源单选择 -->
          <van-field v-model="sourceForm.source_name" :label="sourceType === 'order' ? '销售订单' : '销售出库单'"
            placeholder="请选择源单" is-link readonly @click="showSourcePicker = true"
            :rules="[{ required: true, message: '请选择源单' }]" />

          <van-field v-model="sourceForm.customer_name" label="客户" readonly />

          <van-field v-model="sourceForm.order_no" :label="sourceType === 'order' ? '订单编号' : '出库单号'" readonly />
        </div>

        <!-- 退货基本信息 -->
        <div class="return-section">
          <div class="section-header">
            <h3>退货信息</h3>
          </div>

          <!-- 退货仓库 -->
          <van-field v-model="form.warehouse_name" name="warehouse" label="退货仓库" placeholder="请选择退货仓库" is-link readonly
            @click="showWarehousePicker = true" :rules="[{ required: true, message: '请选择退货仓库' }]" />

          <!-- 退货日期 -->
          <van-field v-model="form.return_date" name="return_date" label="退货日期" placeholder="请选择退货日期" is-link readonly
            @click="showDatePicker = true" :rules="[{ required: true, message: '请选择退货日期' }]" />

          <!-- 退货原因 -->
          <van-field v-model="form.return_type_text" name="return_type" label="退货原因" placeholder="请选择退货原因" is-link
            readonly @click="showReasonPicker = true" :rules="[{ required: true, message: '请选择退货原因' }]" />

          <!-- 备注 -->
          <van-field v-model="form.remark" name="remark" label="备注" type="textarea" placeholder="请输入备注信息" rows="3"
            maxlength="200" show-word-limit />
        </div>

        <!-- 退货商品明细 -->
        <div class="sku-section">
          <div class="section-title">
            <span>退货商品明细</span>
            <van-button size="small" type="primary" @click="showSkuSelect = true" icon="plus"
              :disabled="!sourceForm.source_id || form.items.length >= (sourceForm.items?.length || 0)">
              选择商品
            </van-button>
          </div>

          <!-- 商品列表 -->
          <van-empty v-if="form.items.length === 0" description="请选择退货商品" />
          <van-cell-group v-else class="sku-list">
            <van-swipe-cell v-for="(item, index) in form.items" :key="`${item.sku_id}_${item.source_item_id}_${index}`"
              class="sku-item">
              <van-cell class="sku-cell">
                <template #title>
                  <div class="product-title">
                    <span class="product-name">{{ getProductDisplayName(item) }}</span>
                    <span class="sku-code" v-if="item.sku_code">{{ item.sku_code }}</span>
                  </div>
                </template>
                <template #label>
                  <div class="product-label">
                    <div class="spec-text" v-if="getItemSpecText(item)">规格: {{ getItemSpecText(item) }}</div>
                    <div class="stock-text">
                      源单数量: {{ item.source_quantity || 0 }}{{ item.unit }}
                      <span class="returned-info" v-if="item.returned_quantity">(已退: {{ item.returned_quantity || 0
                      }})</span>
                    </div>
                    <div class="max-return-text">
                      最多可退: <span :class="{ 'out-of-stock': item.max_return_quantity <= 0 }">{{ item.max_return_quantity
                        || 0 }}</span>{{ item.unit }}
                    </div>
                  </div>
                </template>
                <template #default>
                  <div class="item-details">
                    <div class="price-quantity">
                      <!-- 单价（只读） -->
                      <div class="input-field price-field">
                        <van-field v-model="item.unit_price" type="number" readonly
                          class="readonly-field compact-field">
                          <template #extra>元</template>
                        </van-field>
                      </div>
                      <!-- 退货数量输入框 -->
                      <div class="input-field quantity-field">
                        <van-field v-model.number="item.return_quantity" type="number" placeholder="0"
                          class="editable-field compact-field" @blur="validateReturnQuantity(item, index)"
                          @input="updateItemAmount(item)" :error-message="item.quantityError">
                          <template #extra>{{ item.unit || '个' }}</template>
                        </van-field>
                      </div>
                    </div>
                    <div class="item-total">
                      <div class="total-amount">¥{{ getItemReturnAmount(item) }}</div>
                    </div>
                  </div>
                </template>
              </van-cell>
              <template #right>
                <van-button square type="danger" text="删除" class="delete-btn" @click="deleteReturnItem(index)" />
              </template>
            </van-swipe-cell>
          </van-cell-group>
        </div>

        <!-- 合计金额 -->
        <div class="total-section" v-if="form.items.length > 0">
          <div class="total-row">
            <span>退货数量：</span>
            <span class="value">{{ totalReturnQuantity }}</span>
          </div>
          <div class="total-row">
            <span>退货金额：</span>
            <span class="value">¥{{ totalReturnAmount.toFixed(2) }}</span>
          </div>
          <div class="total-row final-amount">
            <span>应退金额：</span>
            <span class="value">¥{{ totalReturnAmount.toFixed(2) }}</span>
          </div>
        </div>
      </van-form>
    </div>

    <!-- 源单选择弹窗 -->
    <van-popup v-model:show="showSourcePicker" position="bottom" :style="{ height: '70%' }"
      :close-on-click-overlay="true">
      <div class="picker-header">
        <van-nav-bar :title="sourceType === 'order' ? '选择销售订单' : '选择销售出库单'" left-text="取消"
          @click-left="closeSourcePicker" />
        <van-search v-model="sourceSearch" :placeholder="sourceType === 'order' ? '搜索订单编号/客户名称' : '搜索出库单号/客户名称'"
          @update:model-value="searchSources" />
      </div>
      <van-list v-model:loading="sourceLoading" :finished="sourceFinished" finished-text="没有更多了" @load="loadMoreSources"
        :immediate-check="false">
        <van-cell v-for="source in sourceList" :key="source.id"
          :title="sourceType === 'order' ? source.order_no : source.stock_no" :label="getSourceCellLabel(source)"
          @click="selectSource(source)" />
        <van-empty v-if="!sourceLoading && sourceList.length === 0"
          :description="sourceSearch ? '未找到相关源单' : '暂无源单数据'" />
      </van-list>
    </van-popup>

    <!-- 仓库选择弹窗 -->
    <van-popup v-model:show="showWarehousePicker" position="bottom" :style="{ height: '70%' }"
      :close-on-click-overlay="true">
      <div class="picker-header">
        <van-nav-bar title="选择退货仓库" left-text="取消" @click-left="closeWarehousePicker" />
        <van-search v-model="warehouseSearch" placeholder="搜索仓库名称" @update:model-value="searchWarehouses" />
      </div>
      <van-list v-model:loading="warehouseLoading" :finished="warehouseFinished" finished-text="没有更多了"
        @load="loadMoreWarehouses" :immediate-check="false">
        <van-cell v-for="warehouse in warehouseList" :key="warehouse.id" :title="warehouse.name"
          :label="getWarehouseCellLabel(warehouse)" @click="selectWarehouse(warehouse)" />
        <van-empty v-if="!warehouseLoading && warehouseList.length === 0"
          :description="warehouseSearch ? '未找到相关仓库' : '暂无仓库数据'" />
      </van-list>
    </van-popup>

    <!-- 日期选择器 -->
    <van-popup v-model:show="showDatePicker" position="bottom" :close-on-click-overlay="true">
      <van-date-picker v-model="selectedDate" :title="'选择退货日期'" :min-date="minDate" :max-date="maxDate"
        @confirm="onDateConfirm" @cancel="closeDatePicker" />
    </van-popup>

    <!-- 退货原因选择器 -->
    <van-action-sheet v-model:show="showReasonPicker" :actions="returnReasonOptions" @select="onReasonSelect" />

    <!-- 商品选择弹窗 -->
    <van-popup v-model:show="showSkuSelect" position="bottom" :style="{ height: '80%' }" :close-on-click-overlay="true">
      <div class="sku-picker">
        <van-nav-bar title="选择退货商品" left-text="取消" right-text="确认" @click-left="closeSkuPicker"
          @click-right="handleSkuSelectConfirm" />
        <div class="sku-picker-content">
          <div>当前选中的商品ID: {{ selectedSourceItemIds }}</div>
          <van-checkbox-group v-model="selectedSourceItemIds">
            <van-cell-group>
              <van-cell v-for="item in availableSourceItems" :key="`${item.sku_id}_${item.id}`" clickable>
                <template #title>
                  <div class="product-title">
                    <span class="product-name">{{ getProductDisplayName(item) }}</span>
                    <span class="sku-code" v-if="item.sku_code">{{ item.sku_code }}</span>
                  </div>
                </template>
                <template #label>
                  <div class="product-label">
                    <div class="spec-text" v-if="getItemSpecText(item)">规格: {{ getItemSpecText(item) }}</div>
                    <div class="stock-text">
                      源单数量: {{ item.source_quantity || 0 }}{{ item.unit }}
                      <span class="returned-info" v-if="item.returned_quantity">(已退: {{ item.returned_quantity || 0
                      }})</span>
                    </div>
                    <div class="max-return-text">
                      最多可退: <span :class="{ 'out-of-stock': item.max_return_quantity <= 0 }">{{ item.max_return_quantity
                        || 0 }}</span>{{ item.unit }}
                    </div>
                    <div>商品ID: {{ item.id }}</div>
                  </div>
                </template>
                <template #right-icon>
                  <van-checkbox :name="item.id" :disabled="item.max_return_quantity <= 0" />
                </template>
              </van-cell>
            </van-cell-group>
          </van-checkbox-group>
          <van-empty v-if="availableSourceItems.length === 0" description="没有可退货的商品" />
        </div>
      </div>
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
import { useWarehouseStore } from '@/store/modules/warehouse'
import { useReturnStore } from '@/store/modules/return'

// 路由相关
const route = useRoute()
const router = useRouter()
const isEdit = !!route.params.id

// 状态管理
const saleStore = useSaleStore()
const warehouseStore = useWarehouseStore()
const returnStore = useReturnStore()

// 组件引用
const formRef = ref(null)
const submitting = ref(false)

// 表单数据
const form = reactive({
  source_type: 'order', // order:销售订单, stock:销售出库单
  source_id: '',
  warehouse_id: '',
  warehouse_name: '',
  return_date: dayjs().format('YYYY-MM-DD'),
  return_type: '', // 退货原因类型
  return_type_text: '', // 退货原因显示文本
  remark: '',
  items: [] // 退货商品明细
})

// 源单表单
const sourceForm = reactive({
  source_id: '',
  source_name: '',
  customer_id: '',
  customer_name: '',
  order_no: '',
  items: [] // 源单商品明细
})

// 源单类型
const sourceType = ref('stock') // order:销售订单, stock:销售出库单

// 选择器状态
const showSourcePicker = ref(false)
const showWarehousePicker = ref(false)
const showDatePicker = ref(false)
const showReasonPicker = ref(false)
const showSkuSelect = ref(false)

// 源单选择相关
const sourceSearch = ref('')
const sourceList = ref([])
const sourceLoading = ref(false)
const sourceFinished = ref(false)
const sourcePage = ref(1)
const isFirstLoad = ref(false)

// 仓库选择相关
const warehouseSearch = ref('')
const warehouseList = ref([])
const warehouseLoading = ref(false)
const warehouseFinished = ref(false)
const warehousePage = ref(1)

// 商品选择相关
const selectedSourceItemIds = ref([]) // 选中的源单明细ID

// 日期相关
const selectedDate = ref([])
const minDate = new Date(2020, 0, 1)
const maxDate = new Date()

// 退货原因选项（映射到数据库的数字类型）
const returnReasonOptions = ref([
  { name: '质量问题', value: 0 },
  { name: '数量问题', value: 1 },
  { name: '客户取消', value: 2 },
  { name: '其他', value: 3 }
])

// 计算属性
const availableSourceItems = computed(() => {
  if (!sourceForm.items || sourceForm.items.length === 0) return []
  
  console.log('计算可用商品，源单商品列表:', sourceForm.items.map(item => ({ id: item.id, product_name: item.product_name })))

  return sourceForm.items.filter(item => {
    // 检查商品是否有ID
    if (!item.id) {
      console.warn('商品缺少ID:', item)
      return false
    }
    
    // 只显示还有可退数量的商品
    const maxReturnQuantity = calculateMaxReturnQuantity(item)
    const isAvailable = maxReturnQuantity > 0
    
    if (!isAvailable) {
      console.log(`商品 ${item.product_name} 不可退货，最大可退数量: ${maxReturnQuantity}`)
    }
    
    return isAvailable
  })
})

const totalReturnQuantity = computed(() => {
  return form.items.reduce((sum, item) => {
    return sum + (Number(item.return_quantity) || 0)
  }, 0)
})

const totalReturnAmount = computed(() => {
  return form.items.reduce((sum, item) => {
    const price = Number(item.unit_price) || 0
    const quantity = Number(item.return_quantity) || 0
    return sum + (price * quantity)
  }, 0)
})

// 方法 - 获取商品显示名称
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
  if (item.spec && typeof item.spec === 'string') {
    return item.spec
  }
  return ''
}

// 获取源单单元格标签
const getSourceCellLabel = (source) => {
  const customerName = source.customer_name || source.customer?.name || '无'
  const amount = source.total_amount || 0
  const statusText = getOrderStatusText(source.status)
  return `客户: ${customerName} | 金额: ¥${amount} | 状态: ${statusText}`
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
  return statusMap[status] || `未知(${status})`
}

// 获取仓库单元格标签
const getWarehouseCellLabel = (warehouse) => {
  const address = warehouse.address || '无'
  const manager = warehouse.manager || '无'
  const phone = warehouse.phone || '无'
  return `地址: ${address} | 负责人: ${manager} | 电话: ${phone}`
}

// 计算单个商品的退货金额
const getItemReturnAmount = (item) => {
  const price = Number(item.unit_price) || 0
  const quantity = Number(item.return_quantity) || 0
  return (price * quantity).toFixed(2)
}

// 计算最大可退数量
const calculateMaxReturnQuantity = (item) => {
  const sourceQuantity = Number(item.source_quantity) || 0
  const returnedQuantity = Number(item.returned_quantity) || 0
  return Math.max(0, sourceQuantity - returnedQuantity)
}

// 初始化表单数据
const initForm = async () => {
  if (isEdit) {
    // 编辑模式：加载退货详情
    const id = route.params.id
    try {
      await saleStore.loadReturnDetail(id)
      const returnDetail = saleStore.currentReturn

      if (returnDetail) {
        // 设置退货基本信息
        form.source_id = returnDetail.sale_order_id || returnDetail.sale_stock_id
        form.warehouse_id = returnDetail.warehouse_id
        form.warehouse_name = returnDetail.warehouse_name || ''
        form.return_date = returnDetail.return_date || dayjs().format('YYYY-MM-DD')
        form.return_type = returnDetail.return_type || ''

        // 获取退货原因文本
        const reasonOption = returnReasonOptions.value.find(opt => opt.value === form.return_type)
        form.return_type_text = reasonOption ? reasonOption.name : ''

        form.remark = returnDetail.remark || ''

        // 设置源单类型
        if (returnDetail.sale_order_id) {
          sourceType.value = 'order'
          await loadSourceDetail(returnDetail.sale_order_id, 'order')
        } else if (returnDetail.sale_stock_id) {
          sourceType.value = 'stock'
          await loadSourceDetail(returnDetail.sale_stock_id, 'stock')
        }

        // 设置退货商品明细
        if (returnDetail.items && Array.isArray(returnDetail.items)) {
          form.items = returnDetail.items.map(item => {
            return {
              id: item.id,
              sku_id: item.sku_id,
              product_id: item.product_id,
              product_name: item.product_name,
              sku_code: item.sku_code || '',
              spec: item.spec || {},
              unit: item.unit || '个',
              unit_price: Number(item.price) || 0,
              return_quantity: Number(item.return_quantity) || 0,
              source_item_id: item.source_item_id,
              source_quantity: item.source_quantity || 0,
              returned_quantity: item.returned_quantity || 0,
              max_return_quantity: calculateMaxReturnQuantity(item),
              quantityError: ''
            }
          })

          // 设置选中的源单明细ID
          selectedSourceItemIds.value = form.items.map(item => item.source_item_id).filter(Boolean)
        }

        // 设置日期选择器的当前值
        if (form.return_date) {
          const date = new Date(form.return_date)
          selectedDate.value = [date.getFullYear(), date.getMonth() + 1, date.getDate()]
        }
      } else {
        showFailToast('加载退货详情失败')
        router.back()
      }
    } catch (error) {
      console.error('加载退货详情失败:', error)
      showFailToast('加载退货详情失败')
      router.back()
    }
  } else {
    // 新增模式：设置默认日期
    form.return_date = dayjs().format('YYYY-MM-DD')
    selectedDate.value = [
      new Date().getFullYear(),
      new Date().getMonth() + 1,
      new Date().getDate()
    ]
  }
}

// 源单类型变化
watch(sourceType, () => {
  // 清空源单相关数据
  sourceForm.source_id = ''
  sourceForm.source_name = ''
  sourceForm.customer_id = ''
  sourceForm.customer_name = ''
  sourceForm.order_no = ''
  sourceForm.items = []
  form.items = []
  form.source_id = ''
  selectedSourceItemIds.value = []
  isFirstLoad.value = false
})

// 监听源单选择器显示/隐藏
watch(showSourcePicker, (newVal) => {
  if (newVal) {
    // 弹窗打开时，重置状态并加载第一页
    sourceList.value = []
    sourcePage.value = 1
    sourceFinished.value = false
    sourceSearch.value = ''
    isFirstLoad.value = false

    // 延迟加载，确保DOM已渲染
    nextTick(() => {
      loadSourceList(1, '', true)
    })
  }
})

// 加载源单列表
const loadSourceList = async (page = 1, keyword = '', isRefresh = false) => {
  // 防止重复请求
  if (sourceLoading.value) {
    return []
  }

  // 如果是刷新，重置状态
  if (isRefresh) {
    sourceList.value = []
    sourceFinished.value = false
    sourcePage.value = 1
    page = 1
  }

  sourceLoading.value = true

  try {
    let res
    const params = {
      page,
      limit: 20,
      keyword: keyword.trim()
    }

    // 根据源单类型添加状态参数
    if (sourceType.value === 'order') {
      // 销售订单：已审核、部分出库、已完成
      params.status = '1,2,3'
      res = await saleStore.loadOrderList(params)
    } else {
      // 销售出库单：已审核
      params.status = '1,2'
      res = await saleStore.loadStockList(params)
    }

    // 处理响应数据
    let list = []
    if (res && res.code === 200) {
      // 标准响应结构
      if (Array.isArray(res.data)) {
        list = res.data
      } else if (res.data && res.data.list && Array.isArray(res.data.list)) {
        list = res.data.list
      }
    } else if (res && res.list) {
      // 直接返回列表结构
      list = res.list
    } else if (Array.isArray(res)) {
      // 直接返回数组
      list = res
    }

    // 更新列表
    if (page === 1) {
      sourceList.value = list
      isFirstLoad.value = true
    } else {
      // 去重合并
      const existingIds = new Set(sourceList.value.map(item => item.id))
      const newItems = list.filter(item => !existingIds.has(item.id))
      sourceList.value = [...sourceList.value, ...newItems]
    }

    // 判断是否加载完成
    sourceFinished.value = list.length < 20

    // 如果第一页就没数据，直接标记完成
    if (page === 1 && list.length === 0) {
      sourceFinished.value = true
    }

    return list
  } catch (error) {
    console.error(`加载第${page}页失败:`, error)

    // 如果是第一页请求失败，重置状态
    if (page === 1) {
      sourceList.value = []
      sourceFinished.value = true
    }

    // 显示错误提示
    if (page === 1 && error.response?.status !== 500) {
      showFailToast('加载失败: ' + (error.message || '网络错误'))
    }

    return []
  } finally {
    sourceLoading.value = false
  }
}

// 搜索源单（防抖）
let searchTimer = null
const searchSources = () => {
  if (searchTimer) clearTimeout(searchTimer)
  searchTimer = setTimeout(() => {
    sourcePage.value = 1
    sourceFinished.value = false
    isFirstLoad.value = false
    loadSourceList(1, sourceSearch.value, true)
  }, 500)
}

// 加载更多源单
const loadMoreSources = () => {
  // 防止重复或无效加载
  if (sourceLoading.value || sourceFinished.value || !isFirstLoad.value) {
    return
  }

  sourcePage.value += 1
  loadSourceList(sourcePage.value, sourceSearch.value)
}

// 选择源单
const selectSource = async (source) => {
  try {
    // 加载源单详情
    const detail = await loadSourceDetail(source.id, sourceType.value)
    if (detail) {
      showSourcePicker.value = false
      sourceSearch.value = ''
      form.source_id = source.id

      // 清空之前选择的商品
      form.items = []
      selectedSourceItemIds.value = []
    }
  } catch (error) {
    console.error('选择源单失败:', error)
    showFailToast('选择源单失败')
  }
}

// 关闭源单选择器
const closeSourcePicker = () => {
  showSourcePicker.value = false
  sourceSearch.value = ''
}

// 加载源单详情
const loadSourceDetail = async (sourceId, type) => {
  try {
    let detail
    if (type === 'order') {
      // 加载销售订单详情
      detail = await saleStore.loadOrderDetail(sourceId)
      console.log('加载的销售订单详情:', detail)
      
      if (detail) {
        sourceForm.source_id = detail.id
        sourceForm.source_name = `销售订单 - ${detail.order_no}`
        sourceForm.customer_id = detail.customer_id
        sourceForm.customer_name = detail.customer?.name || detail.customer_name || ''
        sourceForm.order_no = detail.order_no

        // 自动设置退货仓库（如果订单有指定仓库）
        if (detail.warehouse_id && detail.warehouse) {
          form.warehouse_id = detail.warehouse_id
          form.warehouse_name = detail.warehouse.name || ''
        }

        // 处理订单商品明细
        if (detail.items && Array.isArray(detail.items)) {
          console.log('原始订单商品明细:', detail.items)
          sourceForm.items = detail.items.map(item => {
            const product = item.product || {}
            const sku = item.sku || {}

            return {
              id: item.id, // 确保使用后端返回的ID
              sku_id: item.sku_id,
              product_id: item.product_id,
              product: product,
              product_name: product.name || item.product_name || '',
              sku_code: sku.sku_code || '',
              spec: sku.spec || {},
              unit: sku.unit || product.unit || '个',
              unit_price: Number(item.price) || 0,
              source_quantity: Number(item.quantity) || 0,
              returned_quantity: Number(item.returned_quantity) || 0,
              max_return_quantity: calculateMaxReturnQuantity({
                source_quantity: Number(item.quantity) || 0,
                returned_quantity: Number(item.returned_quantity) || 0
              })
            }
          })
          console.log('处理后的源单商品列表:', sourceForm.items)
          
          // 检查每个商品是否有有效的ID
          sourceForm.items.forEach((item, index) => {
            if (!item.id) {
              console.warn(`商品 ${index} 缺少ID:`, item)
            }
          })
        }
      }
    } else {
      // 加载销售出库单详情
      detail = await saleStore.loadStockDetail(sourceId)
      console.log('加载的销售出库单详情:', detail)
      
      if (detail) {
        sourceForm.source_id = detail.id
        sourceForm.source_name = `销售出库单 - ${detail.stock_no}`
        sourceForm.customer_id = detail.customer_id
        sourceForm.customer_name = detail.customer?.name || detail.customer_name || ''
        sourceForm.order_no = detail.stock_no

        // 自动设置退货仓库（出库单肯定有仓库）
        if (detail.warehouse_id && detail.warehouse) {
          form.warehouse_id = detail.warehouse_id
          form.warehouse_name = detail.warehouse.name || ''
        }

        // 处理出库商品明细
        if (detail.items && Array.isArray(detail.items)) {
          console.log('原始出库单商品明细:', detail.items)
          sourceForm.items = detail.items.map(item => {
            const product = item.product || {}
            const sku = item.sku || {}

            return {
              id: item.id, // 确保使用后端返回的ID
              sku_id: item.sku_id,
              product_id: item.product_id,
              product: product,
              product_name: product.name || item.product_name || '',
              sku_code: sku.sku_code || '',
              spec: sku.spec || {},
              unit: sku.unit || product.unit || '个',
              unit_price: Number(item.price) || 0,
              source_quantity: Number(item.quantity) || 0,
              returned_quantity: Number(item.returned_quantity) || 0,
              max_return_quantity: calculateMaxReturnQuantity({
                source_quantity: Number(item.quantity) || 0,
                returned_quantity: Number(item.returned_quantity) || 0
              })
            }
          })
          console.log('处理后的源单商品列表:', sourceForm.items)
          
          // 检查每个商品是否有有效的ID
          sourceForm.items.forEach((item, index) => {
            if (!item.id) {
              console.warn(`商品 ${index} 缺少ID:`, item)
            }
          })
        }
      }
    }

    return detail
  } catch (error) {
    console.error('加载源单详情失败:', error)
    showFailToast('加载源单详情失败')
    return null
  }
}

// 监听仓库选择器显示/隐藏
watch(showWarehousePicker, (newVal) => {
  if (newVal) {
    // 弹窗打开时，重置状态并加载第一页
    warehouseList.value = []
    warehousePage.value = 1
    warehouseFinished.value = false
    warehouseSearch.value = ''

    // 延迟加载，确保DOM已渲染
    nextTick(() => {
      loadWarehouses(1, '', true)
    })
  }
})

// 加载仓库列表
const loadWarehouses = async (page = 1, keyword = '', isRefresh = false) => {
  // 防止重复请求
  if (warehouseLoading.value) {
    return []
  }

  // 如果是刷新，重置状态
  if (isRefresh) {
    warehouseList.value = []
    warehouseFinished.value = false
    warehousePage.value = 1
    page = 1
  }

  warehouseLoading.value = true

  try {
    const params = {
      page,
      limit: 20,
      keyword: keyword.trim(),
      status: 0
    }

    // 使用仓库store加载列表
    let res
    if (warehouseStore && warehouseStore.loadList) {
      res = await warehouseStore.loadList(params)
    } else {
      console.warn('仓库store未找到loadList方法')
      return []
    }

    // 处理响应数据
    let list = []
    if (res && res.code === 200) {
      // 标准响应结构
      if (Array.isArray(res.data)) {
        list = res.data
      } else if (res.data && res.data.list && Array.isArray(res.data.list)) {
        list = res.data.list
      }
    } else if (res && res.list) {
      // 直接返回列表结构
      list = res.list
    } else if (Array.isArray(res)) {
      // 直接返回数组
      list = res
    }

    // 更新列表
    if (page === 1) {
      warehouseList.value = list
    } else {
      // 去重合并
      const existingIds = new Set(warehouseList.value.map(item => item.id))
      const newItems = list.filter(item => !existingIds.has(item.id))
      warehouseList.value = [...warehouseList.value, ...newItems]
    }

    // 判断是否加载完成
    warehouseFinished.value = list.length < 20

    return list
  } catch (error) {
    console.error(`加载第${page}页仓库失败:`, error)

    // 如果是第一页请求失败，重置状态
    if (page === 1) {
      warehouseList.value = []
      warehouseFinished.value = true
    }

    // 显示错误提示
    if (page === 1) {
      showFailToast('加载仓库失败: ' + (error.message || '网络错误'))
    }

    return []
  } finally {
    warehouseLoading.value = false
  }
}

// 搜索仓库（防抖）
let warehouseSearchTimer = null
const searchWarehouses = () => {
  if (warehouseSearchTimer) clearTimeout(warehouseSearchTimer)
  warehouseSearchTimer = setTimeout(() => {
    warehousePage.value = 1
    warehouseFinished.value = false
    loadWarehouses(1, warehouseSearch.value, true)
  }, 500)
}

// 加载更多仓库
const loadMoreWarehouses = () => {
  // 防止重复或无效加载
  if (warehouseLoading.value || warehouseFinished.value) {
    return
  }

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

// 日期确认
const onDateConfirm = ({ selectedValues }) => {
  const [year, month, day] = selectedValues
  form.return_date = `${year}-${String(month).padStart(2, '0')}-${String(day).padStart(2, '0')}`
  showDatePicker.value = false
}

// 关闭日期选择器
const closeDatePicker = () => {
  showDatePicker.value = false
}

// 退货原因选择
const onReasonSelect = (action) => {
  form.return_type = action.value
  form.return_type_text = action.name
  showReasonPicker.value = false
}

// 商品选择确认
const handleSkuSelectConfirm = () => {
  console.log('选中的源单明细ID:', selectedSourceItemIds.value)
  console.log('源单商品列表:', sourceForm.items.map(item => ({ id: item.id, product_name: item.product_name })))
  
  if (selectedSourceItemIds.value.length === 0) {
    showToast('请至少选择一个商品')
    return
  }

  // 获取选中的源单明细
  const selectedItems = sourceForm.items.filter(item =>
    selectedSourceItemIds.value.includes(item.id)
  )
  
  console.log('过滤后的选中商品:', selectedItems.map(item => ({ id: item.id, product_name: item.product_name })))
  
  // 检查是否有匹配的商品
  if (selectedItems.length === 0 && selectedSourceItemIds.value.length > 0) {
    console.error('选中了商品但过滤后为空，可能是ID不匹配')
    console.error('源单商品ID列表:', sourceForm.items.map(item => item.id))
    console.error('选中的ID列表:', selectedSourceItemIds.value)
    showToast('商品选择失败，请重试')
    return
  }

  // 添加到退货商品列表
  selectedItems.forEach(sourceItem => {
    // 检查是否已存在相同SKU和源单明细ID的退货项
    const existingIndex = form.items.findIndex(item =>
      item.sku_id === sourceItem.sku_id &&
      item.source_item_id === sourceItem.id
    )

    if (existingIndex === -1) {
      // 计算最大可退数量
      const maxReturnQuantity = calculateMaxReturnQuantity(sourceItem)

      // 新增退货项
      form.items.push({
        sku_id: sourceItem.sku_id,
        product_id: sourceItem.product_id, // 确保包含product_id
        product_name: sourceItem.product_name,
        sku_code: sourceItem.sku_code,
        spec: sourceItem.spec,
        unit: sourceItem.unit,
        unit_price: sourceItem.unit_price,
        return_quantity: maxReturnQuantity > 0 ? 1 : 0, // 默认退货数量为1
        source_item_id: sourceItem.id,
        source_quantity: sourceItem.source_quantity,
        returned_quantity: sourceItem.returned_quantity,
        max_return_quantity: maxReturnQuantity,
        quantityError: ''
      })
    }
  })

  showSkuSelect.value = false
  showSuccessToast(`已添加 ${selectedItems.length} 个商品`)

  // 清空选中的源单明细ID
  selectedSourceItemIds.value = []
}

// 关闭商品选择器
const closeSkuPicker = () => {
  showSkuSelect.value = false
  // 清空选中的商品ID
  selectedSourceItemIds.value = []
}

// 删除退货商品
const deleteReturnItem = (index) => {
  // 从选中的源单明细ID中移除
  const sourceItemId = form.items[index].source_item_id
  const itemIndex = selectedSourceItemIds.value.indexOf(sourceItemId)
  if (itemIndex > -1) {
    selectedSourceItemIds.value.splice(itemIndex, 1)
  }

  form.items.splice(index, 1)
}

// 验证退货数量
const validateReturnQuantity = (item, index) => {
  const quantity = Number(item.return_quantity) || 0
  const maxQuantity = item.max_return_quantity || 0

  if (isNaN(quantity) || quantity <= 0) {
    item.quantityError = '退货数量必须大于0'
    return false
  }

  if (quantity > maxQuantity) {
    item.quantityError = `退货数量不能超过${maxQuantity}`
    return false
  }

  item.quantityError = ''
  return true
}

// 更新商品金额
const updateItemAmount = (item) => {
  // 触发响应式更新
  // 计算属性会自动更新
}

// 验证表单
const validateForm = () => {
  // 验证必填字段
  if (!form.source_id) {
    showToast('请选择退货源单')
    return false
  }

  if (!sourceForm.customer_id) {
    showToast('请选择退货客户')
    return false
  }

  if (!form.warehouse_id) {
    showToast('请选择退货仓库')
    return false
  }

  if (!form.return_date) {
    showToast('请选择退货日期')
    return false
  }

  if (!form.return_type) {
    showToast('请选择退货原因')
    return false
  }

  if (form.items.length === 0) {
    showToast('请至少添加一个退货商品')
    return false
  }

  // 验证每个商品的退货数量
  for (const item of form.items) {
    const quantity = Number(item.return_quantity) || 0
    const maxQuantity = item.max_return_quantity || 0

    if (isNaN(quantity) || quantity <= 0) {
      showToast(`请检查商品"${getProductDisplayName(item)}"的退货数量`)
      return false
    }

    if (quantity > maxQuantity) {
      showToast(`商品"${getProductDisplayName(item)}"的退货数量不能超过${maxQuantity}`)
      return false
    }

    // 验证product_id是否存在
    if (!item.product_id) {
      showToast(`商品"${getProductDisplayName(item)}"缺少产品ID，请重新选择商品`)
      return false
    }

    // 验证sku_id是否存在
    if (!item.sku_id) {
      showToast(`商品"${getProductDisplayName(item)}"缺少SKU ID，请重新选择商品`)
      return false
    }
  }

  return true
}

// 构建提交数据
const buildSubmitData = () => {
  // 根据源单类型确定source_order_id和source_stock_id
  const submitData = {
    type: 0, // 销售退货类型（数据库定义：0-销售退货 1-采购退货）
    target_id: sourceForm.customer_id, // 客户ID
    warehouse_id: form.warehouse_id,
    return_date: form.return_date,
    return_type: form.return_type, // 退货原因类型（数字）
    return_reason: form.return_type_text, // 退货原因描述
    remark: form.remark || '',
    items: []
  }

  // 根据源单类型设置不同的source字段
  if (sourceType.value === 'order') {
    submitData.source_order_id = form.source_id
    submitData.source_stock_id = null
  } else {
    submitData.source_order_id = null
    submitData.source_stock_id = form.source_id
  }

  // 构建items数组，确保包含所有必填字段
  submitData.items = form.items.map(item => {
    const itemData = {
      sku_id: Number(item.sku_id),
      product_id: Number(item.product_id),
      return_quantity: Number(item.return_quantity),
      price: Number(item.unit_price)
    }

    // 如果有源单明细ID，也传过去
    if (item.source_item_id) {
      itemData.source_item_id = item.source_item_id
    }

    return itemData
  })

  // 添加调试信息
  console.log('=== 退货单提交数据详情 ===')
  console.log('基础信息:', {
    退货类型: submitData.type === 0 ? '销售退货' : '采购退货',
    客户ID: submitData.target_id,
    仓库ID: submitData.warehouse_id,
    退货日期: submitData.return_date,
    退货原因类型: submitData.return_type,
    退货原因描述: submitData.return_reason
  })

  console.log('源单信息:', {
    源单类型: sourceType.value,
    源单ID: form.source_id,
    销售订单ID: submitData.source_order_id,
    销售出库单ID: submitData.source_stock_id
  })

  console.log('商品明细:', submitData.items.map(item => ({
    SKU_ID: item.sku_id,
    产品ID: item.product_id,
    退货数量: item.return_quantity,
    单价: item.price,
    源单明细ID: item.source_item_id || '无'
  })))

  console.log('总退货数量:', totalReturnQuantity.value)
  console.log('总退货金额:', totalReturnAmount.value.toFixed(2))
  console.log('=======================')

  return submitData
}

// 表单提交
const handleSubmit = async () => {
  console.log('=== 开始提交退货单 ===')

  if (submitting.value) {
    return
  }

  if (!validateForm()) {
    console.log('表单验证失败')
    return
  }

  submitting.value = true

  try {
    const submitData = buildSubmitData()
    console.log('组件构建的提交数据:', JSON.stringify(submitData, null, 2))

    console.log('调用store的addReturn方法...')
    const result = await saleStore.addReturn(submitData)

    console.log('Store返回结果:', result)

    if (result && result.code === 200) {
      showSuccessToast('销售退货单创建成功')

      // 如果后端返回了退货单ID，跳转到详情页
      if (result.data && result.data.id) {
        const returnId = result.data.id
        setTimeout(() => {
          router.push(`/sale/return/detail/${returnId}`)
        }, 1500)
      } else if (result.data && Array.isArray(result.data) && result.data[0] && result.data[0].id) {
        // 如果返回的是数组，取第一个
        const returnId = result.data[0].id
        setTimeout(() => {
          router.push(`/sale/return/detail/${returnId}`)
        }, 1500)
      } else {
        // 如果后端没有返回ID，跳转到列表页并刷新列表
        console.log('后端未返回退货单ID，跳转到列表页')
        setTimeout(async () => {
          // 刷新退货单列表
          await saleStore.loadReturnList({ page: 1, limit: 10 })
          router.push('/sale/return')
        }, 1500)
      }
    } else {
      const errorMsg = result?.msg || result?.message || '保存失败'
      console.error('保存失败:', errorMsg)
      throw new Error(errorMsg)
    }
  } catch (error) {
    console.error('保存失败详情:', error)
    console.error('错误堆栈:', error.stack)

    // 显示错误信息
    const errorMessage = error.message || '保存失败，请稍后重试'
    showFailToast(errorMessage)
  } finally {
    submitting.value = false
  }
}

// 返回上一页
const handleBack = () => {
  // 检查表单是否有内容
  const hasChanges = form.items.length > 0 ||
    form.warehouse_id ||
    form.remark ||
    form.return_type ||
    form.source_id

  if (hasChanges) {
    showConfirmDialog({
      title: '提示',
      message: '表单内容已修改，是否放弃保存？'
    }).then(() => {
      router.back()
    }).catch(() => {
      // 用户取消返回
      console.log('用户取消返回')
    })
  } else {
    router.back()
  }
}

onMounted(async () => {
  console.log('=== 初始化退货单表单 ===')

  try {
    await initForm()

    // 设置日期选择器初始值
    if (!selectedDate.value.length && form.return_date) {
      const date = new Date(form.return_date)
      selectedDate.value = [date.getFullYear(), date.getMonth() + 1, date.getDate()]
    }

    console.log('表单初始化完成:', {
      编辑模式: isEdit,
      表单数据: form,
      源单数据: sourceForm
    })
  } catch (error) {
    console.error('表单初始化失败:', error)
    showFailToast('页面初始化失败')
  }
})
</script>

<style scoped lang="scss">
.sale-return-form {
  background-color: #f7f8fa;
  min-height: 100vh;
  padding-top: 46px;
}

.form-container {
  padding: 16px;
  background-color: #f7f8fa;
}

.source-section,
.return-section,
.sku-section {
  margin: 16px 0;
  background-color: #fff;
  border-radius: 8px;
  overflow: hidden;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
}

.section-header {
  padding: 12px 16px;
  border-bottom: 1px solid #f0f0f0;
  background-color: #fafafa;

  h3 {
    margin: 0;
    font-size: 15px;
    font-weight: 600;
    color: #323233;
  }
}

.source-type-group {
  padding: 12px 16px;
  border-bottom: 1px solid #f0f0f0;

  :deep(.van-radio) {
    margin-right: 16px;

    .van-radio__label {
      margin-left: 4px;
    }
  }

  .radio-label {
    font-size: 13px;
    line-height: 1.4;

    .radio-desc {
      font-size: 11px;
      color: #969799;
    }
  }
}

.sku-section {
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
    margin-bottom: 2px;

    .returned-info {
      color: #969799;
      margin-left: 4px;
    }
  }

  .max-return-text {
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

      :deep(.van-field__body) {
        min-height: auto;
      }

      :deep(.van-field__control) {
        font-size: 13px;
        font-weight: 500;
        color: #323233;
        text-align: center;
        padding: 0 4px;
      }

      :deep(.van-field__extra) {
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

        :deep(.van-field__control) {
          font-size: 12px;
        }
      }
    }

    .readonly-field {
      border: 1px solid #f0f0f0;
      border-radius: 4px;
      background: #fafafa;
      height: 32px;

      :deep(.van-field__control) {
        font-size: 13px;
        font-weight: 500;
        color: #646566;
        text-align: center;
        padding: 0 4px;
      }

      &.compact-field {
        width: 80px;
      }
    }

    &.price-field {

      .editable-field,
      .readonly-field {
        width: 85px;
      }
    }

    &.quantity-field {

      .editable-field,
      .readonly-field {
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

// SKU选择器样式
.sku-picker {
  height: 100%;
  display: flex;
  flex-direction: column;

  .sku-picker-content {
    flex: 1;
    overflow-y: auto;
    background-color: #fff;
    padding-bottom: 20px;
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
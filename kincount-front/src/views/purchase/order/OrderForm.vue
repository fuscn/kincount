<!-- src/views/purchase/order/OrderForm.vue -->
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
          v-model="form.supplier_name"
          name="supplier"
          label="供应商"
          placeholder="请选择供应商"
          is-link
          readonly
          @click="showSupplierPicker = true"
          :rules="[{ required: true, message: '请选择供应商' }]"
        />
        <!-- 仓库选择 -->
        <van-field
          v-model="form.warehouse_name"
          name="warehouse"
          label="入库仓库"
          placeholder="请选择仓库"
          is-link
          readonly
          @click="showWarehousePicker = true"
          :rules="[{ required: true, message: '请选择仓库' }]"
        />
        <!-- 预计到货日期 -->
        <van-field
          v-model="form.expected_date"
          name="expected_date"
          label="预计到货"
          placeholder="请选择日期"
          is-link
          readonly
          @click="showDatePicker = true"
          :rules="[{ required: true, message: '请选择预计到货日期' }]"
        />
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
            <van-button 
              size="small" 
              type="primary" 
              @click="showSkuSelect = true"
            >
              添加商品
            </van-button>
          </div>
          <!-- SKU列表 -->
          <van-empty 
            v-if="form.items.length === 0" 
            description="请添加采购商品" 
          />
          <van-cell-group v-else>
            <van-swipe-cell 
              v-for="(item, index) in form.items" 
              :key="item.sku_id + '_' + index"
            >
              <van-cell>
                <template #title>
                  <div class="product-title">
                    <span class="product-name">{{ getProductDisplayName(item) }}</span>
                    <span class="sku-code">{{ item.sku_code }}</span>
                  </div>
                </template>
                <template #label>
                  <div class="product-label">
                    <div class="spec-text" v-if="getItemSpecText(item)">规格: {{ getItemSpecText(item) }}</div>
                    <!-- <div class="unit-text">单位: {{ item.unit || '个' }}</div> -->
                  </div>
                </template>
                <template #default>
                  <div class="item-details">
                    <div class="price-quantity">
                      <van-field
                        v-model.number="item.price"
                        type="number"
                        placeholder="单价"
                        style="width: 100px"
                        @blur="validatePrice(item)"
                        :error-message="item.priceError"
                      >
                        <template #extra>元</template>
                      </van-field>
                      <van-field
                        v-model.number="item.quantity"
                        type="number"
                        placeholder="数量"
                        style="width: 100px"
                        @blur="validateQuantity(item)"
                        :error-message="item.quantityError"
                      >
                        <template #extra>{{ item.unit || '个' }}</template>
                      </van-field>
                    </div>
                    <div class="item-total">
                      ¥{{ ((item.price || 0) * (item.quantity || 0)).toFixed(2) }}
                    </div>
                  </div>
                </template>
              </van-cell>
              <template #right>
                <van-button 
                  square 
                  type="danger" 
                  text="删除" 
                  @click="deleteSku(index)" 
                />
              </template>
            </van-swipe-cell>
          </van-cell-group>
        </div>
        <!-- 合计金额 -->
        <div class="total-amount" v-if="form.items.length > 0">
          <span>订单总金额：</span>
          <span class="amount">¥{{ totalAmount.toFixed(2) }}</span>
        </div>
      </van-form>
    </div>
    <!-- 供应商选择弹窗 -->
    <van-popup 
      v-model:show="showSupplierPicker" 
      position="bottom"
      :style="{ height: '70%' }"
      :close-on-click-overlay="true"
    >
      <div class="picker-header">
        <van-nav-bar
          title="选择供应商"
          left-text="取消"
          @click-left="closeSupplierPicker"
        />
        <van-search
          v-model="supplierSearch"
          placeholder="搜索供应商名称"
          @update:model-value="searchSuppliers"
        />
      </div>
      <van-list
        v-model:loading="supplierLoading"
        :finished="supplierFinished"
        finished-text="没有更多了"
        @load="loadMoreSuppliers"
      >
        <van-cell
          v-for="supplier in supplierList"
          :key="supplier.id"
          :title="supplier.name"
          :label="`联系人: ${supplier.contact_person || '无'} | 电话: ${supplier.phone || '无'}`"
          @click="selectSupplier(supplier)"
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
    <!-- 日期选择器 -->
    <van-popup 
      v-model:show="showDatePicker" 
      position="bottom"
      :close-on-click-overlay="true"
    >
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
    <van-popup 
      v-model:show="showSkuSelect" 
      position="bottom"
      :style="{ height: '80%' }"
      :close-on-click-overlay="true"
    >
      <div class="popup-header">
        <van-nav-bar
          title="选择采购商品"
          left-text="取消"
          right-text="确定"
          @click-left="closeSkuPicker"
          @click-right="handleSkuConfirm"
        />
      </div>
      <SkuSelect
        ref="skuSelectRef"
        v-model="selectedSkuIds"
        :show-actions="true"
        :show-footer="true"
        @confirm="handleSkuSelectConfirm"
        @cancel="closeSkuPicker"
      />
    </van-popup>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { 
  showToast, 
  showConfirmDialog,
  showSuccessToast 
} from 'vant'
import { usePurchaseStore } from '@/store/modules/purchase'
import { useSupplierStore } from '@/store/modules/supplier'
import { useWarehouseStore } from '@/store/modules/warehouse'
import { getSkuDetail } from '@/api/product'
import { SkuSelect } from '@/components'

// 路由相关
const route = useRoute()
const router = useRouter()
const isEdit = !!route.params.id

// 状态管理
const purchaseStore = usePurchaseStore()
const supplierStore = useSupplierStore()
const warehouseStore = useWarehouseStore()

// 组件引用
const formRef = ref(null)
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
const showSupplierPicker = ref(false)
const showWarehousePicker = ref(false)
const showDatePicker = ref(false)
const showSkuSelect = ref(false)

// 供应商选择相关
const supplierSearch = ref('')
const supplierList = ref([])
const supplierLoading = ref(false)
const supplierFinished = ref(false)
const supplierPage = ref(1)

// 仓库选择相关
const warehouseSearch = ref('')
const warehouseList = ref([])
const warehouseLoading = ref(false)
const warehouseFinished = ref(false)
const warehousePage = ref(1)

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

// 获取商品显示名称 - 修复版本
const getProductDisplayName = (item) => {
  // 根据后端数据结构，优先从 product 对象获取名称
  if (item.product && item.product.name) {
    return item.product.name
  }
  // 其次从 sku_name 获取
  if (item.sku_name) {
    return item.sku_name
  }
  // 然后从 product_name 获取
  if (item.product_name) {
    return item.product_name
  }
  // 最后从 sku 的其他字段获取
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
  return ''
}

// 从SKU数据获取商品名称 - 新增方法
const getSkuProductName = (sku) => {
  // 根据后端数据结构，优先从 product 对象获取名称
  if (sku.product && sku.product.name) {
    return sku.product.name
  }
  // 其次从 product_name 获取
  if (sku.product_name) {
    return sku.product_name
  }
  // 最后从 name 字段获取
  return sku.name || '未知商品'
}

// 初始化表单数据
const initForm = async () => {
  if (isEdit) {
    // 编辑模式：加载订单详情
    const id = route.params.id
    const orderDetail = await purchaseStore.loadOrderDetail(id)
    if (orderDetail) {
      form.supplier_id = orderDetail.supplier_id
      form.supplier_name = orderDetail.supplier?.name || ''
      form.warehouse_id = orderDetail.warehouse_id
      form.warehouse_name = orderDetail.warehouse?.name || ''
      form.expected_date = orderDetail.expected_date
      form.remark = orderDetail.remark
      // 转换订单明细格式 - 修复版本
      form.items = purchaseStore.currentOrderItems.map(item => {
        const sku = item.sku || {}
        const product = sku.product || {}
        return {
          sku_id: item.sku_id,
          sku_code: sku.sku_code || '',
          product: product, // 保存完整的product对象
          product_name: product.name || '',
          sku_name: sku.name || '',
          spec_text: sku.spec_text || '',
          spec: sku.spec || {},
          unit: sku.unit || '个',
          price: item.price,
          quantity: item.quantity,
          priceError: '',
          quantityError: ''
        }
      })
      // 设置选中的SKU ID
      selectedSkuIds.value = form.items.map(item => item.sku_id)
    } else {
      showToast('加载订单详情失败')
      router.back()
    }
  } else {
    // 新增模式：设置默认日期为明天
    const tomorrow = getTomorrowDate()
    form.expected_date = formatDate(tomorrow)
    currentDate.value = [tomorrow.getFullYear(), tomorrow.getMonth() + 1, tomorrow.getDate()]
  }
}

// 加载供应商列表
const loadSuppliers = async (page = 1, keyword = '') => {
  supplierLoading.value = true
  try {
    const params = {
      page,
      limit: 20,
      keyword,
      status: 1 // 只加载启用状态的供应商
    }
    const res = await supplierStore.loadList(params)
    console.log('供应商加载结果:', res)
    let list = []
    if (res && res.list) {
      list = res.list
    } else if (Array.isArray(res)) {
      list = res
    } else {
      list = supplierStore.list || []
    }
    if (page === 1) {
      supplierList.value = list
    } else {
      supplierList.value = [...supplierList.value, ...list]
    }
    supplierFinished.value = list.length < 20
    return list
  } catch (error) {
    console.error('加载供应商列表失败:', error)
    showToast('加载供应商失败')
    return []
  } finally {
    supplierLoading.value = false
  }
}

// 搜索供应商
const searchSuppliers = () => {
  supplierPage.value = 1
  supplierFinished.value = false
  loadSuppliers(1, supplierSearch.value)
}

// 加载更多供应商
const loadMoreSuppliers = () => {
  if (supplierFinished.value) return
  supplierPage.value += 1
  loadSuppliers(supplierPage.value, supplierSearch.value)
}

// 选择供应商
const selectSupplier = (supplier) => {
  safeUpdate(() => {
    form.supplier_id = supplier.id
    form.supplier_name = supplier.name
    showSupplierPicker.value = false
    supplierSearch.value = ''
  })
}

// 关闭供应商选择器
const closeSupplierPicker = () => {
  safeUpdate(() => {
    showSupplierPicker.value = false
    supplierSearch.value = ''
  })
}

// 加载仓库列表
const loadWarehouses = async (page = 1, keyword = '') => {
  warehouseLoading.value = true
  try {
    const params = {
      page,
      limit: 20,
      keyword,
      status: 1 // 只加载启用状态的仓库
    }
    const res = await warehouseStore.loadList(params)
    console.log('仓库加载结果:', res)
    let list = []
    if (res && res.list) {
      list = res.list
    } else if (Array.isArray(res)) {
      list = res
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
    console.error('加载仓库列表失败:', error)
    showToast('加载仓库失败')
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
  safeUpdate(() => {
    form.warehouse_id = warehouse.id
    form.warehouse_name = warehouse.name
    showWarehousePicker.value = false
    warehouseSearch.value = ''
  })
}

// 关闭仓库选择器
const closeWarehousePicker = () => {
  safeUpdate(() => {
    showWarehousePicker.value = false
    warehouseSearch.value = ''
  })
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

// SKU选择确认
const handleSkuConfirm = () => {
  // 直接触发确认事件，而不是调用子组件的方法
  if (skuSelectRef.value) {
    // 获取选中的SKU数据
    const selectedData = skuSelectRef.value.getSelectedData()
    handleSkuSelectConfirm({
      selectedIds: selectedSkuIds.value,
      selectedData: selectedData
    })
  }
}

// SKU选择结果处理 - 修复版本
const handleSkuSelectConfirm = async (result) => {
  safeUpdate(async () => {
    const { selectedIds, selectedData } = result
    console.log('选中的SKU数据:', selectedData)
    
    // 获取SKU详细信息并添加到表单
    for (const sku of selectedData) {
      try {
        const skuDetail = await getSkuDetail(sku.id)
        console.log('SKU详情:', skuDetail)
        
        // 检查是否已存在相同SKU
        const existingIndex = form.items.findIndex(item => item.sku_id === sku.id)
        if (existingIndex > -1) {
          // 已存在，更新数量（+1）
          form.items[existingIndex].quantity = (Number(form.items[existingIndex].quantity) || 0) + 1
        } else {
          // 新增SKU - 修复数据结构
          const newItem = {
            sku_id: sku.id,
            sku_code: sku.sku_code || skuDetail.sku_code,
            product: sku.product || skuDetail.product || null, // 保存product对象
            product_name: getSkuProductName(sku) || getSkuProductName(skuDetail),
            sku_name: sku.name || skuDetail.name,
            spec_text: sku.spec_text || skuDetail.spec_text,
            spec: sku.spec || skuDetail.spec || {},
            unit: sku.unit || skuDetail.unit || '个',
            price: sku.cost_price || skuDetail.cost_price || 0,
            quantity: 1,
            priceError: '',
            quantityError: ''
          }
          console.log('新增商品项:', newItem)
          form.items.push(newItem)
        }
      } catch (error) {
        console.error('获取SKU详情失败:', error)
        // 即使获取详情失败，也添加基本信息
        const existingIndex = form.items.findIndex(item => item.sku_id === sku.id)
        if (existingIndex === -1) {
          const newItem = {
            sku_id: sku.id,
            sku_code: sku.sku_code,
            product: sku.product || null,
            product_name: getSkuProductName(sku),
            sku_name: sku.name,
            spec_text: sku.spec_text,
            spec: sku.spec || {},
            unit: sku.unit || '个',
            price: sku.cost_price || 0,
            quantity: 1,
            priceError: '',
            quantityError: ''
          }
          console.log('新增商品项(无详情):', newItem)
          form.items.push(newItem)
        }
      }
    }
    console.log('最终商品列表:', form.items)
    showSkuSelect.value = false
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
      items: form.items.map(item => ({
        sku_id: item.sku_id,
        quantity: Number(item.quantity),
        price: Number(item.price)
      }))
    }
    if (isEdit) {
      // 编辑订单
      await purchaseStore.updateOrder(route.params.id, submitData)
      showSuccessToast('更新成功')
    } else {
      // 创建新订单
      const newOrder = await purchaseStore.addOrder(submitData)
      showSuccessToast('创建成功')
      if (newOrder?.id) {
        // 创建成功后跳转到详情页
        router.push(`/purchase/order/detail/${newOrder.id}`)
        return
      }
    }
    // 操作成功后返回列表页
    router.push('/purchase/order')
  } catch (error) {
    console.error('提交订单失败:', error)
    showToast(error.message || '操作失败')
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
  // 预加载供应商和仓库列表
  await loadSuppliers()
  await loadWarehouses()
})
</script>

<style scoped lang="scss">
.purchase-order-form {
  background-color: #f7f8fa;
  min-height: 100vh;
  padding-top: 46px; // 为固定导航栏留出空间
}

.form-container {
  padding: 16px;
}

.sku-section {
  margin: 16px 0;
  background-color: #fff;
  border-radius: 8px;
  overflow: hidden;
}

.section-title {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 12px 16px;
  border-bottom: 1px solid #eee;
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
}

.sku-code {
  color: #646566;
  font-size: 13px;
  font-weight: normal;
}

.product-label {
  font-size: 12px;
  color: #969799;
}

.spec-text {
  margin-bottom: 2px;
}

.unit-text {
  color: #646566;
}

.item-details {
  display: flex;
  justify-content: space-between;
  align-items: center;
  width: 100%;
}

.price-quantity {
  display: flex;
  gap: 8px;
  align-items: center;
  :deep(.van-field) {
    padding: 4px 8px;
    .van-field__body {
      min-height: auto;
    }
  }
}

.item-total {
  color: #f53f3f;
  font-weight: bold;
  font-size: 14px;
  min-width: 80px;
  text-align: right;
}

.total-amount {
  padding: 16px;
  text-align: right;
  font-size: 16px;
  background-color: #fff;
  border-radius: 8px;
  margin-bottom: 16px;
  border: 1px solid #ebedf0;
  .amount {
    color: #f53f3f;
    font-weight: bold;
    font-size: 18px;
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

.popup-header {
  background: white;
  .van-nav-bar {
    background: white;
  }
}

:deep(.van-swipe-cell) {
  .van-swipe-cell__wrapper {
    padding: 8px 0;
  }
  .van-cell {
    padding: 12px 16px;
  }
}

// SKU选择组件样式调整
:deep(.sku-select-component) {
  height: calc(100% - 46px); // 减去导航栏高度
  .sku-list-container {
    height: calc(100% - 120px); // 为底部操作栏留出空间
  }
}
</style>
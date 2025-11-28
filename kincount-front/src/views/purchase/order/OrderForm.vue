<template>
  <div class="purchase-order-form">
    <van-nav-bar :title="isEdit ? '编辑采购订单' : '新增采购订单'" fixed placeholder left-text="取消" right-text="保存"
      @click-left="handleBack" @click-right="handleSubmit" />
    <div class="form-container">
      <!-- 订单基本信息 -->
      <van-form ref="formRef" @submit="handleSubmit">
        <!-- 供应商选择 -->
        <van-field v-model="form.supplier_name" name="supplier" label="供应商" placeholder="请选择供应商" is-link readonly
          @click="showSupplierPicker = true" :rules="[{ required: true, message: '请选择供应商' }]" />
        <!-- 仓库选择 -->
        <van-field v-model="form.warehouse_name" name="warehouse" label="入库仓库" placeholder="请选择仓库" is-link readonly
          @click="showWarehousePicker = true" :rules="[{ required: true, message: '请选择仓库' }]" />
        <!-- 预计到货日期 -->
        <van-field v-model="form.expected_date" name="expected_date" label="预计到货" placeholder="请选择日期" is-link readonly
          @click="showDatePicker = true" :rules="[{ required: true, message: '请选择预计到货日期' }]" />
        <!-- 备注 -->
        <van-field v-model="form.remark" name="remark" label="备注" type="textarea" placeholder="请输入备注信息" rows="3" />
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
                  </div>
                </template>
                <template #default>
                  <div class="item-details">
                    <div class="price-quantity">
                      <!-- 修改价格输入框 -->
                      <div class="input-field price-field">
                        <van-field 
                          v-model.number="item.price" 
                          type="number" 
                          placeholder="0.00" 
                          class="editable-field compact-field"
                          @blur="validatePrice(item)" 
                          :error-message="item.priceError">
                          <template #extra>元</template>
                        </van-field>
                      </div>
                      <!-- 修改数量输入框 -->
                      <div class="input-field quantity-field">
                        <van-field 
                          v-model.number="item.quantity" 
                          type="number" 
                          placeholder="0" 
                          class="editable-field compact-field"
                          @blur="validateQuantity(item)" 
                          :error-message="item.quantityError">
                          <template #extra>{{ item.unit || '个' }}</template>
                        </van-field>
                      </div>
                    </div>
                    <div class="item-total">
                      <div class="total-amount">¥{{ ((item.price || 0) * (item.quantity || 0)).toFixed(2) }}</div>
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
          <span>订单总金额：</span>
          <span class="amount">¥{{ totalAmount.toFixed(2) }}</span>
        </div>
      </van-form>
    </div>
    <!-- 供应商选择弹窗 -->
    <van-popup v-model:show="showSupplierPicker" position="bottom" :style="{ height: '70%' }"
      :close-on-click-overlay="true">
      <div class="picker-header">
        <van-nav-bar title="选择供应商" left-text="取消" @click-left="closeSupplierPicker" />
        <van-search v-model="supplierSearch" placeholder="搜索供应商名称" @update:model-value="searchSuppliers" />
      </div>
      <van-list v-model:loading="supplierLoading" :finished="supplierFinished" finished-text="没有更多了"
        @load="loadMoreSuppliers">
        <van-cell v-for="supplier in supplierList" :key="supplier.id" :title="supplier.name"
          :label="`联系人: ${supplier.contact_person || '无'} | 电话: ${supplier.phone || '无'}`"
          @click="selectSupplier(supplier)" />
      </van-list>
    </van-popup>
    <!-- 仓库选择弹窗 -->
    <van-popup v-model:show="showWarehousePicker" position="bottom" :style="{ height: '70%' }"
      :close-on-click-overlay="true">
      <div class="picker-header">
        <van-nav-bar title="选择仓库" left-text="取消" @click-left="closeWarehousePicker" />
        <van-search v-model="warehouseSearch" placeholder="搜索仓库名称" @update:model-value="searchWarehouses" />
      </div>
      <van-list v-model:loading="warehouseLoading" :finished="warehouseFinished" finished-text="没有更多了"
        @load="loadMoreWarehouses">
        <van-cell v-for="warehouse in warehouseList" :key="warehouse.id" :title="warehouse.name"
          :label="`地址: ${warehouse.address || '无'} | 负责人: ${warehouse.manager || '无'} | 电话: ${warehouse.phone || '无'}`"
          @click="selectWarehouse(warehouse)" />
      </van-list>
    </van-popup>
    <!-- 日期选择器 -->
    <van-popup v-model:show="showDatePicker" position="bottom" :close-on-click-overlay="true">
      <van-date-picker v-model="currentDate" title="选择预计到货日期" :min-date="minDate" :max-date="maxDate"
        @confirm="onDateConfirm" @cancel="closeDatePicker" />
    </van-popup>
    <!-- SKU选择弹窗 -->
    <van-popup v-model:show="showSkuSelect" position="bottom" :style="{ height: '80%' }" :close-on-click-overlay="true">
      <SkuSelect ref="skuSelectRef" v-model="selectedSkuIds" :show-header="true" :show-footer="false"
        header-title="选择采购商品" @confirm="handleSkuSelectConfirm" @cancel="closeSkuPicker" />
    </van-popup>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import {
  showToast,
  showConfirmDialog,
  showSuccessToast,
  showFailToast
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
  // 其次从 product_name 获取
  if (item.product_name) {
    return item.product_name
  }
  // 然后从 sku_name 获取
  if (item.sku_name) {
    return item.sku_name
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

// 初始化表单数据 - 修复编辑模式数据回显
const initForm = async () => {
  if (isEdit) {
    // 编辑模式：加载订单详情
    const id = route.params.id
    try {
      const orderDetail = await purchaseStore.loadOrderDetail(id)
      console.log('订单详情数据:', orderDetail) // 调试日志
      
      if (orderDetail) {
        form.supplier_id = orderDetail.supplier_id
        form.supplier_name = orderDetail.supplier?.name || ''
        form.warehouse_id = orderDetail.warehouse_id
        form.warehouse_name = orderDetail.warehouse?.name || ''
        form.expected_date = orderDetail.expected_date
        form.remark = orderDetail.remark || ''
        
        // 修复：直接从 orderDetail.items 获取数据
        // 根据后端返回的数据结构调整
        form.items = (orderDetail.items || []).map(item => {
          const product = item.product || {}
          console.log('订单项数据:', item) // 调试日志
          
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
            id: item.id, // 订单项ID，用于更新
            sku_id: item.sku_id,
            product_id: item.product_id,
            sku_code: product.product_no || '', // 使用商品编码
            product: product,
            product_name: product.name || '',
            sku_name: product.name || '', // 使用商品名称
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
        console.log('表单items数据:', form.items) // 调试日志
        console.log('选中的SKU IDs:', selectedSkuIds.value) // 调试日志
        
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

// 加载供应商列表
const loadSuppliers = async (page = 1, keyword = '') => {
  supplierLoading.value = true
  try {
    const params = {
      page,
      limit: 20,
      keyword,
      status: 1
    }
    const res = await supplierStore.loadList(params)

    // 适配标准响应结构：从 res.data 中获取数据
    let list = []
    if (res && res.code === 200) {
      // 标准响应结构
      if (Array.isArray(res.data)) {
        list = res.data
      } else if (res.data && res.data.list && Array.isArray(res.data.list)) {
        // 如果 data 是分页对象
        list = res.data.list
      }
    } else if (Array.isArray(res)) {
      // 兼容直接返回数组的情况
      list = res
    } else if (res && res.list) {
      // 兼容旧结构
      list = res.list
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
    console.error('加载供应商失败:', error)
    showFailToast('加载供应商失败')
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
      status: 1
    }
    const res = await warehouseStore.loadList(params)

    // 适配标准响应结构：从 res.data 中获取数据
    let list = []
    if (res && res.code === 200) {
      // 标准响应结构
      if (Array.isArray(res.data)) {
        list = res.data
      } else if (res.data && res.data.list && Array.isArray(res.data.list)) {
        // 如果 data 是分页对象
        list = res.data.list
      }
    } else if (Array.isArray(res)) {
      // 兼容直接返回数组的情况
      list = res
    } else if (res && res.list) {
      // 兼容旧结构
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

// SKU选择结果处理 - 修复版本
const handleSkuSelectConfirm = async (result) => {
  safeUpdate(async () => {
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
          form.items[existingIndex].quantity = (Number(form.items[existingIndex].quantity) || 0) + 1
        } else {
          // 新增SKU - 修复数据结构，确保包含 product_id
          const newItem = {
            sku_id: sku.id,
            product_id: sku.product_id, // 确保包含 product_id
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
        }
      } catch (error) {
        console.error('添加SKU失败:', error)
      }
    }

    showSkuSelect.value = false
    showSuccessToast(`已添加 ${selectedData.length} 个商品`)
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
    // 准备提交数据 - 根据后端数据结构调整
    const submitData = {
      supplier_id: form.supplier_id,
      warehouse_id: form.warehouse_id,
      expected_date: form.expected_date,
      remark: form.remark,
      items: form.items.map(item => {
        // 确保包含必要字段
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

    console.log('提交数据:', submitData) // 调试日志

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
    // 显示具体的错误信息，而不是通用的"操作失败"
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
}

.total-section {
  padding: 12px 16px;
  text-align: right;
  font-size: 15px;
  background-color: #f8f9fa;
  border-radius: 8px;
  margin: 16px 0;
  border: 1px solid #e9ecef;
  display: flex;
  justify-content: space-between;
  align-items: center;

  .amount {
    color: #f53f3f;
    font-weight: bold;
    font-size: 16px;
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
  height: calc(100% - 46px); // 减去导航栏高度

  .sku-list-container {
    height: calc(100% - 120px); // 为底部操作栏留出空间
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
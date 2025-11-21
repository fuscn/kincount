<template>
  <div class="product-skus-page">
    <van-nav-bar
      title="SKU管理"
      left-text="返回"
      right-text="新增SKU"
      left-arrow
      @click-left="router.back()"
      @click-right="onAdd"
    />

    <!-- 调试信息 -->
    <div class="debug-info" v-if="debugMode">
      <van-cell-group title="调试信息">
        <van-cell title="产品ID" :value="String(productId)" />
        <van-cell title="SKU列表数量" :value="String(list.length)" />
        <van-cell title="加载状态" :value="loading ? '加载中' : '已完成'" />
        <van-cell title="仓库数量" :value="String(warehouses.length)" />
        <van-cell title="刷新状态" :value="refreshing ? '刷新中' : '空闲'" />
      </van-cell-group>
    </div>

    <!-- SKU 列表 -->
    <van-pull-refresh v-model="refreshing" @refresh="onRefresh">
      <div class="sku-list">
        <!-- 修复：添加安全检查 -->
        <template v-if="list && list.length > 0">
          <van-swipe-cell v-for="item in list" :key="item.id">
            <van-card
              :title="getSkuTitle(item)"
              :desc="getSpecText(item)"
              :thumb="getProductImage(item)"
            >
              <template #price>
                <div class="price-row">
                  <span>成本: ¥{{ getCostPrice(item) }}</span>
                  <span>售价: ¥{{ getSalePrice(item) }}</span>
                </div>
              </template>
              <template #num>
                <van-tag type="primary">总库存: {{ calculateTotalStock(item) }}</van-tag>
              </template>
              <template #bottom>
                <div class="card-bottom">
                  <div>SKU编码: {{ getSkuCode(item) }}</div>
                  <div v-if="getBarcode(item)">条码: {{ getBarcode(item) }}</div>
                  <div>单位: {{ getUnit(item) }}</div>
                </div>
              </template>
            </van-card>
            <template #right>
              <van-button square type="danger" text="删除" @click="onDel(item.id)" />
              <van-button square type="primary" text="编辑" @click="onEdit(item)" />
            </template>
          </van-swipe-cell>
        </template>
      </div>
      
      <!-- 空状态 -->
      <div v-if="!list.length && !loading">
        <van-empty description="暂无SKU数据">
          <van-button type="primary" size="small" @click="onAdd">
            新增SKU
          </van-button>
        </van-empty>
      </div>
    </van-pull-refresh>

    <!-- 新增/编辑抽屉 -->
    <van-popup
      v-model:show="showAdd"
      position="bottom"
      round
      :style="{ height: '85%' }"
      closeable
      @close="resetForm"
    >
      <div class="drawer-header">{{ isEditing ? '编辑SKU' : '新增SKU' }}</div>
      <van-form ref="addFormRef" class="drawer-form" @submit="onSave">
        <van-field
          v-model="addForm.sku_code"
          label="SKU编码"
          placeholder="自动生成留空"
        />
        <van-field
          v-model="addForm.spec_text"
          label="规格描述"
          placeholder="例：红色-L"
          :rules="[{ required: true, message: '请输入规格描述' }]"
        />
        <van-field
          v-model="addForm.barcode"
          label="条码"
          placeholder="扫码或手动输入"
        />
        <van-field
          v-model.number="addForm.cost_price"
          label="成本价"
          type="number"
          :rules="[{ required: true, message: '请输入成本价' }]"
        >
          <template #extra>元</template>
        </van-field>
        <van-field
          v-model.number="addForm.sale_price"
          label="销售价"
          type="number"
          :rules="[{ required: true, message: '请输入销售价' }]"
        >
          <template #extra>元</template>
        </van-field>
        <van-field
          v-model="addForm.unit"
          label="单位"
          placeholder="例：个、件、箱"
          :rules="[{ required: true, message: '请输入单位' }]"
        />

        <!-- 仓库库存一次性录入 -->
        <van-cell-group title="初始库存">
          <van-field
            v-for="warehouse in warehouses"
            :key="warehouse.id"
            v-model.number="stockMap[warehouse.id]"
            :label="warehouse.name"
            type="number"
            placeholder="0"
          >
            <template #extra>件</template>
          </van-field>
        </van-cell-group>

        <div style="margin: 16px;">
          <van-button round block type="primary" native-type="submit" :loading="saving">
            {{ saving ? '保存中...' : '保存' }}
          </van-button>
        </div>
      </van-form>
    </van-popup>

    <!-- 加载状态 -->
    <van-loading v-if="loading" class="loading" />

    <!-- 调试按钮 -->
    <div class="debug-button" v-if="debugMode">
      <van-button type="warning" size="small" @click="testApi">测试API</van-button>
      <van-button type="info" size="small" @click="debugMode = false" style="margin-left: 8px;">
        隐藏调试
      </van-button>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted, computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { showToast, showConfirmDialog, showSuccessToast, showFailToast } from 'vant'
import {
  getProductSkus,
  addSku,
  updateSku,
  deleteSku
} from '@/api/product'
import { getWarehouseOptions } from '@/api/warehouse'

const route = useRoute()
const router = useRouter()
const productId = route.params.id

// 调试模式
const debugMode = ref(true)

/* ===== 数据 ===== */
const list = ref([])
const refreshing = ref(false)
const loading = ref(false)
const saving = ref(false)
const showAdd = ref(false)
const warehouses = ref([])
const stockMap = reactive({})

const addForm = reactive({
  id: '',
  sku_code: '',
  spec_text: '',
  barcode: '',
  cost_price: '',
  sale_price: '',
  unit: '',
  product_id: productId
})

const isEditing = computed(() => !!addForm.id)

// 修复：添加安全的属性访问方法
const getSkuTitle = (item) => {
  return item?.sku_code || `SKU-${item?.id || '未知'}`
}

const getSkuCode = (item) => {
  return item?.sku_code || '未知'
}

const getSpecText = (item) => {
  if (!item) return '无规格'
  
  if (item.spec_text) return item.spec_text
  if (item.spec) {
    if (typeof item.spec === 'string') {
      try {
        const parsed = JSON.parse(item.spec)
        return Object.values(parsed).join(' / ')
      } catch {
        return item.spec
      }
    }
    if (typeof item.spec === 'object') {
      return Object.values(item.spec).join(' / ')
    }
  }
  return '无规格'
}

const getCostPrice = (item) => {
  return item?.cost_price || '0.00'
}

const getSalePrice = (item) => {
  return item?.sale_price || '0.00'
}

const getBarcode = (item) => {
  return item?.barcode || ''
}

const getUnit = (item) => {
  return item?.unit || '个'
}

// 获取产品图片
const getProductImage = (item) => {
  if (!item) return ''
  if (item.image) return item.image
  if (item.product?.image) return item.product.image
  if (item.product?.images) {
    const images = Array.isArray(item.product.images) ? item.product.images : [item.product.images]
    return images[0] || ''
  }
  return ''
}

// 计算总库存
const calculateTotalStock = (item) => {
  if (!item) return 0
  if (item.total_stock !== undefined) return item.total_stock
  if (item.stocks && Array.isArray(item.stocks)) {
    return item.stocks.reduce((total, stock) => total + (stock.quantity || 0), 0)
  }
  return 0
}

/* ===== 测试函数 ===== */
const testApi = async () => {
  console.log('=== 开始API测试 ===')
  console.log('产品ID:', productId)
  
  try {
    console.log('1. 测试获取SKU列表...')
    const skuRes = await getProductSkus(productId)
    console.log('SKU API响应:', skuRes)
    
    console.log('2. 测试获取仓库列表...')
    const warehouseRes = await getWarehouseOptions()
    console.log('仓库API响应:', warehouseRes)
    
    console.log('3. 当前列表数据:', list.value)
    console.log('4. 当前仓库数据:', warehouses.value)
    
    // 显示测试结果
    showToast({
      message: 'API测试完成，请查看控制台',
      position: 'top'
    })
    
  } catch (error) {
    console.error('API测试失败:', error)
    showFailToast('API测试失败')
  }
}

/* ===== 生命周期 ===== */
onMounted(async () => {
  console.log('页面加载，产品ID:', productId)
  await loadData()
})

async function loadData() {
  loading.value = true
  try {
    await Promise.all([loadSkus(), loadWarehouses()])
    console.log('数据加载完成，SKU数量:', list.value.length)
  } catch (error) {
    console.error('加载数据失败:', error)
    showFailToast('加载失败')
  } finally {
    loading.value = false
  }
}

/* ===== 数据加载 ===== */
async function loadSkus() {
  try {
    console.log('开始加载SKU列表，产品ID:', productId)
    const res = await getProductSkus(productId)
    console.log('SKU API原始响应:', res)
    
    // 处理不同的响应结构
    let skuList = []
    if (res && res.code === 200) {
      skuList = res.data?.list || res.data || []
    } else if (res && res.list) {
      skuList = res.list
    } else if (Array.isArray(res)) {
      skuList = res
    } else {
      skuList = res?.data || []
    }
    
    // 修复：确保每个项都有必要的属性
    skuList = skuList.map(item => ({
      id: item?.id || 0,
      sku_code: item?.sku_code || '',
      spec: item?.spec || {},
      spec_text: item?.spec_text || '',
      barcode: item?.barcode || '',
      cost_price: item?.cost_price || '0.00',
      sale_price: item?.sale_price || '0.00',
      unit: item?.unit || '个',
      stocks: item?.stocks || [],
      product: item?.product || {},
      ...item // 保留其他属性
    }))
    
    console.log('处理后的SKU列表:', skuList)
    list.value = skuList
    
    if (skuList.length === 0) {
      console.log('SKU列表为空，可能的原因:')
      console.log('1. 该产品没有SKU')
      console.log('2. API返回数据结构不匹配')
      console.log('3. 产品ID不正确:', productId)
      showToast('该产品暂无SKU数据')
    }
    
  } catch (error) {
    console.error('加载SKU列表失败:', error)
    list.value = []
    showFailToast('加载SKU失败')
  }
}

async function loadWarehouses() {
  try {
    console.log('开始加载仓库列表...')
    const res = await getWarehouseOptions()
    console.log('仓库API原始响应:', res)
    
    // 处理不同的响应结构
    let warehouseList = []
    if (res && res.code === 200) {
      warehouseList = res.data?.list || res.data || []
    } else if (res && res.list) {
      warehouseList = res.list
    } else if (Array.isArray(res)) {
      warehouseList = res
    } else {
      warehouseList = res?.data || []
    }
    
    console.log('处理后的仓库列表:', warehouseList)
    warehouses.value = warehouseList
    
    // 初始化库存映射
    warehouseList.forEach(w => {
      if (w && w.id) {
        stockMap[w.id] = stockMap[w.id] || 0
      }
    })
    
  } catch (error) {
    console.error('加载仓库列表失败:', error)
    warehouses.value = []
    showFailToast('加载仓库列表失败')
  }
}

/* ===== 表单操作 ===== */
function onAdd() {
  resetForm()
  showAdd.value = true
}

function onEdit(item) {
  if (!item) return
  
  Object.assign(addForm, {
    id: item.id || '',
    sku_code: item.sku_code || '',
    spec_text: getSpecText(item),
    barcode: item.barcode || '',
    cost_price: item.cost_price || '',
    sale_price: item.sale_price || '',
    unit: item.unit || ''
  })
  
  if (item.stocks && Array.isArray(item.stocks)) {
    item.stocks.forEach(stock => {
      if (stock && stock.warehouse_id) {
        stockMap[stock.warehouse_id] = stock.quantity || 0
      }
    })
  }
  
  showAdd.value = true
}

function resetForm() {
  Object.assign(addForm, {
    id: '',
    sku_code: '',
    spec_text: '',
    barcode: '',
    cost_price: '',
    sale_price: '',
    unit: ''
  })
  
  warehouses.value.forEach(w => {
    if (w && w.id) {
      stockMap[w.id] = 0
    }
  })
}

async function onDel(id) {
  if (!id) return
  
  try {
    await showConfirmDialog({ 
      title: '确认删除', 
      message: '确定删除该SKU？此操作不可恢复。' 
    })
    await deleteSku(id)
    showSuccessToast('删除成功')
    await loadSkus()
  } catch (error) {
    if (error !== 'cancel') {
      showFailToast('删除失败')
      console.error('删除SKU失败:', error)
    }
  }
}

async function onSave() {
  saving.value = true
  try {
    const skuData = {
      ...addForm,
      product_id: parseInt(productId),
      spec: addForm.spec_text
    }

    const stocksData = warehouses.value
      .filter(w => w && w.id && stockMap[w.id] > 0)
      .map(w => ({
        warehouse_id: w.id,
        quantity: stockMap[w.id] || 0
      }))

    if (addForm.id) {
      await updateSku(addForm.id, { ...skuData, stocks: stocksData })
    } else {
      await addSku({ ...skuData, stocks: stocksData })
    }
    
    showSuccessToast('保存成功')
    showAdd.value = false
    await loadSkus()
  } catch (error) {
    showFailToast('保存失败')
    console.error('保存SKU失败:', error)
  } finally {
    saving.value = false
  }
}

function onRefresh() {
  loadSkus().finally(() => {
    refreshing.value = false
  })
}
</script>

<style scoped lang="scss">
.product-skus-page {
  background: #f7f8fa;
  min-height: 100vh;
}

.debug-info {
  padding: 8px;
  background: #fff5e6;
  border-bottom: 1px solid #ffd699;
}

.debug-button {
  position: fixed;
  bottom: 20px;
  right: 20px;
  z-index: 1000;
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.sku-list {
  padding: 8px;
}

.van-card {
  margin-bottom: 8px;
  border-radius: 8px;
}

.price-row {
  display: flex;
  justify-content: space-between;
  font-size: 14px;
  
  span:first-child {
    color: #666;
  }
  
  span:last-child {
    color: #ff4444;
    font-weight: bold;
  }
}

.card-bottom {
  font-size: 12px;
  color: #999;
  
  div {
    margin-bottom: 2px;
  }
}

.drawer-header {
  padding: 16px;
  font-size: 16px;
  font-weight: 500;
  text-align: center;
  border-bottom: 1px solid #f0f0f0;
}

.drawer-form {
  padding: 0 16px 40px;
}

.loading {
  display: flex;
  justify-content: center;
  padding: 20px;
}
</style>
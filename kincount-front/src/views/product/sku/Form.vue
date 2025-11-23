<template>
  <div class="multi-sku-page">
    <van-nav-bar
      :title="pageTitle"
      left-text="返回"
      left-arrow
      @click-left="handleBack"
    />

    <div class="form-container">
      <!-- 商品基本信息 -->
      <div class="section" v-if="!isEditing">
        <div class="section-title">商品基本信息</div>
        <van-field
          v-model="productBase.product_name"
          label="商品名称"
          placeholder="请输入商品名称"
          :rules="[{ required: true, message: '请输入商品名称' }]"
        />
        <van-field
          v-model="productBase.category_id"
          label="分类ID"
          placeholder="请输入分类ID"
          type="number"
        />
        <van-field
          v-model="productBase.description"
          label="商品描述"
          type="textarea"
          placeholder="请输入商品描述"
          rows="2"
          autosize
        />
      </div>

      <!-- 规格维度定义 -->
      <div class="section">
        <div class="section-title">规格维度</div>
        <div class="spec-dimensions">
          <div 
            v-for="(dimension, index) in specDimensions" 
            :key="index"
            class="dimension-item"
          >
            <van-field
              v-model="dimension.name"
              :label="`规格${index + 1}`"
              placeholder="如：颜色、尺寸等"
              :rules="[{ required: true, message: '请输入规格名称' }]"
            />
            <van-field
              v-model="dimension.values"
              :label="`规格值`"
              placeholder="用逗号分隔，如：红色,蓝色,黑色"
              :rules="[{ required: true, message: '请输入规格值' }]"
              @blur="generateSkuCombinations"
            >
              <template #extra>
                <van-button 
                  size="mini" 
                  type="danger" 
                  plain 
                  @click="removeDimension(index)"
                  v-if="specDimensions.length > 1"
                >
                  删除
                </van-button>
              </template>
            </van-field>
          </div>
        </div>
        
        <div class="dimension-actions">
          <van-button 
            size="small" 
            type="primary" 
            @click="addDimension"
          >
            添加规格维度
          </van-button>
          <van-button 
            size="small" 
            type="default" 
            @click="generateSkuCombinations"
            :disabled="!canGenerateCombinations"
          >
            生成SKU组合
          </van-button>
        </div>
      </div>

      <!-- SKU列表 -->
      <div class="section" v-if="skuList.length > 0">
        <div class="section-title">
          SKU列表 ({{ skuList.length }}个)
          <van-button 
            size="mini" 
            type="primary" 
            plain 
            @click="batchSetPrice"
          >
            批量设置
          </van-button>
        </div>
        
        <div class="sku-list">
          <div 
            v-for="(sku, index) in skuList" 
            :key="sku.combinationKey"
            class="sku-item"
          >
            <div class="sku-header">
              <span class="sku-spec">{{ sku.specText }}</span>
              <van-switch
                v-model="sku.status"
                :model-value="sku.status === 1"
                @update:model-value="sku.status = $event ? 1 : 0"
                size="20px"
              />
            </div>
            
            <div class="sku-fields">
              <van-field
                v-model.number="sku.cost_price"
                label="成本价"
                type="number"
                placeholder="0.00"
                :rules="[{ required: true, message: '请输入成本价' }]"
              />
              <van-field
                v-model.number="sku.sale_price"
                label="销售价"
                type="number"
                placeholder="0.00"
                :rules="[{ required: true, message: '请输入销售价' }]"
              />
              <van-field
                v-model="sku.barcode"
                label="条码"
                placeholder="请输入条码"
              />
              <van-field
                v-model="sku.unit"
                label="单位"
                placeholder="个/件/箱"
                :rules="[{ required: true, message: '请输入单位' }]"
              />
              <van-field
                v-model.number="sku.stock"
                label="库存"
                type="number"
                placeholder="0"
              />
            </div>
            
            <van-button 
              size="mini" 
              type="danger" 
              plain 
              @click="removeSku(index)"
              class="remove-sku-btn"
            >
              删除此SKU
            </van-button>
          </div>
        </div>
      </div>

      <!-- 提交按钮 -->
      <div class="form-actions">
        <van-button 
          round 
          block 
          type="primary" 
          @click="submitAll"
          :loading="submitting"
          :disabled="skuList.length === 0"
        >
          {{ submitButtonText }}
        </van-button>
        <van-button 
          round 
          block 
          type="default" 
          @click="handleBack" 
          :disabled="submitting"
          style="margin-top: 12px;"
        >
          取消
        </van-button>
      </div>
    </div>

    <!-- 批量设置弹窗 -->
    <van-popup
      v-model:show="showBatchDialog"
      position="bottom"
      round
      :style="{ height: '60%' }"
      closeable
    >
      <div class="form-title">批量设置</div>
      <van-form @submit="confirmBatchSet">
        <van-field
          v-model.number="batchData.cost_price"
          label="成本价"
          type="number"
          placeholder="留空则不修改"
        />
        <van-field
          v-model.number="batchData.sale_price"
          label="销售价"
          type="number"
          placeholder="留空则不修改"
        />
        <van-field
          v-model="batchData.unit"
          label="单位"
          placeholder="留空则不修改"
        />
        <van-field
          v-model.number="batchData.stock"
          label="库存"
          type="number"
          placeholder="留空则不修改"
        />
        <van-field name="状态" label="批量设置状态">
          <template #input>
            <van-radio-group v-model="batchData.status" direction="horizontal">
              <van-radio :name="null">不修改</van-radio>
              <van-radio :name="1">启用</van-radio>
              <van-radio :name="0">禁用</van-radio>
            </van-radio-group>
          </template>
        </van-field>
        <div class="form-actions">
          <van-button round block type="primary" native-type="submit">
            确认应用到所有SKU
          </van-button>
        </div>
      </van-form>
    </van-popup>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { showToast, showConfirmDialog, showLoadingToast, closeToast } from 'vant'
import { 
  saveProductAggregate, 
  updateProductAggregate, 
  getProductAggregate,
  getProductSkus,
  addMultipleSku,
  batchSku
} from '@/api/product'

const route = useRoute()
const router = useRouter()

// 表单状态
const submitting = ref(false)
const isEditing = ref(false)
const showBatchDialog = ref(false)
const productId = ref('')

// 商品基本信息
const productBase = reactive({
  product_name: '',
  category_id: '',
  description: ''
})

// 规格维度
const specDimensions = ref([
  { name: '颜色', values: '红色,蓝色,黑色' },
  { name: '尺寸', values: 'S,M,L' }
])

// SKU列表
const skuList = ref([])

// 批量设置数据
const batchData = reactive({
  cost_price: null,
  sale_price: null,
  unit: '',
  stock: null,
  status: null
})

// 计算属性
const canGenerateCombinations = computed(() => {
  return specDimensions.value.every(dim => 
    dim.name && dim.values && dim.values.split(',').length > 0
  )
})

const pageTitle = computed(() => {
  return isEditing.value ? '编辑商品SKU' : '新增商品SKU'
})

const submitButtonText = computed(() => {
  const count = skuList.value.length
  const action = isEditing.value ? '更新' : '新增'
  return `${action}商品SKU (${count}个)`
})

// 添加规格维度
const addDimension = () => {
  specDimensions.value.push({ name: '', values: '' })
}

// 删除规格维度
const removeDimension = (index) => {
  specDimensions.value.splice(index, 1)
  generateSkuCombinations()
}

// 生成SKU组合
const generateSkuCombinations = () => {
  if (!canGenerateCombinations.value) return

  const dimensions = specDimensions.value.map(dim => ({
    name: dim.name,
    values: dim.values.split(',').map(v => v.trim()).filter(v => v)
  }))

  // 生成所有可能的组合
  const combinations = generateCombinations(dimensions)
  
  // 转换为SKU列表
  skuList.value = combinations.map(comb => {
    const spec = {}
    const specTextParts = []
    
    comb.forEach(item => {
      spec[item.dimension] = item.value
      specTextParts.push(item.value)
    })
    
    const combinationKey = specTextParts.join('_')
    
    // 如果是已存在的SKU，保留原有数据
    const existingSku = skuList.value.find(sku => sku.combinationKey === combinationKey)
    
    return {
      combinationKey,
      spec,
      specText: specTextParts.join(' / '),
      cost_price: existingSku?.cost_price || 0,
      sale_price: existingSku?.sale_price || 0,
      barcode: existingSku?.barcode || '',
      unit: existingSku?.unit || '个',
      stock: existingSku?.stock || 0,
      status: existingSku?.status ?? 1
    }
  })
}

// 生成组合的递归函数
const generateCombinations = (dimensions, currentIndex = 0, currentCombination = []) => {
  if (currentIndex === dimensions.length) {
    return [currentCombination]
  }

  const results = []
  const currentDimension = dimensions[currentIndex]
  
  for (const value of currentDimension.values) {
    const newCombination = [
      ...currentCombination,
      { dimension: currentDimension.name, value }
    ]
    results.push(...generateCombinations(dimensions, currentIndex + 1, newCombination))
  }
  
  return results
}

// 删除SKU
const removeSku = (index) => {
  skuList.value.splice(index, 1)
}

// 批量设置
const batchSetPrice = () => {
  // 重置批量数据
  Object.keys(batchData).forEach(key => {
    batchData[key] = null
  })
  batchData.unit = ''
  showBatchDialog.value = true
}

// 确认批量设置
const confirmBatchSet = () => {
  skuList.value.forEach(sku => {
    if (batchData.cost_price !== null) {
      sku.cost_price = batchData.cost_price
    }
    if (batchData.sale_price !== null) {
      sku.sale_price = batchData.sale_price
    }
    if (batchData.unit) {
      sku.unit = batchData.unit
    }
    if (batchData.stock !== null) {
      sku.stock = batchData.stock
    }
    if (batchData.status !== null) {
      sku.status = batchData.status
    }
  })
  
  showBatchDialog.value = false
  showToast('批量设置成功')
}

// 加载商品数据
const loadProductData = async (id) => {
  try {
    const data = await getProductAggregate(id)
    
    // 设置商品基本信息
    productBase.product_name = data.product_name || ''
    productBase.category_id = data.category_id || ''
    productBase.description = data.description || ''
    
    // 加载商品SKU
    await loadProductSkus(id)
    
  } catch (error) {
    console.error('加载商品数据失败:', error)
    showToast('加载商品数据失败')
  }
}

// 加载商品SKU
const loadProductSkus = async (id) => {
  try {
    const data = await getProductSkus(id)
    
    if (data && data.length > 0) {
      // 解析规格维度
      const dimensions = extractDimensionsFromSkus(data)
      if (dimensions.length > 0) {
        specDimensions.value = dimensions
      }
      
      // 设置SKU列表
      skuList.value = data.map(sku => ({
        ...sku,
        combinationKey: generateCombinationKey(sku.spec),
        specText: Object.values(sku.spec).join(' / ')
      }))
    }
    
  } catch (error) {
    console.error('加载商品SKU失败:', error)
    // 如果加载失败，使用默认规格维度
    generateSkuCombinations()
  }
}

// 从SKU列表中提取规格维度
const extractDimensionsFromSkus = (skus) => {
  if (!skus || skus.length === 0) return []
  
  const dimensionMap = new Map()
  
  skus.forEach(sku => {
    if (sku.spec && typeof sku.spec === 'object') {
      Object.entries(sku.spec).forEach(([dimName, dimValue]) => {
        if (!dimensionMap.has(dimName)) {
          dimensionMap.set(dimName, new Set())
        }
        dimensionMap.get(dimName).add(dimValue)
      })
    }
  })
  
  return Array.from(dimensionMap.entries()).map(([name, values]) => ({
    name,
    values: Array.from(values).join(',')
  }))
}

// 生成组合键
const generateCombinationKey = (spec) => {
  if (!spec || typeof spec !== 'object') return ''
  return Object.values(spec).join('_')
}

// 准备提交数据
const prepareSubmitData = () => {
  const skus = skuList.value.map(sku => ({
    spec: sku.spec,
    cost_price: sku.cost_price,
    sale_price: sku.sale_price,
    barcode: sku.barcode,
    unit: sku.unit,
    stock: sku.stock,
    status: sku.status
  }))

  if (isEditing.value) {
    return {
      product_id: productId.value,
      skus: skus
    }
  } else {
    return {
      product: {
        product_name: productBase.product_name,
        category_id: productBase.category_id,
        description: productBase.description
      },
      skus: skus
    }
  }
}

// 提交所有数据
const submitAll = async () => {
  submitting.value = true
  const loadingToast = showLoadingToast({
    message: '正在保存...',
    forbidClick: true,
    duration: 0
  })
  
  try {
    const submitData = prepareSubmitData()
    
    if (isEditing.value) {
      // 编辑模式：使用批量更新
      await batchSku(submitData)
      showToast('更新成功')
    } else {
      // 新增模式：创建商品聚合
      await saveProductAggregate(submitData)
      showToast('新增成功')
    }
    
    closeToast()
    router.back()
  } catch (error) {
    closeToast()
    showToast(isEditing.value ? '更新失败' : '新增失败')
    console.error('操作失败:', error)
  } finally {
    submitting.value = false
  }
}

// 处理返回
const handleBack = () => {
  router.back()
}

// 初始化
onMounted(async () => {
  const id = route.params.productId
  if (id) {
    productId.value = id
    isEditing.value = true
    await loadProductData(id)
  } else {
    isEditing.value = false
    // 初始生成组合
    generateSkuCombinations()
  }
})
</script>

<style scoped lang="scss">
.multi-sku-page {
  background-color: #f7f8fa;
  min-height: 100vh;
}

.form-container {
  padding: 16px;
}

.section {
  background: white;
  border-radius: 8px;
  margin-bottom: 16px;
  padding: 16px;
}

.section-title {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding-bottom: 12px;
  font-size: 16px;
  font-weight: 500;
  color: #323233;
  border-bottom: 1px solid #ebedf0;
  margin-bottom: 12px;
}

.dimension-item {
  background: #f7f8fa;
  border-radius: 6px;
  padding: 12px;
  margin-bottom: 12px;
  
  &:last-child {
    margin-bottom: 0;
  }
}

.dimension-actions {
  display: flex;
  gap: 8px;
  margin-top: 12px;
}

.sku-list {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.sku-item {
  background: #f7f8fa;
  border-radius: 8px;
  padding: 12px;
  border: 1px solid #ebedf0;
}

.sku-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 12px;
  padding-bottom: 8px;
  border-bottom: 1px solid #e1e2e3;
}

.sku-spec {
  font-weight: 500;
  color: #323233;
}

.sku-fields {
  display: grid;
  gap: 8px;
}

.remove-sku-btn {
  margin-top: 12px;
  width: 100%;
}

.form-actions {
  padding: 16px 0;
}

.form-title {
  padding: 16px;
  font-size: 16px;
  font-weight: 500;
  text-align: center;
  border-bottom: 1px solid #eee;
}

:deep(.van-radio-group) {
  width: 100%;
  display: flex;
  justify-content: space-around;
}
</style>
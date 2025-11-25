<template>
  <div class="multi-sku-page">
    <van-nav-bar :title="pageTitle" left-text="返回" left-arrow @click-left="handleBack">
      <template #right>
        <van-button size="small" type="primary" @click="submitAll" :loading="submitting"
          :disabled="skuList.length === 0 || submitting">
          {{ submitButtonText }}
        </van-button>
      </template>
    </van-nav-bar>

    <div class="form-container">
      <!-- 规格维度定义 -->
      <div class="section">
        <div class="section-title">规格维度</div>
        <div class="spec-dimensions">
          <div v-for="(dimension, index) in specDimensions" :key="index" class="dimension-item">
            <van-field v-model="dimension.name" :label="`规格${index + 1}`" placeholder="如：颜色、尺寸等"
              :rules="[{ required: true, message: '请输入规格名称' }]" :readonly="dimension.name === '颜色'" />
            <!-- 颜色维度 - 始终使用选择器 -->
            <van-field v-if="dimension.name === '颜色'" readonly :label="`规格值`" :model-value="dimension.values"
              :placeholder="`点击选择颜色值`" @click="openColorPicker(dimension)">
              <template #button>
                <van-button size="mini" type="primary" plain @click="openColorPicker(dimension)">
                  选择颜色
                </van-button>
              </template>
            </van-field>
            <!-- 其他维度 -->
            <van-field v-else v-model="dimension.values" :label="`规格值`" placeholder="用逗号分隔，如：红色,蓝色,黑色"
              :rules="[{ required: true, message: '请输入规格值' }]" @blur="generateSkuCombinations">
              <template #extra>
                <van-button size="mini" type="danger" plain @click="removeDimension(index)"
                  v-if="specDimensions.length > 1">
                  删除
                </van-button>
              </template>
            </van-field>
          </div>
        </div>

        <div class="dimension-actions">
          <van-button size="small" type="primary" @click="addDimension">
            添加规格维度
          </van-button>
          <van-button size="small" type="default" @click="generateSkuCombinations" :disabled="!canGenerateCombinations">
            生成SKU组合
          </van-button>
        </div>
      </div>

      <!-- SKU列表 -->
      <div class="section" v-if="skuList.length > 0">
        <div class="section-title">
          SKU列表 ({{ skuList.length }}个)
          <span class="sku-count-info">({{ existingSkuCount }}个已有, {{ newSkuCount }}个新增)</span>
          <van-button size="mini" type="primary" plain @click="batchSetPrice">
            批量设置
          </van-button>
        </div>

        <div class="sku-list">
          <div v-for="(sku, index) in skuList" :key="sku.combinationKey" class="sku-item"
            :class="{ 'new-sku': !sku.id }">
            <div class="sku-header">
              <span class="sku-spec">{{ sku.specText }}</span>
              <div class="sku-status">
                <span class="sku-type-tag" :class="sku.id ? 'existing' : 'new'">
                  {{ sku.id ? '已有' : '新增' }}
                </span>
                <van-switch v-model="sku.status" :model-value="sku.status === 1"
                  @update:model-value="sku.status = $event ? 1 : 0" size="20px" />
              </div>
            </div>

            <div class="sku-fields">
              <van-field v-model.number="sku.cost_price" label="成本价" type="number" placeholder="0.00"
                :rules="[{ required: true, message: '请输入成本价' }]" />
              <van-field v-model.number="sku.sale_price" label="销售价" type="number" placeholder="0.00"
                :rules="[{ required: true, message: '请输入销售价' }]" />
              <van-field v-model="sku.barcode" label="条码" placeholder="留空则自动生成" :readonly="!!sku.id"
                :tooltip="sku.id ? '已有SKU条码不可修改' : '新增SKU条码自动生成'" />
              <van-field v-model="sku.unit" label="单位" placeholder="个/件/箱"
                :rules="[{ required: true, message: '请输入单位' }]" />
            </div>

            <van-button size="mini" type="danger" plain @click="removeSku(index)" class="remove-sku-btn">
              删除此SKU
            </van-button>
          </div>
        </div>
      </div>
    </div>

    <!-- 批量设置弹窗 -->
    <van-popup v-model:show="showBatchDialog" position="bottom" round :style="{ height: '50%' }" closeable>
      <div class="form-title">批量设置</div>
      <van-form @submit="confirmBatchSet">
        <van-field v-model.number="batchData.cost_price" label="成本价" type="number" placeholder="留空则不修改" />
        <van-field v-model.number="batchData.sale_price" label="销售价" type="number" placeholder="留空则不修改" />
        <van-field v-model="batchData.unit" label="单位" placeholder="留空则不修改" />
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

    <!-- 颜色选择弹窗 -->
    <van-popup v-model:show="showColorPicker" position="bottom" round :style="{ height: '60%' }" closeable>
      <div class="form-title">选择颜色</div>
      <div class="color-picker">
        <div class="color-grid">
          <div v-for="color in predefinedColors" :key="color" class="color-item">
            <van-checkbox :model-value="selectedColors.includes(color)" @update:model-value="toggleColor(color, $event)"
              :class="`color-${getColorClass(color)}`">
              {{ color }}
            </van-checkbox>
          </div>
        </div>
        <div class="color-actions">
          <van-button round block type="primary" @click="confirmColorSelection" :disabled="selectedColors.length === 0">
            确认选择 ({{ selectedColors.length }}个颜色)
          </van-button>
          <van-button round block type="default" @click="clearColorSelection" style="margin-top: 12px;">
            清空选择
          </van-button>
        </div>
      </div>
    </van-popup>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { showToast, showConfirmDialog, showLoadingToast, closeToast } from 'vant'
import {
  getProductSkus,
  batchSku,
  deleteSku
} from '@/api/product'

const route = useRoute()
const router = useRouter()

// 表单状态
const submitting = ref(false)
const isNewSkuMode = ref(false) // 新增SKU模式
const showBatchDialog = ref(false)
const showColorPicker = ref(false)
const productId = ref('')
const currentColorDimension = ref(null) // 当前正在编辑的颜色维度

// 预定义颜色列表
const predefinedColors = [
  '红色', '蓝色', '黑色', '白色', '粉色',
  '黄色', '绿色', '紫色', '橙色', '灰色', '无颜色'
]

// 选中的颜色 - 默认为空
const selectedColors = ref([])

// 规格维度 - 新增SKU时默认只有颜色
const defaultSpecDimensions = () => [{
  name: '颜色',
  values: '' // 默认为空
}]

const specDimensions = ref(defaultSpecDimensions())

// SKU列表
const skuList = ref([])

// 批量设置数据
const batchData = reactive({
  cost_price: null,
  sale_price: null,
  unit: '',
  status: null
})

// 计算属性
const canGenerateCombinations = computed(() => {
  return specDimensions.value.every(dim =>
    dim.name && dim.values && dim.values.split(',').length > 0
  )
})

const pageTitle = computed(() => {
  return isNewSkuMode.value ? '新增SKU' : '编辑SKU'
})

const submitButtonText = computed(() => {
  const count = skuList.value.length
  return isNewSkuMode.value ? `新增(${count})` : `更新(${count})`
})

// 统计已有和新增SKU数量
const existingSkuCount = computed(() => {
  return skuList.value.filter(sku => sku.id).length
})

const newSkuCount = computed(() => {
  return skuList.value.filter(sku => !sku.id).length
})

// 获取颜色对应的CSS类名
const getColorClass = (color) => {
  const colorMap = {
    '红色': 'red',
    '蓝色': 'blue',
    '黑色': 'black',
    '白色': 'white',
    '粉色': 'pink',
    '黄色': 'yellow',
    '绿色': 'green',
    '紫色': 'purple',
    '橙色': 'orange',
    '灰色': 'gray',
    '无颜色': 'no-color'
  }
  return colorMap[color] || 'default'
}

// 打开颜色选择器
const openColorPicker = (dimension) => {
  currentColorDimension.value = dimension
  // 如果有已选的颜色值，初始化选中状态
  if (dimension.values) {
    selectedColors.value = dimension.values.split(',').map(v => v.trim()).filter(v => v)
  } else {
    selectedColors.value = []
  }
  showColorPicker.value = true
}

// 添加规格维度
const addDimension = () => {
  specDimensions.value.push({ name: '', values: '' })
}

// 删除规格维度
const removeDimension = (index) => {
  // 防止删除颜色维度
  if (specDimensions.value[index].name === '颜色') {
    showToast('颜色维度不能删除')
    return
  }
  specDimensions.value.splice(index, 1)
  generateSkuCombinations()
}

// 切换颜色选择
const toggleColor = (color, checked) => {
  if (checked) {
    if (!selectedColors.value.includes(color)) {
      selectedColors.value.push(color)
    }
  } else {
    const index = selectedColors.value.indexOf(color)
    if (index > -1) {
      selectedColors.value.splice(index, 1)
    }
  }
}

// 清空颜色选择
const clearColorSelection = () => {
  selectedColors.value = []
}

// 确认颜色选择
const confirmColorSelection = () => {
  if (selectedColors.value.length === 0) {
    showToast('请至少选择一个颜色')
    return
  }

  // 更新当前颜色维度的值
  if (currentColorDimension.value) {
    currentColorDimension.value.values = selectedColors.value.join(',')
    generateSkuCombinations()
  }

  showColorPicker.value = false
  showToast(`已选择 ${selectedColors.value.length} 个颜色`)
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
      status: existingSku?.status ?? 1,
      id: existingSku?.id, // 保留id字段
      sku_code: existingSku?.sku_code || '' // 保留sku_code字段
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
const removeSku = async (index) => {
  const sku = skuList.value[index]

  // 如果有id，说明是已存在的SKU，需要调用接口软删除
  if (sku.id) {
    try {
      await showConfirmDialog({
        title: '确认删除',
        message: `确定要删除SKU "${sku.specText}" 吗？此操作不可撤销。`
      })

      const loadingToast = showLoadingToast({
        message: '正在删除...',
        forbidClick: true,
        duration: 0
      })

      try {
        // 调用删除接口，传递id参数
        await deleteSku(sku.id)
        closeToast()
        // 从列表中移除
        skuList.value.splice(index, 1)
        showToast('删除成功')
      } catch (apiError) {
        closeToast()
        // console.error('删除失败:', apiError)

        // 从 apiError.message 中提取错误信息
        const errorMsg = apiError.message || '删除失败，请重试'
        showToast(errorMsg)
      }

    } catch (dialogError) {
      // 用户取消删除，不做任何操作
      if (dialogError === 'cancel') {
        console.log('用户取消删除')
      }
    }
  } else {
    // 没有id，说明是新增的SKU，直接移除列表
    skuList.value.splice(index, 1)
    showToast('已移除SKU')
  }
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
    if (batchData.status !== null) {
      sku.status = batchData.status
    }
  })

  showBatchDialog.value = false
  showToast('批量设置成功')
}

// 加载商品SKU
const loadProductSkus = async (id) => {
  try {
    const data = await getProductSkus(id)

    if (data && data.length > 0) {
      // 有SKU数据，设置为编辑SKU模式
      isNewSkuMode.value = false

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
    } else {
      // 没有SKU数据，设置为新增SKU模式
      isNewSkuMode.value = true
      // 使用默认规格维度（只有颜色）
      specDimensions.value = defaultSpecDimensions()
    }

  } catch (error) {
    console.error('加载商品SKU失败:', error)
    // 如果加载失败，默认设置为新增SKU模式
    isNewSkuMode.value = true
    specDimensions.value = defaultSpecDimensions()
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

// 准备提交数据 - 确保传递已有SKU的ID
const prepareSubmitData = () => {
  const skus = skuList.value.map(sku => {
    const skuData = {
      spec: sku.spec,
      cost_price: parseFloat(sku.cost_price) || 0,
      sale_price: parseFloat(sku.sale_price) || 0,
      unit: sku.unit,
      status: sku.status
    }

    // 关键：如果是已有SKU，必须传递id
    if (sku.id) {
      skuData.id = sku.id
    }

    // 对于已有SKU，如果条码不为空则传递
    // 对于新增SKU，如果条码不为空则传递，为空则后端自动生成
    if (sku.barcode && sku.barcode.trim() !== '') {
      skuData.barcode = sku.barcode
    }

    return skuData
  })

  return {
    product_id: productId.value,
    skus: skus
  }
}

// 提交所有数据
const submitAll = async () => {
  // 验证数据
  for (const sku of skuList.value) {
    if (sku.cost_price === undefined || sku.cost_price === null || sku.cost_price < 0) {
      showToast(`请填写有效的成本价: ${sku.specText}`)
      return
    }
    if (sku.sale_price === undefined || sku.sale_price === null || sku.sale_price < 0) {
      showToast(`请填写有效的销售价: ${sku.specText}`)
      return
    }
    if (!sku.unit) {
      showToast(`请填写单位: ${sku.specText}`)
      return
    }
  }

  // 检查条码重复（前端检查）
  const barcodeMap = new Map()
  for (const sku of skuList.value) {
    if (sku.barcode && sku.barcode.trim() !== '') {
      if (barcodeMap.has(sku.barcode)) {
        showToast(`条码 "${sku.barcode}" 重复，请修改`)
        return
      }
      barcodeMap.set(sku.barcode, true)
    }
  }

  submitting.value = true
  const loadingToast = showLoadingToast({
    message: '正在保存...',
    forbidClick: true,
    duration: 0
  })

  try {
    const submitData = prepareSubmitData()
    console.log('提交数据:', JSON.stringify(submitData, null, 2))

    // 统一使用批量更新接口处理混合数据
    const response = await batchSku(submitData)

    closeToast()
    showToast('保存成功')

    // 延迟返回，让用户看到成功提示
    setTimeout(() => {
      router.back()
    }, 1500)

  } catch (error) {
    closeToast()
    console.error('操作失败:', error)

    // 更详细的错误处理
    if (error.response && error.response.data) {
      const errorData = error.response.data
      if (errorData.code === 1062 || errorData.message?.includes('Duplicate entry')) {
        showToast('条码重复，请检查数据')
      } else if (errorData.message) {
        showToast(`操作失败: ${errorData.message}`)
      } else {
        showToast('操作失败，请重试')
      }
    } else if (error.message) {
      showToast(`操作失败: ${error.message}`)
    } else {
      showToast('操作失败，请重试')
    }
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
    await loadProductSkus(id)
  } else {
    // 如果没有商品ID，返回上一页
    showToast('缺少商品ID')
    router.back()
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

  .sku-count-info {
    font-size: 12px;
    color: #969799;
    margin-left: 8px;
  }
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

  &.new-sku {
    border-left: 4px solid #07c160;
  }
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

.sku-status {
  display: flex;
  align-items: center;
  gap: 8px;
}

.sku-type-tag {
  padding: 2px 6px;
  border-radius: 4px;
  font-size: 10px;
  font-weight: 500;

  &.existing {
    background: #e8f4fd;
    color: #1989fa;
  }

  &.new {
    background: #e8f8ef;
    color: #07c160;
  }
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

.color-picker {
  padding: 16px;
}

.color-grid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 16px;
  max-height: 300px;
  overflow-y: auto;
  margin-bottom: 20px;
}

.color-item {
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 8px;
  border-radius: 6px;
  background: #f7f8fa;
}

.color-actions {
  padding: 16px 0;
}

:deep(.van-radio-group) {
  width: 100%;
  display: flex;
  justify-content: space-around;
}

/* 导航栏右侧按钮样式 */
:deep(.van-nav-bar__right) {
  padding-right: 8px;
}

/* 颜色文本样式 */
:deep(.color-red .van-checkbox__label) {
  color: #ee0a24 !important;
}

:deep(.color-blue .van-checkbox__label) {
  color: #1989fa !important;
}

:deep(.color-black .van-checkbox__label) {
  color: #000000 !important;
}

:deep(.color-white .van-checkbox__label) {
  color: #ffffff !important;
  text-shadow: 0 0 2px rgba(0, 0, 0, 0.5);
}

:deep(.color-pink .van-checkbox__label) {
  color: #ff69b4 !important;
}

:deep(.color-yellow .van-checkbox__label) {
  color: #ffd700 !important;
}

:deep(.color-green .van-checkbox__label) {
  color: #07c160 !important;
}

:deep(.color-purple .van-checkbox__label) {
  color: #8b00ff !important;
}

:deep(.color-orange .van-checkbox__label) {
  color: #ffa500 !important;
}

:deep(.color-gray .van-checkbox__label) {
  color: #808080 !important;
}

:deep(.color-no-color .van-checkbox__label) {
  color: #969799 !important;
}

:deep(.color-default .van-checkbox__label) {
  color: #323233 !important;
}
</style>
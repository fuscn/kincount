<template>
  <div class="product-aggregate-form">
    <van-nav-bar :title="pageTitle" left-text="取消" left-arrow fixed placeholder
      @click-left="handleBack">
      <template #right>
        <van-button size="small" type="primary" @click="submitAll" :loading="submitting"
          :disabled="skuList.length === 0 || submitting">
          {{ submitButtonText }}
        </van-button>
      </template>
    </van-nav-bar>

    <van-form ref="formRef" class="form-wrap">
      <!-- 规格维度定义 -->
      <van-cell-group title="规格维度">
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
      </van-cell-group>

      <!-- SKU列表 -->
      <van-cell-group title="SKU列表" v-if="skuList.length > 0">
        <div class="section-title">
          <span>SKU列表</span>
          <span class="sku-count-badge total-count">{{ skuList.length }}个</span>
          <span class="sku-count-info">
            <span class="count-tag existing">已有 {{ existingSkuCount }} 个</span>
            <span class="count-tag new">新增 {{ newSkuCount }} 个</span>
          </span>
          <van-button size="mini" type="primary" plain class="batch-set-btn" @click="batchSetPrice">
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
            </div>

            <van-button size="mini" type="danger" plain @click="removeSku(index)" class="remove-sku-btn">
              删除此SKU
            </van-button>
          </div>
        </div>
      </van-cell-group>
    </van-form>

    <!-- 批量设置弹窗 -->
    <van-popup v-model:show="showBatchDialog" position="bottom" round :style="{ height: '50%' }" closeable>
      <div class="form-title">批量设置</div>
      <van-form @submit="confirmBatchSet">
        <van-field v-model.number="batchData.cost_price" label="成本价" type="number" placeholder="留空则不修改" />
      <van-field v-model.number="batchData.sale_price" label="销售价" type="number" placeholder="留空则不修改" />
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
import { ref, reactive, computed, onMounted, watch } from 'vue'
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
const isNewSkuMode = ref(false)
const showBatchDialog = ref(false)
const showColorPicker = ref(false)
const productId = ref('')
const currentColorDimension = ref(null)

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
  values: '无颜色' // 默认为无颜色
}]

const specDimensions = ref(defaultSpecDimensions())

// SKU列表
const skuList = ref([])

// SKU数据存储，用于维度变化时保留数据
const skuDataStore = ref({
  original: [], // 原始SKU数据（从API加载）
  modified: {}, // 用户修改的数据，按combinationKey存储
  dimensions: [] // 维度配置
})

// 批量设置数据
const batchData = reactive({
    cost_price: null,
    sale_price: null,
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

// 添加watch监听skuList的变化
watch(skuList, (newVal) => {
  console.log('🔄 SKU列表变化:', newVal)
  if (newVal && newVal.length > 0) {
    console.log('第一个SKU的价格详情:', {
      cost_price: newVal[0].cost_price,
      sale_price: newVal[0].sale_price,
      typeof_cost: typeof newVal[0].cost_price,
      typeof_sale: typeof newVal[0].sale_price
    })
  }
}, { deep: true })

// 监听规格维度的变化
watch(specDimensions, (newDimensions, oldDimensions) => {
  if (oldDimensions.length > 0) {
    // 检查是否是维度数量变化
    if (newDimensions.length !== oldDimensions.length) {
      console.log('📐 规格维度数量变化:', oldDimensions.length, '->', newDimensions.length)
      // 维度变化，需要重新生成组合
      if (canGenerateCombinations.value) {
        generateSkuCombinations()
      }
    }
  }
}, { deep: true })

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
  if (dimension.values && dimension.values !== '无颜色') {
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

// 价格解析的辅助函数
const parsePrice = (price) => {
  if (price === null || price === undefined || price === '') {
    return 0
  }
  // 如果是字符串，尝试解析
  if (typeof price === 'string') {
    // 移除可能的货币符号和逗号
    const cleanedPrice = price.replace(/[^\d.-]/g, '')
    const parsed = parseFloat(cleanedPrice)
    return isNaN(parsed) ? 0 : parsed
  }
  // 如果是数字，直接返回
  if (typeof price === 'number') {
    return price
  }
  // 其他情况返回0
  return 0
}

// 根据维度名称获取当前组合键的前缀（用于维度变化时匹配）
const getCombinationKeyPrefix = (spec, dimensionNames) => {
  const values = dimensionNames
    .map(dimName => spec[dimName] || '')
    .filter(val => val !== '')
  return values.join('_')
}

// 保存SKU数据到存储中
const saveSkuToStore = (sku) => {
  if (!sku.combinationKey) return
  
  // 只保存已有SKU的数据或用户已修改的数据
  if (sku.id || sku.cost_price > 0 || sku.sale_price > 0 || sku.barcode) {
    skuDataStore.value.modified[sku.combinationKey] = {
      cost_price: sku.cost_price,
      sale_price: sku.sale_price,
      barcode: sku.barcode,
      status: sku.status,
      id: sku.id,
      sku_code: sku.sku_code
    }
  }
}

// 从存储中获取SKU数据
const getSkuFromStore = (combinationKey) => {
  return skuDataStore.value.modified[combinationKey] || null
}

// 保存所有当前SKU数据到存储
const saveAllSkuToStore = () => {
  skuList.value.forEach(sku => {
    saveSkuToStore(sku)
  })
  console.log('💾 保存SKU数据到存储:', Object.keys(skuDataStore.value.modified).length, '个')
}

// 删除规格维度 - 改进版本
const removeDimension = async (index) => {
  // 防止删除颜色维度
  if (specDimensions.value[index].name === '颜色') {
    showToast('颜色维度不能删除')
    return
  }
  
  // 检查是否已有SKU数据
  const hasExistingSku = skuList.value.some(sku => sku.id || sku.cost_price > 0 || sku.sale_price > 0)
  
  if (hasExistingSku) {
    try {
      await showConfirmDialog({
        title: '确认删除',
        message: '删除规格维度将导致SKU数据可能丢失，是否继续？'
      })
      
      // 在删除前保存当前数据到存储
      saveAllSkuToStore()
      
      // 用户确认，删除维度
      const removedDimension = specDimensions.value[index]
      specDimensions.value.splice(index, 1)
      
      console.log('🗑️ 删除维度:', removedDimension.name, '剩余维度:', specDimensions.value.map(d => d.name))
      
      // 重新生成组合
      if (canGenerateCombinations.value) {
        generateSkuCombinations()
      } else {
        skuList.value = []
      }
      
      showToast('规格维度已删除')
    } catch (dialogError) {
      // 用户取消删除，不做任何操作
      console.log('用户取消删除规格维度')
    }
  } else {
    // 没有已有SKU，直接删除
    specDimensions.value.splice(index, 1)
    
    if (canGenerateCombinations.value) {
      generateSkuCombinations()
    } else {
      skuList.value = []
    }
    
    showToast('规格维度已删除')
  }
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
  // 更新当前颜色维度的值
  if (currentColorDimension.value) {
    if (selectedColors.value.length === 0) {
      // 没有选择任何颜色，设置为无颜色
      currentColorDimension.value.values = '无颜色'
      showToast('已设置为无颜色')
    } else {
      // 有选择颜色，使用选择的颜色
      currentColorDimension.value.values = selectedColors.value.join(',')
      showToast(`已选择 ${selectedColors.value.length} 个颜色`)
    }
    generateSkuCombinations()
  }

  showColorPicker.value = false
}

// 改进的SKU组合生成函数
const generateSkuCombinations = () => {
  if (!canGenerateCombinations.value) {
    console.log('⚠️ 无法生成组合，条件不满足')
    return
  }

  const dimensions = specDimensions.value.map(dim => ({
    name: dim.name,
    values: dim.values.split(',').map(v => v.trim()).filter(v => v)
  }))

  console.log('🔧 开始生成SKU组合，维度:', dimensions)

  // 生成所有可能的组合
  const combinations = generateCombinations(dimensions)
  console.log('🔢 生成的组合数量:', combinations.length)

  // 保存当前的维度名称列表，用于匹配
  const currentDimensionNames = specDimensions.value.map(dim => dim.name)

  // 转换为SKU列表
  skuList.value = combinations.map(comb => {
    const spec = {}
    const specTextParts = []

    comb.forEach(item => {
      spec[item.dimension] = item.value
      specTextParts.push(item.value)
    })

    const combinationKey = specTextParts.join('_')
    
    console.log('🔍 处理组合:', {
      combinationKey,
      spec,
      dimensionNames: currentDimensionNames
    })

    // 尝试从多个来源获取已有数据
    let existingData = null
    
    // 1. 首先从存储中查找
    existingData = getSkuFromStore(combinationKey)
    
    // 2. 如果存储中没有，尝试从当前SKU列表中查找匹配的
    if (!existingData) {
      const matchingSku = skuList.value.find(sku => {
        if (!sku.spec || typeof sku.spec !== 'object') return false
        
        // 检查所有当前维度是否匹配
        return currentDimensionNames.every(dimName => {
          return sku.spec[dimName] === spec[dimName]
        })
      })
      
      if (matchingSku) {
        existingData = {
          cost_price: matchingSku.cost_price,
          sale_price: matchingSku.sale_price,
          barcode: matchingSku.barcode,
          unit: matchingSku.unit,
          status: matchingSku.status,
          id: matchingSku.id,
          sku_code: matchingSku.sku_code
        }
      }
    }
    
    // 3. 如果仍然没有，尝试使用组合键前缀匹配（用于维度变化的情况）
    // 但是只匹配价格和单位，不匹配条码和ID
    if (!existingData) {
      const keyPrefix = getCombinationKeyPrefix(spec, currentDimensionNames)
      
      // 查找存储中是否有匹配前缀的数据
      for (const [key, data] of Object.entries(skuDataStore.value.modified)) {
        if (key.includes(keyPrefix) || keyPrefix.includes(key)) {
          console.log('🔗 找到前缀匹配:', key, '->', keyPrefix)
          // 只复制价格和单位信息，不复制条码和ID
          existingData = {
            cost_price: data.cost_price,
            sale_price: data.sale_price,
            unit: data.unit,
            status: data.status
            // 不复制 barcode, id, sku_code
          }
          break
        }
      }
    }

    console.log('📊 找到的已有数据:', existingData)

    // 使用修复的价格转换函数
    const costPrice = existingData ? parsePrice(existingData.cost_price) : 0
    const salePrice = existingData ? parsePrice(existingData.sale_price) : 0

    return {
      combinationKey,
      spec,
      specText: specTextParts.join(' / '),
      cost_price: costPrice,
      sale_price: salePrice,
      barcode: existingData?.barcode || '',
      status: existingData?.status ?? 1,
      id: existingData?.id,
      sku_code: existingData?.sku_code || ''
    }
  })

  console.log('✅ 生成SKU组合完成，总数:', skuList.value.length)
  console.log('📋 SKU列表详情:', skuList.value.map(sku => ({
    specText: sku.specText,
    cost_price: sku.cost_price,
    sale_price: sku.sale_price,
    barcode: sku.barcode,
    id: sku.id
  })))
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
        // 从存储中移除
        delete skuDataStore.value.modified[sku.combinationKey]
        showToast('删除成功')
      } catch (apiError) {
        closeToast()
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
        sku.cost_price = parsePrice(batchData.cost_price)
      }
      if (batchData.sale_price !== null) {
        sku.sale_price = parsePrice(batchData.sale_price)
      }
      if (batchData.status !== null) {
        sku.status = batchData.status
      }
    })

    showBatchDialog.value = false
    showToast('批量设置成功')
  }

// 从spec对象生成组合键
const generateCombinationKeyFromSpec = (spec) => {
  if (!spec || typeof spec !== 'object') return ''
  // 按照规格维度的顺序生成组合键
  const values = specDimensions.value.map(dim => spec[dim.name] || '')
  return values.join('_')
}

// 从spec对象生成显示文本
const generateSpecTextFromSpec = (spec) => {
  if (!spec || typeof spec !== 'object') return ''
  return Object.values(spec).join(' / ')
}

// 加载商品SKU
const loadProductSkus = async (id) => {
  try {
    console.log('📡 开始加载商品SKU，商品ID:', id)
    const response = await getProductSkus(id)
    console.log('✅ API返回的完整响应:', response)
    
    // 根据您的API响应结构调整
    let data = response
    if (response && response.code === 200) {
      data = response.data || []
      console.log('📊 提取的数据:', data)
    } else {
      console.warn('⚠️ 响应code不是200:', response?.code)
      data = []
    }

    if (data && data.length > 0) {
      console.log(`✅ 找到 ${data.length} 个SKU数据`)
      
      // 有SKU数据，设置为编辑SKU模式
      isNewSkuMode.value = false

      // 解析规格维度（从第一个SKU的spec中提取维度名称）
      const firstSku = data[0]
      if (firstSku.spec && typeof firstSku.spec === 'object') {
        console.log('🔍 解析规格维度...')
        const dimensions = Object.keys(firstSku.spec).map(key => {
          // 收集所有SKU中该维度的值
          const valuesSet = new Set()
          data.forEach(sku => {
            if (sku.spec && sku.spec[key]) {
              valuesSet.add(sku.spec[key])
            }
          })
          
          // 检查是否是颜色维度
          const isColorDimension = key.includes('颜色') || key.includes('color') || key.includes('Color')
          const values = Array.from(valuesSet).join(',')
          console.log(`维度: ${key}, 值: ${values}, 是颜色: ${isColorDimension}`)
          
          return {
            name: key,
            values: values,
            isColor: isColorDimension
          }
        })

        // 确保颜色维度在前
        const colorDimension = dimensions.find(dim => dim.isColor)
        const otherDimensions = dimensions.filter(dim => !dim.isColor)
        
        if (colorDimension) {
          console.log('🎨 找到颜色维度:', colorDimension.name)
          specDimensions.value = [
            { name: colorDimension.name, values: colorDimension.values },
            ...otherDimensions.map(dim => ({ name: dim.name, values: dim.values }))
          ]
        } else {
          specDimensions.value = dimensions.map(dim => ({ name: dim.name, values: dim.values }))
        }
        
        console.log('📋 最终规格维度:', specDimensions.value)
        
        // 初始化数据存储
        skuDataStore.value = {
          original: [],
          modified: {},
          dimensions: specDimensions.value.map(dim => dim.name)
        }
        
        // 加载原始SKU数据到存储
        data.forEach(sku => {
          const combinationKey = generateCombinationKeyFromSpec(sku.spec)
          const specText = generateSpecTextFromSpec(sku.spec)
          
          // 将字符串价格转换为数字
          const costPrice = parsePrice(sku.cost_price)
          const salePrice = parsePrice(sku.sale_price)
          
          console.log('📦 加载SKU到存储:', {
            id: sku.id,
            combinationKey,
            specText,
            cost_price: costPrice,
            sale_price: salePrice
          })
          
          // 保存到原始数据
          skuDataStore.value.original.push({
            ...sku,
            combinationKey,
            specText,
            cost_price: costPrice,
            sale_price: salePrice
          })
          
          // 保存到修改数据
        skuDataStore.value.modified[combinationKey] = {
          cost_price: costPrice,
          sale_price: salePrice,
          barcode: sku.barcode,
          status: sku.status,
          id: sku.id,
          sku_code: sku.sku_code
        }
        })
        
        console.log('💾 数据存储初始化完成:', {
          originalCount: skuDataStore.value.original.length,
          modifiedCount: Object.keys(skuDataStore.value.modified).length
        })
        
        // 生成SKU组合
        if (specDimensions.value.length > 0) {
          generateSkuCombinations()
        }
      } else {
        console.warn('⚠️ 第一个SKU没有spec字段或格式不正确')
        // 设置默认维度
        specDimensions.value = defaultSpecDimensions()
        showToast('SKU数据格式不正确')
      }
      
      showToast(`已加载 ${data.length} 个SKU`)
    } else {
      console.log('📭 没有SKU数据或数据为空')
      // 没有SKU数据，设置为新增SKU模式
      isNewSkuMode.value = true
      // 使用默认规格维度（只有颜色）
      specDimensions.value = defaultSpecDimensions()
      skuDataStore.value = {
        original: [],
        modified: {},
        dimensions: []
      }
      // 自动生成默认SKU组合（无颜色）
      generateSkuCombinations()
      showToast('暂无SKU数据，已自动创建默认SKU')
    }

  } catch (error) {
      console.error('❌ 加载商品SKU失败:', error)
      console.error('错误详情:', error.response || error.message)
      showToast('加载SKU失败')
      // 如果加载失败，默认设置为新增SKU模式
      isNewSkuMode.value = true
      specDimensions.value = defaultSpecDimensions()
      skuDataStore.value = {
        original: [],
        modified: {},
        dimensions: []
      }
      // 自动生成默认SKU组合（无颜色）
      generateSkuCombinations()
  }
}

// 准备提交数据 - 将数字价格转换为字符串格式
const prepareSubmitData = () => {
    // 在提交前保存所有数据到存储
    saveAllSkuToStore()
    
    const skus = skuList.value.map(sku => {
      const skuData = {
        spec: sku.spec,
        // 将数字转换为字符串，保留两位小数
        cost_price: typeof sku.cost_price === 'number' ? sku.cost_price.toFixed(2) : '0.00',
        sale_price: typeof sku.sale_price === 'number' ? sku.sale_price.toFixed(2) : '0.00',
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
    console.log('📤 提交数据:', JSON.stringify(submitData, null, 2))

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
    console.error('❌ 操作失败:', error)

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
  console.log('🚀 页面初始化，路由参数productId:', id)
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
.product-aggregate-form {
  background: #f7f8fa;
  min-height: 100vh;
  padding-bottom: 20px;
}

.form-wrap {
  :deep(.van-cell-group__title) {
    padding-top: 16px;
    padding-bottom: 8px;
    font-weight: 500;
    color: #333;
  }
  
  :deep(.van-field__label) {
    font-weight: 500;
  }
  
  /* 减少规格维度的行高 */
  :deep(.van-field) {
    min-height: auto;
    line-height: 1.2;
  }
  
  :deep(.van-cell) {
    padding: 6px 16px;
    min-height: auto;
  }
  
  :deep(.van-field__body) {
    line-height: 1.2;
  }
}

/* 规格维度样式 */
.spec-dimensions {
  padding: 16px 0;
}

.dimension-item {
  padding: 2px 0;
  border-bottom: 1px solid #e0e0e0;
}

.dimension-item:last-child {
  border-bottom: none;
}

.dimension-actions {
  display: flex;
  gap: 16px;
  padding: 16px;
}

/* SKU列表样式 */
.sku-list {
  padding: 16px;
}

.section-title {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 8px;
  padding: 16px;
  font-size: 16px;
  font-weight: 500;
  color: #333;
}

.sku-count-badge {
  display: inline-block;
  padding: 4px 8px;
  border-radius: 12px;
  font-size: 12px;
  font-weight: 500;
}

.sku-count-badge.total-count {
  background: #1989fa;
  color: #fff;
}

.sku-count-info {
  display: flex;
  gap: 8px;
  flex-wrap: wrap;
}

.count-tag {
  display: inline-block;
  padding: 4px 8px;
  border-radius: 12px;
  font-size: 12px;
  font-weight: 400;
}

.count-tag.existing {
  background: #f0f0f0;
  color: #666;
}

.count-tag.new {
  background: #f0f9ff;
  color: #1989fa;
}

.batch-set-btn {
  margin-left: auto;
}

.sku-item {
  margin-bottom: 8px;
  padding: 10px;
  border-radius: 8px;
  background: #fff;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
  
  &.new-sku {
    border: 2px dashed #1989fa;
  }
}

.sku-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 10px;
}

.sku-spec {
  font-weight: 500;
  color: #333;
}

.sku-status {
  display: flex;
  align-items: center;
  gap: 8px;
}

.sku-type-tag {
  padding: 4px 8px;
  border-radius: 12px;
  font-size: 12px;
  
  &.existing {
    background: #f0f0f0;
    color: #666;
  }
  
  &.new {
    background: #f0f9ff;
    color: #1989fa;
  }
}

.sku-fields {
  display: grid;
  gap: 6px;
}

.remove-sku-btn {
  margin-top: 8px;
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
<!-- src/views/stock/TakeCreate.vue -->
<template>
  <div class="stock-take-form">
    <van-nav-bar 
      :title="isEditMode ? '编辑盘点单' : '新建盘点单'" 
      fixed 
      placeholder 
      left-text="取消" 
      right-text="保存"
      @click-left="handleCancel" 
      @click-right="handleSubmit" 
    />

    <div class="form-container">
      <!-- 盘点基本信息 -->
      <van-form ref="formRef" @submit="handleSubmit">
        <!-- 盘点仓库 -->
        <van-field
          v-model="form.warehouse_name"
          readonly
          :clickable="!isEditMode"
          label="盘点仓库"
          placeholder="请选择仓库"
          :rules="[{ required: true, message: '请选择盘点仓库' }]"
          @click="handleWarehouseClick"
        >
          <template #input>
            {{ form.warehouse_name || '' }}
          </template>
          <template #right-icon>
            <van-icon v-if="!isEditMode" name="arrow" />
          </template>
        </van-field>
        
        <!-- 盘点日期 -->
        <van-field
          v-model="form.take_date"
          readonly
          clickable
          label="盘点日期"
          :placeholder="form.take_date || '请选择日期'"
          :rules="[{ required: true, message: '请选择盘点日期' }]"
          @click="showDatePicker = true"
        >
          <template #right-icon>
            <van-icon name="arrow" />
          </template>
        </van-field>
        
        <!-- 盘点说明 -->
        <van-field 
          v-model="form.remark" 
          name="remark" 
          label="备注说明" 
          type="textarea" 
          placeholder="请输入盘点说明（可选）" 
          rows="3" 
          maxlength="200"
          show-word-limit
        />
        
        <!-- 盘点商品明细选择区域 -->
        <div class="sku-section">
          <div class="section-title">
            <span>盘点商品明细</span>
            <van-button size="small" type="primary" @click="handleAddProduct" icon="plus">
              添加商品
            </van-button>
          </div>
          
          <!-- 盘点商品列表 -->
          <div class="sku-list">
            <van-swipe-cell v-for="(item, index) in form.items" :key="item.sku_id + '_' + index" class="sku-item">
              <van-cell class="sku-cell">
                <div class="product-grid">
                  <!-- 第一行：商品名,规格文本 -->
                  <div class="grid-row first-row">
                    <div class="left-column">
                      <span class="product-name">{{ item.product_name || '未知商品' }}</span>
                      <span class="spec-text-inline" v-if="item.spec">{{ item.spec }}</span>
                    </div>
                    <div class="right-column">
                      <!-- 实际库存输入框 -->
                      <van-field
                        v-model="item.actual_stock"
                        type="number"
                        placeholder="实际库存"
                        class="compact-field"
                        :error-message="item.actualStockError"
                        @blur="validateActualStock(item, index)"
                        @input="calculateDifference(item, index)"
                        :suffix="item.unit"
                      />
                    </div>
                  </div>
                  
                  <!-- 第二行：sku编码  单位 -->
                  <div class="grid-row second-row">
                    <div class="left-column">
                      <span class="sku-code">{{ item.product_no }}</span>
                      <span class="unit-text">单位: {{ item.unit || '个' }}</span>
                    </div>
                    <div class="right-column">
                      <!-- 差异显示 -->
                      <div class="difference-amount" :class="{ positive: item.difference > 0, negative: item.difference < 0 }">
                        {{ item.difference > 0 ? '+' : '' }}{{ item.difference }}
                      </div>
                      <div class="difference-label">差异</div>
                    </div>
                  </div>
                  
                  <!-- 第三行：当前库存 -->
                  <div class="grid-row third-row">
                    <div class="left-column">
                      <span class="stock-info">当前库存: {{ item.current_stock }} {{ item.unit || '个' }}</span>
                    </div>
                  </div>
                </div>
              </van-cell>
              
              <!-- 删除按钮 -->
              <template #right>
                <van-button square type="danger" text="删除" class="delete-btn" @click="removeProduct(index)" />
              </template>
            </van-swipe-cell>
            
            <!-- 空状态 -->
            <van-empty 
              v-if="form.items.length === 0" 
              description="暂无盘点商品" 
              image="default"
            />
          </div>
        </div>
        
        <!-- 盘点统计 -->
        <div class="total-section" v-if="form.items.length > 0">
          <div class="total-line">
            <span>盘点商品数：</span>
            <span class="amount">{{ form.items.length }}</span>
          </div>
          <div class="total-line">
            <span>盘盈总数：</span>
            <span class="amount positive">+{{ totalProfit }}</span>
          </div>
          <div class="total-line">
            <span>盘亏总数：</span>
            <span class="amount negative">{{ totalLoss }}</span>
          </div>
        </div>
      </van-form>
    </div>

    <!-- 仓库选择器组件 -->
    <WarehouseSelect
      ref="warehouseSelectRef"
      v-model="form.warehouse_id"
      :hide-trigger="true"
      :popup-title="'选择仓库'"
      :search-placeholder="'搜索仓库'"
      :only-enabled="true"
      @change="onWarehouseSelectChange"
      @confirm="onWarehouseConfirm"
    />

    <!-- 日期选择器 -->
    <van-popup v-model:show="showDatePicker" position="bottom">
      <van-date-picker
        v-model="currentDate"
        :min-date="minDate"
        :max-date="maxDate"
        @confirm="onDateConfirm"
        @cancel="showDatePicker = false"
      />
    </van-popup>

    <!-- 商品选择器 -->
    <van-popup v-model:show="showProductPicker" position="bottom" :style="{ height: '70%' }">
      <div class="product-picker-container">
        <SkuSelect
          v-model="selectedSkuIds"
          show-header
          show-footer
          show-filters
          header-title="选择盘点商品"
          @confirm="handleProductConfirm"
          @cancel="showProductPicker = false"
        />
      </div>
    </van-popup>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted, watch, nextTick } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { showToast, showConfirmDialog } from 'vant'
import dayjs from 'dayjs'
import { useStockStore } from '@/store/modules/stock'
import WarehouseSelect from '@/components/business/WarehouseSelect.vue'
import SkuSelect from '@/components/business/SkuSelect.vue'

const router = useRouter()
const route = useRoute()
const formRef = ref()
const warehouseSelectRef = ref()

// 状态管理
const stockStore = useStockStore()

// 表单数据
const form = reactive({
    id: '',
    warehouse_id: '',
    warehouse_name: '',
    take_date: dayjs().format('YYYY-MM-DD'),
    remark: '',
    items: []
})

// 弹窗显示状态
const showDatePicker = ref(false)
const showProductPicker = ref(false)

// 日期选择器相关
const currentDate = ref([])
const minDate = new Date(2020, 0, 1)
const maxDate = new Date(2030, 12, 31)

// 商品选择相关
const selectedSkus = ref([]) // 存储选中的商品
const selectedSkuIds = ref([]) // 存储选中的商品ID

// 计算属性
const isEditMode = computed(() => !!form.id)

const totalProfit = computed(() => {
    return form.items.reduce((total, item) => {
        const diff = Number(item.difference) || 0
        return diff > 0 ? total + diff : total
    }, 0)
})

const totalLoss = computed(() => {
    return form.items.reduce((total, item) => {
        const diff = Number(item.difference) || 0
        return diff < 0 ? total + Math.abs(diff) : total
    }, 0)
})

// 仓库选择器事件处理
const onWarehouseSelectChange = (id, name) => {
  console.log('=== 仓库选择变化事件触发 ===')
  console.log('新选择的仓库ID:', id)
  console.log('新选择的仓库名称:', name)
  console.log('当前表单仓库ID:', form.warehouse_id)
  console.log('当前表单仓库名称:', form.warehouse_name)
  console.log('当前盘点项数量:', form.items.length)
  console.log('当前选中SKU数量:', selectedSkus.value.length)
  
  if (id) {
    // 检查是否真的改变了仓库
    // 由于v-model双向绑定，我们需要检查是否有盘点项来判断是否需要清空
    if (form.items.length > 0 || selectedSkus.value.length > 0) {
      console.log('=== 检测到有盘点商品，需要确认切换 ===')
      showConfirmDialog({
        title: '切换仓库',
        message: '切换仓库将清空已选择的盘点商品，是否继续？',
      }).then(() => {
        // 用户确认，清空数据并切换仓库
        console.log('=== 用户确认切换仓库 ===')
        form.warehouse_id = id // 更新仓库ID
        form.warehouse_name = name // 更新仓库名称
        form.items = [] // 清空盘点项
        selectedSkus.value = [] // 清空选择列表
        console.log('=== 数据已清空 ===')
        console.log('清空后盘点项数量:', form.items.length)
        console.log('清空后选中SKU数量:', selectedSkus.value.length)
        showToast(`已切换到仓库：${name}`)
      }).catch(() => {
        // 用户取消，恢复原来的仓库
        console.log('=== 用户取消切换仓库 ===')
        // 恢复原来的仓库名称
        form.warehouse_name = form.warehouse_name
        // 通过重新加载仓库数据来恢复选择器状态
        nextTick(() => {
          if (warehouseSelectRef.value) {
            warehouseSelectRef.value.loadWarehouses()
          }
        })
      })
    } else {
      // 没有盘点商品，直接切换
      console.log('=== 没有盘点商品，直接切换仓库 ===')
      form.warehouse_id = id // 更新仓库ID
      form.warehouse_name = name // 更新仓库名称
      console.log('选择的仓库:', name, 'ID:', id)
    }
  } else {
    // 清空仓库选择
    console.log('=== 清空仓库选择 ===')
    form.warehouse_id = ''
    form.warehouse_name = ''
    form.items = [] // 清空盘点项
    selectedSkus.value = [] // 清空选择列表
    console.log('已清空仓库选择')
  }
}

// 仓库确认事件
const onWarehouseConfirm = (id, warehouse) => {
  console.log('仓库确认:', id, warehouse)
  if (id) {
    // 如果仓库发生改变，清空已选择的商品和盘点项
    if (form.warehouse_id && form.warehouse_id !== id) {
      showConfirmDialog({
        title: '切换仓库',
        message: '切换仓库将清空已选择的盘点商品，是否继续？',
      }).then(() => {
        // 用户确认，清空数据
        form.warehouse_id = id
        form.warehouse_name = warehouse?.name || ''
        form.items = [] // 清空盘点项
        selectedSkus.value = [] // 清空选择列表
        showToast(`已切换到仓库：${warehouse?.name || ''}`)
      }).catch(() => {
        // 用户取消，不做处理
        console.log('用户取消切换仓库')
      })
    } else {
      form.warehouse_id = id
      form.warehouse_name = warehouse?.name || ''
    }
  }
}

// 处理仓库字段点击
const handleWarehouseClick = () => {
  console.log('handleWarehouseClick 被调用')
  console.log('isEditMode:', isEditMode.value)
  
  // 如果是编辑模式，不允许选择仓库
  if (isEditMode.value) {
    showToast('编辑模式下不能切换仓库')
    return
  }
  
  // 直接调用组件的 openPicker 方法
  nextTick(() => {
    if (warehouseSelectRef.value && typeof warehouseSelectRef.value.openPicker === 'function') {
      try {
        warehouseSelectRef.value.openPicker()
        console.log('openPicker 调用成功')
      } catch (error) {
        console.error('openPicker 调用失败:', error)
        showToast('仓库选择器打开失败')
      }
    } else {
      console.error('warehouseSelectRef.value 为空或 openPicker 方法不存在')
      showToast('仓库选择器未准备好')
    }
  })
}

// 手动触发仓库选择器打开
const openWarehouseSelect = () => {
  console.log('openWarehouseSelect 被调用')
  console.log('isEditMode:', isEditMode.value)
  console.log('warehouseSelectRef.value:', warehouseSelectRef.value)
  
  // 如果是编辑模式，不允许选择仓库
  if (isEditMode.value) {
    showToast('编辑模式下不能切换仓库')
    return
  }
  
  // 确保组件已经挂载
  nextTick(() => {
    if (warehouseSelectRef.value) {
      console.log('调用 openPicker')
      warehouseSelectRef.value.openPicker()
    } else {
      console.error('warehouseSelectRef.value 为空')
      showToast('仓库选择器未准备好')
    }
  })
}

// 处理添加商品
const handleAddProduct = () => {
  // 检查是否已选择仓库
  if (!form.warehouse_id) {
    showToast('请先选择仓库')
    return
  }
  
  // 打开商品选择器
  showProductPicker.value = true
}

// 处理SKU选择
const handleSkuSelect = (sku) => {
  // 检查是否已经选择过
  const exists = selectedSkus.value.find(item => item.id === sku.id)
  if (exists) {
    // 如果已选择，则取消选择
    selectedSkus.value = selectedSkus.value.filter(item => item.id !== sku.id)
  } else {
    // 添加到选择列表
    selectedSkus.value.push(sku)
  }
}

// 处理商品选择确认
const handleProductConfirm = (confirmData) => {
  try {
    const selectedData = confirmData?.selectedData || [] // 从SkuSelect的confirm事件获取选中数据
    const addedCount = selectedData.length
    
    // 添加选中的商品到盘点项，避免重复添加
    for (const sku of selectedData) {
      const exists = form.items.find(item => item.sku_id === sku.id)
      if (!exists) {
        // 更灵活地获取商品名称，适配不同的数据结构
        let productName = '未知商品'
        if (sku.product?.name) {
          productName = sku.product.name
        } else if (sku.product_name) {
          productName = sku.product_name
        } else if (sku.name) {
          productName = sku.name
        }

        // 获取商品编码
        let productNo = ''
        if (sku.sku_code) {
          productNo = sku.sku_code
        } else if (sku.product_no) {
          productNo = sku.product_no
        } else if (sku.no) {
          productNo = sku.no
        }

        // 获取规格
        let spec = ''
        if (sku.spec_text) {
          spec = sku.spec_text
        } else if (sku.spec && typeof sku.spec === 'string') {
          spec = sku.spec
        } else if (sku.spec && typeof sku.spec === 'object') {
          spec = Object.entries(sku.spec).map(([key, value]) => `${key}:${value}`).join(' ')
        }

        const currentStock = sku.quantity || sku.stock_quantity || sku.stock || 0
        const actualStock = currentStock // 默认实际库存为当前库存，用户可以修改
        
        const newItem = {
          product_id: sku.product?.id || sku.product_id,
          sku_id: sku.id, // SKU ID
          product_name: productName,
          product_no: productNo,
          spec: spec,
          unit: sku.unit || '个',
          current_stock: currentStock,
          actual_stock: actualStock,
          difference: actualStock - currentStock, // 计算差异数量
          actualStockError: ''
        }
        
        form.items.push(newItem)
      }
    }

    // 清空选择列表并关闭弹窗
    selectedSkuIds.value = [] // 清空选中ID数组
    showProductPicker.value = false
    showToast(`成功添加 ${addedCount} 个商品`)
  } catch (error) {
    console.error('添加商品失败:', error)
    showToast('添加失败')
  }
}

// 表单验证
const validateForm = () => {
    if (!form.warehouse_id) {
        showToast('请选择盘点仓库')
        return false
    }
    
    if (!form.take_date) {
        showToast('请选择盘点日期')
        return false
    }
    
    if (form.items.length === 0) {
        showToast('请添加盘点商品')
        return false
    }
    
    // 验证每个商品的实际库存
    for (let i = 0; i < form.items.length; i++) {
        const item = form.items[i]
        // 如果实际库存为空，则不允许提交
        if (item.actual_stock === '') {
            showToast(`第${i + 1}个商品的实际库存不能为空`)
            return false
        }
        
        const actualStock = Number(item.actual_stock) || 0
        
        if (actualStock < 0) {
            showToast(`第${i + 1}个商品的实际库存不能为负数`)
            return false
        }
        
        if (item.actualStockError) {
            showToast(`第${i + 1}个商品的实际库存输入有误`)
            return false
        }
    }
    
    // 不再检查是否有盘点差异，允许用户提交所有商品（包括没有差异的商品）
    
    return true
}

// 事件处理
const handleCancel = async () => {
    try {
        await showConfirmDialog({
            title: '确认取消',
            message: '确定要取消当前操作吗？未保存的数据将丢失。'
        })
        
        router.back()
    } catch {
        // 用户取消
    }
}

const handleSubmit = async () => {
    if (!validateForm()) {
        return
    }
    
    try {
        const submitData = {
                    warehouse_id: form.warehouse_id,
                    remark: form.remark,
                    items: form.items.map(item => ({
                        product_id: item.product_id,
                        sku_id: item.sku_id,
                        actual_quantity: Number(item.actual_stock) || 0
                    }))
                }
        
        let result
        if (isEditMode.value) {
            result = await stockStore.updateStockTakeData(form.id, submitData)
            showToast('修改成功')
        } else {
            result = await stockStore.createStockTake(submitData)
            showToast('创建成功')
        }
        
        // 根据后端返回结果跳转
        if (result.data?.id) {
            router.push(`/stock/take/detail/${result.data.id}`)
        } else {
            router.back()
        }
    } catch (error) {
        if (error.message !== 'cancel') {
            console.error('保存失败:', error)
            showToast(error.message || '保存失败')
        }
    }
}

const onDateConfirm = ({ selectedValues }) => {
    const [year, month, day] = selectedValues
    form.take_date = `${year}-${String(month).padStart(2, '0')}-${String(day).padStart(2, '0')}`
    showDatePicker.value = false
}

const removeProduct = async (index) => {
    try {
        await showConfirmDialog({
            title: '确认删除',
            message: '确定要删除这个商品吗？'
        })
        
        form.items.splice(index, 1)
        showToast('删除成功')
    } catch {
        // 用户取消删除
    }
}

// 验证实际库存输入
const validateActualStock = (item, index) => {
    // 如果输入为空字符串，不进行验证，允许用户清空输入
    if (item.actual_stock === '') {
        item.actualStockError = ''
        return true
    }
    
    const actualStock = Number(item.actual_stock)
    
    if (isNaN(actualStock)) {
        item.actualStockError = '请输入有效数字'
        return false
    }
    
    if (actualStock < 0) {
        item.actualStockError = '库存不能为负数'
        return false
    }
    
    item.actualStockError = ''
    return true
}

// 计算差异数量
const calculateDifference = (item, index) => {
    const currentStock = Number(item.current_stock) || 0
    // 处理空字符串情况，如果为空则差异为0
    const actualStock = item.actual_stock === '' ? 0 : (Number(item.actual_stock) || 0)
    item.difference = actualStock - currentStock
    
    // 清除错误信息
    if (item.actualStockError) {
        validateActualStock(item, index)
    }
}

// 监听表单项变化，但不强制设置默认值
watch(() => form.items, (newItems) => {
    newItems.forEach((item, index) => {
        // 只在实际库存为undefined时才设置默认值，允许空字符串
        if (item.actual_stock === undefined || item.actual_stock === null) {
            item.actual_stock = item.current_stock
        }
        
        // 重新计算差异
        const current = Number(item.current_stock) || 0
        const actual = item.actual_stock === '' ? 0 : (Number(item.actual_stock) || 0)
        item.difference = actual - current
    })
}, { deep: true })

// 初始化页面
const initPage = async () => {
    const { id } = route.params
    
    if (id) {
        // 编辑模式，加载数据
        try {
            await stockStore.loadTakeDetail(id)
            const detail = stockStore.currentTake
            Object.assign(form, {
                id: detail.id,
                warehouse_id: detail.warehouse_id,
                warehouse_name: detail.warehouse?.name || detail.warehouse_name,
                take_date: detail.take_date || detail.created_at?.split(' ')[0],
                remark: detail.remark || '',
                items: detail.items ? detail.items.map(item => {
                    const newItem = {
                        product_id: item.product_id,
                        sku_id: item.sku_id,
                        product_name: item.product?.name || item.product_name,
                        product_no: item.product?.product_no || item.product_no || '',
                        spec: item.product?.spec || item.spec || '',
                        unit: item.product?.unit || item.unit || '个',
                        current_stock: item.system_quantity,
                        actual_stock: item.actual_quantity,
                        difference: item.difference_quantity,
                        actualStockError: ''
                    }
                    return newItem
                }) : []
            })
            
            // 设置日期选择器的当前值
            if (detail.take_date) {
                const date = new Date(detail.take_date)
                currentDate.value = [date.getFullYear(), date.getMonth() + 1, date.getDate()]
            }
        } catch (error) {
            console.error('加载详情失败:', error)
            showToast('加载失败')
        }
    } else {
        // 新建模式，设置默认日期为今天
        const today = new Date()
        currentDate.value = [today.getFullYear(), today.getMonth() + 1, today.getDate()]
    }
}

onMounted(() => {
    initPage()
})
</script>

<style lang="scss" scoped>
.stock-take-form {
  min-height: 100vh;
  background-color: #f7f8fa;
  padding-top: 46px;
  padding-bottom: 80px;
}

.form-container {
  padding: 0;
}

.sku-section {
  margin-top: 16px;
  background-color: #fff;
  border-radius: 8px;
  overflow: hidden;

  .section-title {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 16px;
    border-bottom: 1px solid #ebedf0;
    font-size: 14px;
    font-weight: 600;
    color: #323233;
  }
}

.sku-list {
  .sku-item {
    border-bottom: 1px solid #ebedf0;

    &:last-child {
      border-bottom: none;
    }

    .sku-cell {
      padding: 12px 16px;

      .product-title {
        display: flex;
        align-items: center;
        margin-bottom: 4px;

        .product-name {
          font-size: 14px;
          font-weight: 600;
          color: #323233;
          margin-right: 8px;
        }

        .sku-code {
          font-size: 12px;
          color: #969799;
        }
      }

      .product-label {
        .spec-text,
        .unit-text,
        .stock-info {
          font-size: 12px;
          color: #969799;
          margin-bottom: 2px;
        }

        .stock-info {
          color: #646566;
          font-weight: 500;
        }
      }

      .item-details {
        .stock-fields {
          display: flex;
          gap: 8px;
          margin-bottom: 8px;

          .input-field {
            flex: 1;

            .compact-field {
              :deep(.van-field__control) {
                text-align: center;
                font-size: 14px;
                font-weight: 600;
              }

              :deep(.van-field__extra) {
                font-size: 12px;
                color: #969799;
              }
            }

            .current-stock-field {
              .compact-field {
                :deep(.van-field__control) {
                  color: #646566;
                  background-color: #f7f8fa;
                }
              }
            }

            .actual-stock-field {
              .compact-field {
                :deep(.van-field__control) {
                  color: #323233;
                  border: 1px solid #ebedf0;
                  border-radius: 4px;
                  padding: 4px 8px;
                }
              }
            }
          }
        }

        .item-total {
          display: flex;
          flex-direction: column;
          align-items: flex-end;

          .difference-amount {
            font-size: 14px;
            font-weight: 600;

            &.positive {
              color: #07c160;
            }

            &.negative {
              color: #ee0a24;
            }
          }

          .difference-label {
            font-size: 12px;
            color: #969799;
          }
        }
      }
    }

    .delete-btn {
      height: 100%;
    }
  }
}

.total-section {
  margin-top: 16px;
  background-color: #fff;
  border-radius: 8px;
  padding: 16px;

  .total-line {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 8px;

    &:last-child {
      margin-bottom: 0;
    }

    span {
      font-size: 14px;
      color: #323233;
    }

    .amount {
      font-weight: 600;
      font-size: 16px;

      &.positive {
        color: #07c160;
      }

      &.negative {
        color: #ee0a24;
      }
    }
  }
}

// 仓库选择器样式
.warehouse-picker {
  .warehouse-list {
    .warehouse-item {
      padding: 16px;
      border-bottom: 1px solid #ebedf0;
      background-color: #fff;
      transition: background-color 0.2s;

      &:last-child {
        border-bottom: none;
      }

      &:active {
        background-color: #f2f3f5;
      }

      .warehouse-name {
        font-size: 14px;
        font-weight: 600;
        color: #323233;
        margin-bottom: 4px;
      }

      .warehouse-address {
        font-size: 12px;
        color: #969799;
      }
    }
  }
}

// 日期选择器样式
.date-picker {
  :deep(.van-picker__confirm) {
    color: var(--van-primary-color);
  }
}

// 商品选择器样式
.product-picker-container {
  height: 100%;
  display: flex;
  flex-direction: column;
  
  .picker-content {
    flex: 1;
    overflow: hidden;
    background: #f7f8fa;
  }
  
  // 为选择的商品添加选中状态样式
  :deep(.sku-card) {
    position: relative;
    
    &.selected {
      border: 2px solid var(--van-primary-color);
      background: #f0f9ff;
      
      &::after {
        content: '✓';
        position: absolute;
        top: 8px;
        right: 8px;
        width: 20px;
        height: 20px;
        background: var(--van-primary-color);
        color: white;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 12px;
        font-weight: bold;
      }
    }
  }
}

// 响应式设计
@media (max-width: 375px) {
  .form-container {
    padding: 0;
  }
}

// 商品列表样式优化
.sku-list {
  .sku-item {
    margin-bottom: 8px;
    border-radius: 8px;
    overflow: hidden;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
    background-color: #fff;
    border-bottom: none; // 移除原有的边框
    
    &:last-child {
      margin-bottom: 0;
    }
  }
  
  .sku-cell {
    padding: 12px 16px;
    background-color: #fff;
    
    &:after {
      display: none;
    }
  }
}

.product-grid {
  display: flex;
  flex-direction: column;
  gap: 4px;
  width: 100%;
}

.grid-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 12px;
  
  .left-column {
    flex: 1;
    display: flex;
    align-items: center;
    gap: 8px;
    min-width: 0;
  }
  
  .right-column {
    flex-shrink: 0;
    display: flex;
    align-items: center;
    justify-content: flex-end;
    min-width: 100px;
  }
}

.first-row {
  .left-column {
    .product-name {
      font-weight: bold;
      color: #323233;
      font-size: 14px; // 参考OrderForm
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
      max-width: 150px;
    }
    
    .spec-text-inline {
      font-size: 12px; // 参考OrderForm
      color: #969799;
      white-space: nowrap;
    }
  }
}

.second-row {
  .left-column {
    .sku-code {
      color: #646566;
      font-size: 12px; // 参考OrderForm
      font-weight: normal;
      background: #f5f5f5;
      padding: 1px 4px;
      border-radius: 3px;
    }
    
    .unit-text {
      font-size: 12px; // 参考OrderForm
      color: #969799;
    }
  }
}

.third-row {
  .left-column {
    .stock-info {
      font-size: 12px; // 参考OrderForm
      color: #1890ff;
      white-space: nowrap;
    }
  }
  
  .right-column {
    flex-direction: column;
    align-items: flex-end;
    gap: 4px;
  }
}

// 紧凑字段样式
.compact-field {
  border: 1px solid #e0e0e0;
  border-radius: 4px;
  background: #fff;
  transition: all 0.2s;
  height: 22px;
  width: 85px;
  overflow: hidden;
  vertical-align: top;
  padding: 0 !important;
  box-sizing: border-box;
  
  :deep(.van-field__body) {
    min-height: auto;
    height: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 0 !important;
    margin: 0;
    box-sizing: border-box;
  }
  
  :deep(.van-field__control) {
    font-size: 12px !important; // 参考OrderForm
    font-weight: 500;
    color: #323233;
    text-align: center;
    padding: 0 !important;
    height: 100% !important;
    line-height: 22px !important;
    margin: 0;
    border: none;
    outline: none;
    background: transparent;
    display: block;
    max-height: 22px;
  }
  
  :deep(.van-field__extra) {
    color: #969799;
    font-size: 10px; // 参考OrderForm
    padding-left: 2px;
    flex-shrink: 0;
    line-height: 22px;
    height: 100%;
    display: flex;
    align-items: center;
    margin: 0;
  }
  
  &:focus-within {
    border-color: #1989fa;
    box-shadow: 0 0 0 2px rgba(25, 137, 250, 0.1);
  }
}

.difference-amount {
  font-size: 14px; // 参考OrderForm
  font-weight: 600;
  
  &.positive {
    color: #07c160;
  }
  
  &.negative {
    color: #ee0a24;
  }
}

.difference-label {
  font-size: 12px; // 参考OrderForm
  color: #969799;
}

.delete-btn {
  height: 100%;
}

.sku-section {
  margin-top: 16px;
  background-color: #fff;
  border-radius: 8px;
  overflow: hidden;

  .section-title {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 16px;
    border-bottom: 1px solid #ebedf0;
    font-size: 14px;
    font-weight: 600;
    color: #323233;
  }
}

// 仓库选择器样式
.warehouse-picker {
  .warehouse-list {
    .warehouse-item {
      padding: 16px;
      border-bottom: 1px solid #ebedf0;
      background-color: #fff;
      transition: background-color 0.2s;

      &:last-child {
        border-bottom: none;
      }

      &:active {
        background-color: #f2f3f5;
      }

      .warehouse-name {
        font-size: 14px;
        font-weight: 600;
        color: #323233;
        margin-bottom: 4px;
      }

      .warehouse-address {
        font-size: 12px;
        color: #969799;
      }
    }
  }
}

// 日期选择器样式
.date-picker {
  :deep(.van-picker__confirm) {
    color: var(--van-primary-color);
  }
}

// 商品选择器样式
.product-picker-container {
  height: 100%;
  display: flex;
  flex-direction: column;
  
  .picker-content {
    flex: 1;
    overflow: hidden;
    background: #f7f8fa;
  }
  
  // 为选择的商品添加选中状态样式
  :deep(.sku-card) {
    position: relative;
    
    &.selected {
      border: 2px solid var(--van-primary-color);
      background: #f0f9ff;
      
      &::after {
        content: '✓';
        position: absolute;
        top: 8px;
        right: 8px;
        width: 20px;
        height: 20px;
        background: var(--van-primary-color);
        color: white;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 12px;
        font-weight: bold;
      }
    }
  }
}

// 响应式设计
@media (max-width: 375px) {
  .form-container {
    padding: 0 12px;
  }
}
</style>
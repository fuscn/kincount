// src/store/modules/return.js
import { defineStore } from 'pinia'
import { ref, computed } from 'vue'

export const useReturnStore = defineStore('return', () => {
  // 通用状态
  const returnType = ref('') // 当前退货类型: 'sale' 或 'purchase'
  
  // 订单商品列表 - 按类型分开存储
  const saleOrderItems = ref([])     // 销售订单商品
  const purchaseOrderItems = ref([]) // 采购订单商品
  
  // 仓库列表缓存
  const warehouseList = ref([])
  
  // 退货原因选项 - 根据API调整
  const returnReasonOptions = ref([
    { text: '质量问题', value: 'quality' },
    { text: '客户原因', value: 'customer' },
    { text: '发错货', value: 'wrong_delivery' },
    { text: '其他', value: 'other' }
  ])
  
  // 退货状态常量
  const RETURN_STATUS = {
    PENDING: 1,      // 待审核
    AUDITED: 2,      // 已审核
    COMPLETED: 3,    // 已完成
    CANCELLED: 4     // 已取消
  }
  
  // 计算属性
  const currentOrderItems = computed(() => {
    return returnType.value === 'sale' ? saleOrderItems.value : purchaseOrderItems.value
  })
  
  // 获取退货类型文本
  const getReturnTypeText = (type) => {
    const typeMap = {
      quality: '质量问题',
      customer: '客户原因',
      wrong_delivery: '发错货',
      other: '其他'
    }
    return typeMap[type] || ''
  }
  
  // 获取状态文本
  const getStatusText = (status) => {
    const statusMap = {
      [RETURN_STATUS.PENDING]: '待审核',
      [RETURN_STATUS.AUDITED]: '已审核',
      [RETURN_STATUS.COMPLETED]: '已完成',
      [RETURN_STATUS.CANCELLED]: '已取消'
    }
    return statusMap[status] || `未知状态(${status})`
  }
  
  // 设置退货类型
  const setReturnType = (type) => {
    returnType.value = type
  }
  
  // 设置订单商品 - 根据API调整数据结构
  const setOrderItems = (type, items) => {
    const processedItems = (items || []).map(item => ({
      // SKU级数据
      sku_id: item.sku_id || item.id || '',
      product_id: item.product_id || '',
      product_name: item.product_name || item.name || '',
      product_no: item.product_no || item.code || '',
      spec: item.spec || item.specification || '',
      unit: item.unit || '个',
      sale_quantity: item.quantity || item.sale_quantity || 0,
      purchase_quantity: item.quantity || item.purchase_quantity || 0,
      unit_price: item.price || item.unit_price || 0,
      stock_quantity: item.stock_quantity || 0, // 当前库存
      
      // 退货表单用字段
      return_quantity: 1,
      return_reason: '', // 退货原因（采购退货特有）
      return_remark: '', // 退货备注（采购退货特有）
      priceError: '',
      quantityError: ''
    }))
    
    if (type === 'sale') {
      saleOrderItems.value = processedItems
    } else if (type === 'purchase') {
      purchaseOrderItems.value = processedItems
    }
  }
  
  // 清空订单商品
  const clearOrderItems = (type) => {
    if (type === 'sale') {
      saleOrderItems.value = []
    } else if (type === 'purchase') {
      purchaseOrderItems.value = []
    } else {
      // 清空所有
      saleOrderItems.value = []
      purchaseOrderItems.value = []
    }
  }
  
  // 添加单个商品
  const addOrderItem = (type, item) => {
    if (type === 'sale') {
      if (!saleOrderItems.value.some(exist => 
        exist.sku_id === item.sku_id && 
        exist.spec === item.spec
      )) {
        saleOrderItems.value.push(item)
      }
    } else if (type === 'purchase') {
      if (!purchaseOrderItems.value.some(exist => 
        exist.sku_id === item.sku_id && 
        exist.spec === item.spec
      )) {
        purchaseOrderItems.value.push(item)
      }
    }
  }
  
  // 删除单个商品
  const removeOrderItem = (type, skuId, spec) => {
    if (type === 'sale') {
      const index = saleOrderItems.value.findIndex(item => 
        item.sku_id === skuId && 
        item.spec === spec
      )
      if (index !== -1) {
        saleOrderItems.value.splice(index, 1)
      }
    } else if (type === 'purchase') {
      const index = purchaseOrderItems.value.findIndex(item => 
        item.sku_id === skuId && 
        item.spec === spec
      )
      if (index !== -1) {
        purchaseOrderItems.value.splice(index, 1)
      }
    }
  }
  
  // 获取商品列表（过滤已选商品）
  const getAvailableOrderItems = (type, selectedItems = []) => {
    const items = type === 'sale' ? saleOrderItems.value : purchaseOrderItems.value
    
    return items.filter(item => {
      // 过滤已选择的商品（根据sku_id和spec判断）
      return !selectedItems.some(selected => 
        selected.sku_id === item.sku_id && 
        selected.spec === item.spec
      )
    })
  }
  
  // 检查商品是否已选择
  const isItemSelected = (type, skuId, spec, selectedItems = []) => {
    return selectedItems.some(item => 
      item.sku_id === skuId && 
      item.spec === spec
    )
  }
  
  // 设置仓库列表
  const setWarehouseList = (list) => {
    warehouseList.value = (list || []).map(warehouse => ({
      id: warehouse.id,
      name: warehouse.name,
      code: warehouse.code || '',
      address: warehouse.address || '无',
      manager: warehouse.manager || '无',
      phone: warehouse.phone || '',
      status: warehouse.status || 1
    }))
  }
  
  // 获取仓库列表
  const getWarehouseList = () => {
    return warehouseList.value
  }
  
  // 根据仓库ID获取仓库名称
  const getWarehouseName = (warehouseId) => {
    const warehouse = warehouseList.value.find(w => w.id === warehouseId)
    return warehouse ? warehouse.name : ''
  }
  
  // 构建销售退货提交数据
  const buildSaleReturnData = (formData) => {
    return {
      type: 0, // 销售退货类型标识（数据库定义：0-销售退货 1-采购退货）
      order_id: formData.order_id,
      order_no: formData.order_no,
      warehouse_id: formData.warehouse_id,
      return_date: formData.return_date,
      return_type: formData.return_type,
      remark: formData.remark,
      items: (formData.items || []).map(item => ({
        sku_id: item.sku_id,
        return_quantity: item.return_quantity,
        price: item.unit_price
      }))
    }
  }
  
  // 构建采购退货提交数据
  const buildPurchaseReturnData = (formData) => {
    return {
      type: 1, // 采购退货类型标识（数据库定义：0-销售退货 1-采购退货）
      supplier_id: formData.supplier_id,
      supplier_name: formData.supplier_name,
      purchase_order_id: formData.order_id,
      purchase_order_no: formData.order_no,
      warehouse_id: formData.warehouse_id,
      return_date: formData.return_date,
      return_type: formData.return_type,
      remark: formData.remark,
      items: (formData.items || []).map(item => ({
        sku_id: item.sku_id,
        return_quantity: item.return_quantity,
        price: item.unit_price,
        reason: item.return_reason || '', // 采购退货特有
        remark: item.return_remark || ''  // 采购退货特有
      }))
    }
  }
  
  // 解析退货详情数据
  const parseReturnDetail = (detail, type) => {
    const baseData = {
      id: detail.id,
      order_id: type === 'sale' ? detail.sale_order_id : detail.purchase_order_id,
      order_no: type === 'sale' ? detail.sale_order_no : detail.purchase_order_no,
      warehouse_id: detail.warehouse_id,
      warehouse_name: detail.warehouse_name,
      return_date: detail.return_date,
      return_type: detail.return_type,
      remark: detail.remark,
      status: detail.status,
      created_at: detail.created_at,
      items: (detail.items || []).map(item => ({
        sku_id: item.sku_id,
        product_id: item.product_id,
        product_name: item.product_name,
        product_no: item.product_no,
        spec: item.spec,
        unit: item.unit,
        return_quantity: item.return_quantity,
        unit_price: item.price,
        reason: item.reason || '', // 采购退货特有
        remark: item.remark || '', // 采购退货特有
        priceError: '',
        quantityError: ''
      }))
    }
    
    if (type === 'purchase') {
      baseData.supplier_id = detail.supplier_id
      baseData.supplier_name = detail.supplier_name
    }
    
    return baseData
  }
  
  // 计算退货金额
  const calculateReturnAmount = (items) => {
    return (items || []).reduce((total, item) => {
      const price = Number(item.unit_price) || 0
      const quantity = Number(item.return_quantity) || 0
      return total + (price * quantity)
    }, 0)
  }
  
  // 计算退货总数量
  const calculateReturnQuantity = (items) => {
    return (items || []).reduce((total, item) => {
      return total + (Number(item.return_quantity) || 0)
    }, 0)
  }
  
  // 验证退货商品数据
  const validateReturnItems = (items, maxQuantityField = 'sale_quantity') => {
    const errors = []
    
    if (!items || items.length === 0) {
      errors.push('请至少添加一个退货商品')
      return errors
    }
    
    items.forEach((item, index) => {
      const quantity = Number(item.return_quantity) || 0
      const maxQuantity = Number(item[maxQuantityField]) || 0
      const price = Number(item.unit_price) || 0
      
      if (quantity <= 0) {
        errors.push(`第${index + 1}个商品：退货数量必须大于0`)
      }
      
      if (quantity > maxQuantity) {
        errors.push(`第${index + 1}个商品：退货数量不能超过${maxQuantity}`)
      }
      
      if (price <= 0) {
        errors.push(`第${index + 1}个商品：退货单价必须大于0`)
      }
      
      if (isNaN(price)) {
        errors.push(`第${index + 1}个商品：请输入有效的单价`)
      }
    })
    
    return errors
  }
  
  // 清空所有状态
  const clearAll = () => {
    returnType.value = ''
    saleOrderItems.value = []
    purchaseOrderItems.value = []
    warehouseList.value = []
  }
  
  return {
    // 状态
    returnType,
    saleOrderItems,
    purchaseOrderItems,
    returnReasonOptions,
    warehouseList,
    RETURN_STATUS,
    
    // 计算属性
    currentOrderItems,
    
    // 方法
    setReturnType,
    setOrderItems,
    clearOrderItems,
    addOrderItem,
    removeOrderItem,
    getAvailableOrderItems,
    isItemSelected,
    
    // 仓库相关
    setWarehouseList,
    getWarehouseList,
    getWarehouseName,
    
    // 数据构建和解析
    buildSaleReturnData,
    buildPurchaseReturnData,
    parseReturnDetail,
    
    // 工具方法
    getReturnTypeText,
    getStatusText,
    calculateReturnAmount,
    calculateReturnQuantity,
    validateReturnItems,
    
    // 清空
    clearAll
  }
})
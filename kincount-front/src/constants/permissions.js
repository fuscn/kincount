// src/constants/permissions.js
// 权限常量定义 - 基于数据库结构和业务模块设计

// ==================================================
// 权限键常量定义
// ==================================================

export const PERM = {
  // ==================== 仪表盘 ====================
  DASHBOARD_VIEW: 'dashboard:view',

  // ==================== 商品管理 ====================
  // 商品
  PRODUCT_VIEW: 'product:view',
  PRODUCT_ADD: 'product:add',
  PRODUCT_EDIT: 'product:edit',
  PRODUCT_DELETE: 'product:delete',
  
  // SKU管理
  SKU_VIEW: 'sku:view',
  SKU_ADD: 'sku:add',
  SKU_EDIT: 'sku:edit',
  SKU_DELETE: 'sku:delete',
  
  // 分类管理
  CATEGORY_VIEW: 'category:view',
  CATEGORY_ADD: 'category:add',
  CATEGORY_EDIT: 'category:edit',
  CATEGORY_DELETE: 'category:delete',
  
  // 品牌管理
  BRAND_VIEW: 'brand:view',
  BRAND_ADD: 'brand:add',
  BRAND_EDIT: 'brand:edit',
  BRAND_DELETE: 'brand:delete',

  // ==================== 客户管理 ====================
  CUSTOMER_VIEW: 'customer:view',
  CUSTOMER_ADD: 'customer:add',
  CUSTOMER_EDIT: 'customer:edit',
  CUSTOMER_DELETE: 'customer:delete',

  // ==================== 供应商管理 ====================
  SUPPLIER_VIEW: 'supplier:view',
  SUPPLIER_ADD: 'supplier:add',
  SUPPLIER_EDIT: 'supplier:edit',
  SUPPLIER_DELETE: 'supplier:delete',

  // ==================== 仓库管理 ====================
  WAREHOUSE_VIEW: 'warehouse:view',
  WAREHOUSE_ADD: 'warehouse:add',
  WAREHOUSE_EDIT: 'warehouse:edit',
  WAREHOUSE_DELETE: 'warehouse:delete',

  // ==================== 采购管理 ====================
  // 采购订单
  PURCHASE_ORDER_VIEW: 'purchase_order:view',
  PURCHASE_ORDER_ADD: 'purchase_order:add',
  PURCHASE_ORDER_EDIT: 'purchase_order:edit',
  PURCHASE_ORDER_AUDIT: 'purchase_order:audit',
  PURCHASE_ORDER_DELETE: 'purchase_order:delete',
  
  // 采购入库
  PURCHASE_STOCK_VIEW: 'purchase_stock:view',
  PURCHASE_STOCK_ADD: 'purchase_stock:add',
  PURCHASE_STOCK_AUDIT: 'purchase_stock:audit',
  PURCHASE_STOCK_CANCEL: 'purchase_stock:cancel',

  // ==================== 销售管理 ====================
  // 销售订单
  SALE_ORDER_VIEW: 'sale_order:view',
  SALE_ORDER_ADD: 'sale_order:add',
  SALE_ORDER_EDIT: 'sale_order:edit',
  SALE_ORDER_AUDIT: 'sale_order:audit',
  SALE_ORDER_DELETE: 'sale_order:delete',
  
  // 销售出库
  SALE_STOCK_VIEW: 'sale_stock:view',
  SALE_STOCK_ADD: 'sale_stock:add',
  SALE_STOCK_AUDIT: 'sale_stock:audit',
  SALE_STOCK_CANCEL: 'sale_stock:cancel',

  // ==================== 退货管理 ====================
  // 退货订单（统一）
  RETURN_ORDER_VIEW: 'return_order:view',
  RETURN_ORDER_ADD: 'return_order:add',
  RETURN_ORDER_EDIT: 'return_order:edit',
  RETURN_ORDER_AUDIT: 'return_order:audit',
  RETURN_ORDER_DELETE: 'return_order:delete',
  
  // 退货出入库
  RETURN_STOCK_VIEW: 'return_stock:view',
  RETURN_STOCK_CREATE: 'return_stock:create',
  RETURN_STOCK_AUDIT: 'return_stock:audit',
  RETURN_STOCK_CANCEL: 'return_stock:cancel',

  // ==================== 库存管理 ====================
  // 库存查询
  STOCK_VIEW: 'stock:view',
  
  // 库存盘点
  STOCK_TAKE_VIEW: 'stock_take:view',
  STOCK_TAKE_ADD: 'stock_take:add',
  STOCK_TAKE_AUDIT: 'stock_take:audit',
  STOCK_TAKE_CANCEL: 'stock_take:cancel',
  
  // 库存调拨
  STOCK_TRANSFER_VIEW: 'stock_transfer:view',
  STOCK_TRANSFER_ADD: 'stock_transfer:add',
  STOCK_TRANSFER_AUDIT: 'stock_transfer:audit',
  STOCK_TRANSFER_CANCEL: 'stock_transfer:cancel',
  
  // 库存预警
  STOCK_WARNING_VIEW: 'stock_warning:view',

  // ==================== 账款管理 ====================
  // 应收款
  ACCOUNT_RECEIVABLE_VIEW: 'account_receivable:view',
  ACCOUNT_RECEIVABLE_SETTLE: 'account_receivable:settle',
  
  // 应付款
  ACCOUNT_PAYABLE_VIEW: 'account_payable:view',
  ACCOUNT_PAYABLE_SETTLE: 'account_payable:settle',
  
  // 核销记录
  SETTLEMENT_VIEW: 'settlement:view',

  // ==================== 财务管理 ====================
  // 财务记录
  FINANCIAL_RECORD_VIEW: 'financial_record:view',
  FINANCIAL_RECORD_ADD: 'financial_record:add',
  FINANCIAL_RECORD_EDIT: 'financial_record:edit',
  FINANCIAL_RECORD_DELETE: 'financial_record:delete',
  
  // 财务报表
  FINANCIAL_REPORT_VIEW: 'financial_report:view',

  // ==================== 系统管理 ====================
  // 用户管理
  USER_VIEW: 'user:view',
  USER_ADD: 'user:add',
  USER_EDIT: 'user:edit',
  USER_DELETE: 'user:delete',
  USER_RESET_PWD: 'user:reset_pwd',
  
  // 角色管理
  ROLE_VIEW: 'role:view',
  ROLE_ADD: 'role:add',
  ROLE_EDIT: 'role:edit',
  ROLE_DELETE: 'role:delete',
  
  // 系统配置
  CONFIG_VIEW: 'config:view',
  CONFIG_EDIT: 'config:edit',
  
  // 系统信息
  SYSTEM_INFO_VIEW: 'system:info:view',
  SYSTEM_LOGS_VIEW: 'system:logs:view'
}

// ==================================================
// 权限分组（用于权限树展示）
// ==================================================

export const PERMISSION_GROUPS = {
  // ==================== 仪表盘 ====================
  dashboard: {
    name: '仪表盘',
    permissions: [
      { 
        key: PERM.DASHBOARD_VIEW, 
        name: '查看仪表盘', 
        description: '查看系统仪表盘和统计数据' 
      }
    ]
  },
  
  // ==================== 商品管理 ====================
  product: {
    name: '商品管理',
    permissions: [
      { key: PERM.PRODUCT_VIEW, name: '查看商品', description: '查看商品列表和详情' },
      { key: PERM.PRODUCT_ADD, name: '新增商品', description: '创建新商品' },
      { key: PERM.PRODUCT_EDIT, name: '编辑商品', description: '编辑商品信息' },
      { key: PERM.PRODUCT_DELETE, name: '删除商品', description: '删除商品记录' },
      { key: PERM.SKU_VIEW, name: '查看SKU', description: '查看商品SKU列表' },
      { key: PERM.SKU_ADD, name: '新增SKU', description: '为商品添加新SKU' },
      { key: PERM.SKU_EDIT, name: '编辑SKU', description: '编辑SKU信息' },
      { key: PERM.SKU_DELETE, name: '删除SKU', description: '删除SKU记录' }
    ]
  },
  
  category: {
    name: '分类管理',
    permissions: [
      { key: PERM.CATEGORY_VIEW, name: '查看分类', description: '查看商品分类列表' },
      { key: PERM.CATEGORY_ADD, name: '新增分类', description: '创建新分类' },
      { key: PERM.CATEGORY_EDIT, name: '编辑分类', description: '编辑分类信息' },
      { key: PERM.CATEGORY_DELETE, name: '删除分类', description: '删除分类记录' }
    ]
  },
  
  brand: {
    name: '品牌管理',
    permissions: [
      { key: PERM.BRAND_VIEW, name: '查看品牌', description: '查看品牌列表' },
      { key: PERM.BRAND_ADD, name: '新增品牌', description: '创建新品牌' },
      { key: PERM.BRAND_EDIT, name: '编辑品牌', description: '编辑品牌信息' },
      { key: PERM.BRAND_DELETE, name: '删除品牌', description: '删除品牌记录' }
    ]
  },
  
  // ==================== 客户管理 ====================
  customer: {
    name: '客户管理',
    permissions: [
      { key: PERM.CUSTOMER_VIEW, name: '查看客户', description: '查看客户列表和详情' },
      { key: PERM.CUSTOMER_ADD, name: '新增客户', description: '创建新客户' },
      { key: PERM.CUSTOMER_EDIT, name: '编辑客户', description: '编辑客户信息' },
      { key: PERM.CUSTOMER_DELETE, name: '删除客户', description: '删除客户记录' }
    ]
  },
  
  // ==================== 供应商管理 ====================
  supplier: {
    name: '供应商管理',
    permissions: [
      { key: PERM.SUPPLIER_VIEW, name: '查看供应商', description: '查看供应商列表和详情' },
      { key: PERM.SUPPLIER_ADD, name: '新增供应商', description: '创建新供应商' },
      { key: PERM.SUPPLIER_EDIT, name: '编辑供应商', description: '编辑供应商信息' },
      { key: PERM.SUPPLIER_DELETE, name: '删除供应商', description: '删除供应商记录' }
    ]
  },
  
  // ==================== 仓库管理 ====================
  warehouse: {
    name: '仓库管理',
    permissions: [
      { key: PERM.WAREHOUSE_VIEW, name: '查看仓库', description: '查看仓库列表和详情' },
      { key: PERM.WAREHOUSE_ADD, name: '新增仓库', description: '创建新仓库' },
      { key: PERM.WAREHOUSE_EDIT, name: '编辑仓库', description: '编辑仓库信息' },
      { key: PERM.WAREHOUSE_DELETE, name: '删除仓库', description: '删除仓库记录' }
    ]
  },
  
  // ==================== 采购管理 ====================
  purchase: {
    name: '采购管理',
    permissions: [
      { key: PERM.PURCHASE_ORDER_VIEW, name: '查看采购订单', description: '查看采购订单列表和详情' },
      { key: PERM.PURCHASE_ORDER_ADD, name: '创建采购订单', description: '创建采购订单' },
      { key: PERM.PURCHASE_ORDER_EDIT, name: '编辑采购订单', description: '编辑采购订单信息' },
      { key: PERM.PURCHASE_ORDER_AUDIT, name: '审核采购订单', description: '审核或驳回采购订单' },
      { key: PERM.PURCHASE_ORDER_DELETE, name: '删除采购订单', description: '删除采购订单' },
      
      { key: PERM.PURCHASE_STOCK_VIEW, name: '查看采购入库', description: '查看采购入库记录' },
      { key: PERM.PURCHASE_STOCK_ADD, name: '创建采购入库', description: '创建采购入库单' },
      { key: PERM.PURCHASE_STOCK_AUDIT, name: '审核采购入库', description: '审核或驳回采购入库' },
      { key: PERM.PURCHASE_STOCK_CANCEL, name: '取消采购入库', description: '取消采购入库单' }
    ]
  },
  
  // ==================== 销售管理 ====================
  sale: {
    name: '销售管理',
    permissions: [
      { key: PERM.SALE_ORDER_VIEW, name: '查看销售订单', description: '查看销售订单列表和详情' },
      { key: PERM.SALE_ORDER_ADD, name: '创建销售订单', description: '创建销售订单' },
      { key: PERM.SALE_ORDER_EDIT, name: '编辑销售订单', description: '编辑销售订单信息' },
      { key: PERM.SALE_ORDER_AUDIT, name: '审核销售订单', description: '审核或驳回销售订单' },
      { key: PERM.SALE_ORDER_DELETE, name: '删除销售订单', description: '删除销售订单' },
      
      { key: PERM.SALE_STOCK_VIEW, name: '查看销售出库', description: '查看销售出库记录' },
      { key: PERM.SALE_STOCK_ADD, name: '创建销售出库', description: '创建销售出库单' },
      { key: PERM.SALE_STOCK_AUDIT, name: '审核销售出库', description: '审核或驳回销售出库' },
      { key: PERM.SALE_STOCK_CANCEL, name: '取消销售出库', description: '取消销售出库单' }
    ]
  },
  
  // ==================== 退货管理 ====================
  return: {
    name: '退货管理',
    permissions: [
      { key: PERM.RETURN_ORDER_VIEW, name: '查看退货单', description: '查看退货单列表和详情' },
      { key: PERM.RETURN_ORDER_ADD, name: '创建退货单', description: '创建销售或采购退货单' },
      { key: PERM.RETURN_ORDER_EDIT, name: '编辑退货单', description: '编辑退货单信息' },
      { key: PERM.RETURN_ORDER_AUDIT, name: '审核退货单', description: '审核或驳回退货单' },
      { key: PERM.RETURN_ORDER_DELETE, name: '删除退货单', description: '删除退货单' },
      
      { key: PERM.RETURN_STOCK_VIEW, name: '查看退货出入库', description: '查看退货出入库记录' },
      { key: PERM.RETURN_STOCK_CREATE, name: '创建退货出入库', description: '创建退货出入库单' },
      { key: PERM.RETURN_STOCK_AUDIT, name: '审核退货出入库', description: '审核或驳回退货出入库' },
      { key: PERM.RETURN_STOCK_CANCEL, name: '取消退货出入库', description: '取消退货出入库单' }
    ]
  },
  
  // ==================== 库存管理 ====================
  stock: {
    name: '库存管理',
    permissions: [
      { key: PERM.STOCK_VIEW, name: '查看库存', description: '查看库存列表和详情' },
      
      { key: PERM.STOCK_TAKE_VIEW, name: '查看盘点单', description: '查看库存盘点记录' },
      { key: PERM.STOCK_TAKE_ADD, name: '创建盘点单', description: '创建库存盘点单' },
      { key: PERM.STOCK_TAKE_AUDIT, name: '审核盘点单', description: '审核或驳回库存盘点' },
      { key: PERM.STOCK_TAKE_CANCEL, name: '取消盘点单', description: '取消库存盘点' },
      
      { key: PERM.STOCK_TRANSFER_VIEW, name: '查看调拨单', description: '查看库存调拨记录' },
      { key: PERM.STOCK_TRANSFER_ADD, name: '创建调拨单', description: '创建库存调拨单' },
      { key: PERM.STOCK_TRANSFER_AUDIT, name: '审核调拨单', description: '审核或驳回库存调拨' },
      { key: PERM.STOCK_TRANSFER_CANCEL, name: '取消调拨单', description: '取消库存调拨' },
      
      { key: PERM.STOCK_WARNING_VIEW, name: '查看库存预警', description: '查看库存预警信息' }
    ]
  },
  
  // ==================== 账款管理 ====================
  account: {
    name: '账款管理',
    permissions: [
      { key: PERM.ACCOUNT_RECEIVABLE_VIEW, name: '查看应收款', description: '查看客户应收账款' },
      { key: PERM.ACCOUNT_RECEIVABLE_SETTLE, name: '核销应收款', description: '核销客户应收账款' },
      
      { key: PERM.ACCOUNT_PAYABLE_VIEW, name: '查看应付款', description: '查看供应商应付账款' },
      { key: PERM.ACCOUNT_PAYABLE_SETTLE, name: '核销应付款', description: '核销供应商应付账款' },
      
      { key: PERM.SETTLEMENT_VIEW, name: '查看核销记录', description: '查看账款核销历史记录' }
    ]
  },
  
  // ==================== 财务管理 ====================
  financial: {
    name: '财务管理',
    permissions: [
      { key: PERM.FINANCIAL_RECORD_VIEW, name: '查看财务记录', description: '查看财务收支记录' },
      { key: PERM.FINANCIAL_RECORD_ADD, name: '新增财务记录', description: '创建新的财务收支记录' },
      { key: PERM.FINANCIAL_RECORD_EDIT, name: '编辑财务记录', description: '编辑财务收支记录' },
      { key: PERM.FINANCIAL_RECORD_DELETE, name: '删除财务记录', description: '删除财务收支记录' },
      
      { key: PERM.FINANCIAL_REPORT_VIEW, name: '查看财务报表', description: '查看财务报表和分析' }
    ]
  },
  
  // ==================== 系统管理 ====================
  system: {
    name: '系统管理',
    permissions: [
      { key: PERM.USER_VIEW, name: '查看用户', description: '查看用户列表和详情' },
      { key: PERM.USER_ADD, name: '新增用户', description: '创建新用户' },
      { key: PERM.USER_EDIT, name: '编辑用户', description: '编辑用户信息' },
      { key: PERM.USER_DELETE, name: '删除用户', description: '删除用户记录' },
      { key: PERM.USER_RESET_PWD, name: '重置密码', description: '重置用户登录密码' },
      
      { key: PERM.ROLE_VIEW, name: '查看角色', description: '查看角色列表和详情' },
      { key: PERM.ROLE_ADD, name: '新增角色', description: '创建新角色' },
      { key: PERM.ROLE_EDIT, name: '编辑角色', description: '编辑角色信息' },
      { key: PERM.ROLE_DELETE, name: '删除角色', description: '删除角色记录' },
      
      { key: PERM.CONFIG_VIEW, name: '查看配置', description: '查看系统配置' },
      { key: PERM.CONFIG_EDIT, name: '编辑配置', description: '编辑系统配置' },
      
      { key: PERM.SYSTEM_INFO_VIEW, name: '查看系统信息', description: '查看系统状态和环境信息' },
      { key: PERM.SYSTEM_LOGS_VIEW, name: '查看系统日志', description: '查看系统操作日志' }
    ]
  }
}

// ==================================================
// 工具函数
// ==================================================

/**
 * 获取所有权限键的数组
 */
export const ALL_PERMISSIONS = Object.values(PERM)

/**
 * 验证权限键是否有效
 * @param {string} perm - 权限键
 * @returns {boolean}
 */
export const isValidPermission = (perm) => {
  return ALL_PERMISSIONS.includes(perm)
}

/**
 * 过滤无效权限
 * @param {string[]} permissions - 权限数组
 * @returns {string[]}
 */
export const filterValidPermissions = (permissions) => {
  if (!Array.isArray(permissions)) return []
  return permissions.filter(perm => isValidPermission(perm))
}

/**
 * 获取模块下的所有权限
 * @param {string} moduleKey - 模块键，如 'product'
 * @returns {string[]}
 */
export const getModulePermissions = (moduleKey) => {
  const group = PERMISSION_GROUPS[moduleKey]
  if (!group) return []
  return group.permissions.map(p => p.key)
}

/**
 * 获取权限树的格式化数据（用于角色管理页面）
 * @returns {Array}
 */
export const getPermissionTree = () => {
  return Object.entries(PERMISSION_GROUPS).map(([key, group]) => ({
    key,
    title: group.name,
    permissions: group.permissions.map(perm => ({
      key: perm.key,
      name: perm.name,
      description: perm.description,
      checked: false
    })),
    expanded: true,
    checked: false,
    indeterminate: false
  }))
}

/**
 * 根据权限键获取权限信息
 * @param {string} permKey - 权限键
 * @returns {Object|null}
 */
export const getPermissionInfo = (permKey) => {
  for (const group of Object.values(PERMISSION_GROUPS)) {
    const permission = group.permissions.find(p => p.key === permKey)
    if (permission) {
      return permission
    }
  }
  return null
}

// ==================================================
// 默认导出
// ==================================================

export default {
  PERM,
  PERMISSION_GROUPS,
  ALL_PERMISSIONS,
  isValidPermission,
  filterValidPermissions,
  getModulePermissions,
  getPermissionTree,
  getPermissionInfo
}
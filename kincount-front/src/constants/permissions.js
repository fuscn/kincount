// src/constants/permissions.js
// 模块维度统一前缀，防止重复
export const PERM = {
  // 仪表盘
  DASHBOARD: 'dashboard:view',

  // 基础资料
  PRODUCT_VIEW: 'product:view',
  PRODUCT_ADD: 'product:add',
  PRODUCT_EDIT: 'product:edit',
  PRODUCT_DELETE: 'product:delete',

  CATEGORY_VIEW: 'category:view',
  CATEGORY_ADD: 'category:add',
  CATEGORY_EDIT: 'category:edit',
  CATEGORY_DELETE: 'category:delete',

  BRAND_VIEW: 'brand:view',
  BRAND_ADD: 'brand:add',
  BRAND_EDIT: 'brand:edit',
  BRAND_DELETE: 'brand:delete',

  // 客户
  CUSTOMER_VIEW: 'customer:view',
  CUSTOMER_ADD: 'customer:add',
  CUSTOMER_EDIT: 'customer:edit',
  CUSTOMER_DELETE: 'customer:delete',

  // 供应商
  SUPPLIER_VIEW: 'supplier:view',
  SUPPLIER_ADD: 'supplier:add',
  SUPPLIER_EDIT: 'supplier:edit',
  SUPPLIER_DELETE: 'supplier:delete',

  // 仓库
  WAREHOUSE_VIEW: 'warehouse:view',
  WAREHOUSE_ADD: 'warehouse:add',
  WAREHOUSE_EDIT: 'warehouse:edit',
  WAREHOUSE_DELETE: 'warehouse:delete',

  // 采购
  PURCHASE_VIEW: 'purchase:view',
  PURCHASE_ADD: 'purchase:add',
  PURCHASE_AUDIT: 'purchase:audit',
  PURCHASE_DELETE: 'purchase:delete',

  // 销售
  SALE_VIEW: 'sale:view',
  SALE_ADD: 'sale:add',
  SALE_EDIT: 'sale:edit',
  SALE_AUDIT: 'sale:audit',
  SALE_DELETE: 'sale:delete',

  // 库存
  STOCK_VIEW: 'stock:view',
  STOCK_TAKE: 'stock:take',
  STOCK_TRANSFER: 'stock:transfer',
  STOCK_WARNING: 'stock:warning',

  // 财务
  FINANCE_VIEW: 'finance:view',
  FINANCE_ADD: 'finance:add',
  FINANCE_REPORT: 'finance:report',

  // 系统
  USER_MANAGE: 'user:manage',
  ROLE_MANAGE: 'role:manage',
  CONFIG_MANAGE: 'config:manage'
}
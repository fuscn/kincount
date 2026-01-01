-- MySQL数据库表结构导出
-- 数据库: kincount
-- 主机: 127.0.0.1:3306
-- 导出时间: 2026-01-01 19:56:42
-- 共 31 个表
-- 生成工具: Python MySQL Table Exporter
============================================================

-- ==================================================
-- 表: account_records
-- ==================================================
CREATE TABLE `account_records` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `type` tinyint(4) NOT NULL COMMENT '类型：0-应收(客户销售,采购退货) 1-应付(供应商采购,销售退货)',
  `target_id` bigint(20) unsigned NOT NULL COMMENT '目标ID(客户ID/供应商ID)',
  `target_type` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'supplier' COMMENT '目标类型: customer, supplier',
  `related_id` bigint(20) unsigned NOT NULL COMMENT '关联业务ID',
  `related_type` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'sale_return=销售退货,purchase_return=采购退货,sale=销售,purchase=采购',
  `amount` decimal(10,2) NOT NULL COMMENT '金额',
  `paid_amount` decimal(10,2) DEFAULT '0.00' COMMENT '已收/已付金额',
  `balance_amount` decimal(10,2) NOT NULL COMMENT '余额',
  `status` tinyint(4) NOT NULL DEFAULT '0' COMMENT '状态：0-未结清 1-已结清',
  `due_date` date DEFAULT NULL COMMENT '到期日',
  `remark` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci COMMENT '备注',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_type_target` (`type`,`target_id`) USING BTREE,
  KEY `idx_related` (`related_type`,`related_id`) USING BTREE,
  KEY `idx_status` (`status`) USING BTREE,
  KEY `idx_account_records_status` (`status`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='账款记录表';


-- ==================================================
-- 表: account_settlements
-- ==================================================
CREATE TABLE `account_settlements` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `settlement_no` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '核销单号',
  `account_type` tinyint(4) NOT NULL COMMENT '账款类型：0-应收 1-应付',
  `account_id` bigint(20) unsigned NOT NULL COMMENT '账款ID',
  `financial_id` bigint(20) unsigned NOT NULL COMMENT '财务收支ID',
  `settlement_amount` decimal(10,2) NOT NULL COMMENT '核销金额',
  `settlement_date` date NOT NULL COMMENT '核销日期',
  `remark` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '备注',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_account` (`account_type`,`account_id`) USING BTREE,
  KEY `idx_financial` (`financial_id`) USING BTREE,
  KEY `idx_settlement_no` (`settlement_no`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='账款核销表';


-- ==================================================
-- 表: brands
-- ==================================================
CREATE TABLE `brands` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '品牌名称',
  `sort` int(11) NOT NULL DEFAULT '0' COMMENT '排序号，越小越靠前',
  `code` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '一般为英文名称',
  `logo` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '品牌Logo',
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci COMMENT '品牌描述',
  `status` tinyint(4) DEFAULT '1' COMMENT '状态：0-禁用 1-启用',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=17 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='品牌表';


-- ==================================================
-- 表: categorys
-- ==================================================
CREATE TABLE `categorys` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '分类名称',
  `parent_id` bigint(20) unsigned DEFAULT '0' COMMENT '父级ID',
  `sort` int(11) DEFAULT '0' COMMENT '排序',
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci COMMENT '分类描述',
  `status` tinyint(4) DEFAULT '1' COMMENT '状态：0-禁用 1-启用',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_parent` (`parent_id`) USING BTREE,
  KEY `idx_sort` (`sort`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=30 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='商品分类表';


-- ==================================================
-- 表: customers
-- ==================================================
CREATE TABLE `customers` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '客户名称',
  `type` tinyint(4) DEFAULT '0' COMMENT '客户类型：0-个人 1-公司',
  `contact_person` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '联系人',
  `phone` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '联系电话',
  `email` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '邮箱',
  `address` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '地址',
  `level` tinyint(4) DEFAULT '0' COMMENT '客户等级：0-普通 1-银牌 2-金牌',
  `discount` decimal(3,2) DEFAULT '1.00' COMMENT '折扣率',
  `credit_amount` decimal(10,2) DEFAULT '0.00' COMMENT '信用额度',
  `arrears_amount` decimal(10,2) DEFAULT '0.00' COMMENT '欠款金额,客户欠我们的金额',
  `status` tinyint(4) DEFAULT '1' COMMENT '状态：0-禁用 1-启用',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `credit_days` int(11) DEFAULT '30' COMMENT '信用天数',
  `receivable_balance` decimal(10,2) DEFAULT '0.00' COMMENT '应收账款余额',
  PRIMARY KEY (`id`),
  KEY `idx_name` (`name`) USING BTREE,
  KEY `idx_phone` (`phone`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='客户表';


-- ==================================================
-- 表: financial_records
-- ==================================================
CREATE TABLE `financial_records` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `record_no` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '收支单号',
  `type` tinyint(4) NOT NULL COMMENT '类型：0-收入 1-支出',
  `category` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '收支类别',
  `amount` decimal(10,2) NOT NULL COMMENT '金额',
  `payment_method` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '支付方式',
  `remark` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci COMMENT '备注',
  `record_date` date NOT NULL COMMENT '收支日期',
  `created_by` bigint(20) unsigned NOT NULL COMMENT '创建人',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `account_id` bigint(20) unsigned DEFAULT NULL COMMENT '关联账款ID',
  `customer_id` bigint(20) unsigned DEFAULT NULL COMMENT '客户ID',
  `supplier_id` bigint(20) unsigned DEFAULT NULL COMMENT '供应商ID',
  `order_id` bigint(20) unsigned DEFAULT NULL COMMENT '关联订单ID',
  `order_type` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '订单类型',
  PRIMARY KEY (`id`),
  UNIQUE KEY `record_no` (`record_no`) USING BTREE,
  KEY `idx_type` (`type`) USING BTREE,
  KEY `idx_date` (`record_date`) USING BTREE,
  KEY `idx_category` (`category`) USING BTREE,
  KEY `idx_financial_records_date` (`record_date`) USING BTREE,
  KEY `idx_account_id` (`account_id`) USING BTREE,
  KEY `idx_customer` (`customer_id`) USING BTREE,
  KEY `idx_supplier` (`supplier_id`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='财务收支表';


-- ==================================================
-- 表: product_skus
-- ==================================================
CREATE TABLE `product_skus` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `product_id` int(11) NOT NULL,
  `sku_code` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
  `spec` json NOT NULL COMMENT '{"颜色":"红胡桃","厚度":"18mm"}',
  `barcode` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `cost_price` decimal(10,2) DEFAULT NULL,
  `sale_price` decimal(10,2) DEFAULT NULL,
  `unit` varchar(8) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT '张',
  `status` tinyint(1) DEFAULT '1' COMMENT '0-禁用1-启用',
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `sku_code` (`sku_code`) USING BTREE,
  UNIQUE KEY `idx_sku_code` (`sku_code`) USING BTREE,
  UNIQUE KEY `unique_sku_code` (`sku_code`) USING BTREE,
  UNIQUE KEY `unique_barcode` (`barcode`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=59 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;


-- ==================================================
-- 表: product_spec_definitions
-- ==================================================
CREATE TABLE `product_spec_definitions` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `product_id` int(11) NOT NULL,
  `spec_name` varchar(32) NOT NULL COMMENT '颜色/厚度/长度',
  `spec_values` json NOT NULL COMMENT '["红胡桃","白橡"]',
  `sort` int(11) DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `product_id` (`product_id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;


-- ==================================================
-- 表: products
-- ==================================================
CREATE TABLE `products` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `product_no` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '商品编号',
  `name` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '商品名称',
  `category_id` bigint(20) unsigned NOT NULL COMMENT '分类ID',
  `brand_id` bigint(20) unsigned DEFAULT NULL COMMENT '品牌ID',
  `spec` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '规格',
  `unit` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '单位',
  `cost_price` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT '成本价',
  `sale_price` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT '销售价',
  `wholesale_price` decimal(10,2) DEFAULT NULL COMMENT '批发价',
  `min_stock` int(11) DEFAULT '0' COMMENT '最低库存预警',
  `max_stock` int(11) DEFAULT '0' COMMENT '最高库存预警',
  `images` json DEFAULT NULL COMMENT '商品图片(JSON数组)',
  `description` text COLLATE utf8mb4_unicode_ci COMMENT '商品描述',
  `status` tinyint(4) DEFAULT '0' COMMENT '状态：0-下架 1-上架',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `product_no` (`product_no`),
  KEY `idx_category` (`category_id`),
  KEY `idx_brand` (`brand_id`),
  KEY `idx_product_no` (`product_no`),
  KEY `idx_status` (`status`),
  KEY `idx_products_category` (`category_id`),
  KEY `idx_products_brand` (`brand_id`)
) ENGINE=InnoDB AUTO_INCREMENT=26 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='商品表';


-- ==================================================
-- 表: purchase_order_items
-- ==================================================
CREATE TABLE `purchase_order_items` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `purchase_order_id` bigint(20) unsigned NOT NULL COMMENT '采购订单ID',
  `product_id` bigint(20) unsigned NOT NULL COMMENT '商品ID',
  `sku_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL COMMENT '采购数量',
  `received_quantity` int(11) NOT NULL DEFAULT '0' COMMENT '已入库数量',
  `price` decimal(10,2) NOT NULL COMMENT '采购单价',
  `total_amount` decimal(10,2) NOT NULL COMMENT '总金额',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `returned_quantity` int(11) NOT NULL DEFAULT '0' COMMENT '已退货数量',
  `returned_amount` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT '已退货金额',
  PRIMARY KEY (`id`),
  KEY `idx_order` (`purchase_order_id`),
  KEY `idx_product` (`product_id`),
  KEY `sku_id` (`sku_id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='采购订单明细表';


-- ==================================================
-- 表: purchase_orders
-- ==================================================
CREATE TABLE `purchase_orders` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `order_no` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '订单编号',
  `supplier_id` bigint(20) unsigned NOT NULL COMMENT '供应商ID',
  `warehouse_id` bigint(20) unsigned NOT NULL COMMENT '入库仓库ID',
  `total_amount` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT '订单总金额',
  `paid_amount` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT '已付金额',
  `status` tinyint(4) NOT NULL DEFAULT '0' COMMENT '状态：0-待审核 1-已审核 2-部分入库 3-已完成 4-已取消',
  `remark` text COLLATE utf8mb4_unicode_ci COMMENT '备注',
  `created_by` bigint(20) unsigned NOT NULL COMMENT '创建人',
  `audit_by` bigint(20) unsigned DEFAULT NULL COMMENT '审核人',
  `audit_time` datetime DEFAULT NULL COMMENT '审核时间',
  `expected_date` date DEFAULT NULL COMMENT '预计到货日期',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `order_no` (`order_no`),
  KEY `idx_supplier` (`supplier_id`),
  KEY `idx_warehouse` (`warehouse_id`),
  KEY `idx_status` (`status`),
  KEY `idx_order_no` (`order_no`),
  KEY `idx_purchase_orders_supplier` (`supplier_id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='采购订单表';


-- ==================================================
-- 表: purchase_stock_items
-- ==================================================
CREATE TABLE `purchase_stock_items` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `purchase_stock_id` bigint(20) unsigned NOT NULL COMMENT '采购入库ID',
  `product_id` bigint(20) unsigned NOT NULL COMMENT '商品ID',
  `sku_id` int(11) NOT NULL COMMENT 'sku-id',
  `quantity` int(11) NOT NULL COMMENT '入库数量',
  `price` decimal(10,2) NOT NULL COMMENT '入库单价',
  `total_amount` decimal(10,2) NOT NULL COMMENT '总金额',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `returned_quantity` int(11) NOT NULL DEFAULT '0' COMMENT '已退货数量',
  PRIMARY KEY (`id`),
  KEY `idx_stock` (`purchase_stock_id`),
  KEY `idx_product` (`product_id`),
  KEY `sku_id` (`sku_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='采购入库明细表';


-- ==================================================
-- 表: purchase_stocks
-- ==================================================
CREATE TABLE `purchase_stocks` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `stock_no` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '入库单号',
  `purchase_order_id` bigint(20) unsigned DEFAULT NULL COMMENT '采购订单ID',
  `supplier_id` bigint(20) unsigned NOT NULL COMMENT '供应商ID',
  `warehouse_id` bigint(20) unsigned NOT NULL COMMENT '入库仓库ID',
  `total_amount` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT '入库总金额',
  `status` tinyint(4) NOT NULL DEFAULT '0' COMMENT '状态：0-待审核 1-已审核 2-已取消',
  `remark` text COLLATE utf8mb4_unicode_ci COMMENT '备注',
  `created_by` bigint(20) unsigned NOT NULL COMMENT '创建人',
  `audit_by` bigint(20) unsigned DEFAULT NULL COMMENT '审核人',
  `audit_time` datetime DEFAULT NULL COMMENT '审核时间',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `stock_no` (`stock_no`),
  KEY `idx_supplier` (`supplier_id`),
  KEY `idx_warehouse` (`warehouse_id`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='采购入库表';


-- ==================================================
-- 表: return_order_items
-- ==================================================
CREATE TABLE `return_order_items` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `return_id` bigint(20) unsigned NOT NULL COMMENT '退货单ID',
  `source_order_item_id` bigint(20) unsigned DEFAULT NULL COMMENT '源订单明细ID',
  `source_stock_item_id` bigint(20) unsigned DEFAULT NULL COMMENT '源出入库明细ID',
  `product_id` bigint(20) unsigned NOT NULL COMMENT '商品ID',
  `sku_id` int(11) NOT NULL COMMENT 'SKU_ID',
  `return_quantity` int(11) NOT NULL COMMENT '退货数量',
  `processed_quantity` int(11) NOT NULL DEFAULT '0' COMMENT '已处理数量(入库/出库)',
  `price` decimal(10,2) NOT NULL COMMENT '退货单价',
  `total_amount` decimal(10,2) NOT NULL COMMENT '总金额',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_return` (`return_id`),
  KEY `idx_product` (`product_id`),
  KEY `idx_sku` (`sku_id`),
  KEY `idx_source_order_item` (`source_order_item_id`),
  KEY `idx_source_stock_item` (`source_stock_item_id`),
  KEY `idx_return_items_product` (`product_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='统一退货明细表';


-- ==================================================
-- 表: return_orders
-- ==================================================
CREATE TABLE `return_orders` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `return_no` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '退货单号',
  `type` tinyint(4) NOT NULL COMMENT '类型：0-销售退货 1-采购退货',
  `source_order_id` bigint(20) unsigned DEFAULT NULL COMMENT '源订单ID(sale_orders.id/purchase_orders.id)',
  `source_stock_id` bigint(20) unsigned DEFAULT NULL COMMENT '源出入库ID(sale_stocks.id/purchase_stocks.id)',
  `target_id` bigint(20) unsigned NOT NULL COMMENT '对方ID(客户ID/供应商ID)',
  `warehouse_id` bigint(20) unsigned NOT NULL COMMENT '仓库ID',
  `total_amount` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT '退货总金额',
  `refund_amount` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT '应退/应付金额',
  `refunded_amount` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT '已退/已收金额',
  `return_type` tinyint(4) DEFAULT '1' COMMENT '退货原因类型：0-质量问题 1-数量问题 2-客户/供应商取消 3-其他',
  `return_reason` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '退货原因',
  `status` tinyint(4) NOT NULL DEFAULT '0' COMMENT '状态：0-待审核 1-已审核 2-部分入库/出库 3-已入库/出库 4-已退款/收款 5-已完成 6-已取消',
  `stock_status` tinyint(4) DEFAULT '0' COMMENT '出入库状态：0-待处理 1-部分处理 2-已完成',
  `refund_status` tinyint(4) DEFAULT '0' COMMENT '款项状态：0-待处理 1-部分处理 2-已完成',
  `remark` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci COMMENT '备注',
  `created_by` bigint(20) unsigned NOT NULL COMMENT '创建人',
  `audit_by` bigint(20) unsigned DEFAULT NULL COMMENT '审核人',
  `audit_time` datetime DEFAULT NULL COMMENT '审核时间',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `return_no` (`return_no`),
  KEY `idx_type` (`type`),
  KEY `idx_target` (`target_id`),
  KEY `idx_warehouse` (`warehouse_id`),
  KEY `idx_source_order` (`source_order_id`),
  KEY `idx_status` (`status`),
  KEY `idx_return_type` (`return_type`),
  KEY `idx_returns_type_status` (`type`,`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='统一退货单表';


-- ==================================================
-- 表: return_stock_items
-- ==================================================
CREATE TABLE `return_stock_items` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `return_stock_id` bigint(20) unsigned NOT NULL COMMENT '退货出入库单ID',
  `return_item_id` bigint(20) unsigned NOT NULL COMMENT '退货明细ID',
  `product_id` bigint(20) unsigned NOT NULL COMMENT '商品ID',
  `sku_id` int(11) NOT NULL COMMENT 'SKU_ID',
  `quantity` int(11) NOT NULL COMMENT '出入库数量',
  `price` decimal(10,2) NOT NULL COMMENT '单价',
  `total_amount` decimal(10,2) NOT NULL COMMENT '总金额',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_stock` (`return_stock_id`),
  KEY `idx_return_item` (`return_item_id`),
  KEY `idx_product` (`product_id`),
  KEY `idx_sku` (`sku_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='统一退货出入库明细表';


-- ==================================================
-- 表: return_stocks
-- ==================================================
CREATE TABLE `return_stocks` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `stock_no` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '出入库单号',
  `return_id` bigint(20) unsigned NOT NULL COMMENT '退货单ID',
  `target_id` bigint(20) unsigned NOT NULL COMMENT '对方ID(客户/供应商)',
  `type` tinyint(4) unsigned NOT NULL COMMENT '0=销售退货,1=采购退货',
  `warehouse_id` bigint(20) unsigned NOT NULL COMMENT '仓库ID',
  `total_amount` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT '出入库总金额',
  `status` tinyint(4) NOT NULL DEFAULT '0' COMMENT '状态：0-待审核 1-已审核 2-已取消',
  `remark` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci COMMENT '备注',
  `created_by` bigint(20) unsigned NOT NULL COMMENT '创建人',
  `audit_by` bigint(20) unsigned DEFAULT NULL COMMENT '审核人',
  `audit_time` datetime DEFAULT NULL COMMENT '审核时间',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `stock_no` (`stock_no`),
  KEY `idx_return` (`return_id`),
  KEY `idx_target` (`target_id`),
  KEY `idx_warehouse` (`warehouse_id`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='统一退货出入库表';


-- ==================================================
-- 表: roles
-- ==================================================
CREATE TABLE `roles` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '角色名称',
  `description` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '角色描述',
  `permissions` json DEFAULT NULL COMMENT '权限配置(JSON格式)',
  `status` tinyint(4) DEFAULT '1' COMMENT '状态：0-禁用 1-启用',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='角色表';


-- ==================================================
-- 表: sale_order_items
-- ==================================================
CREATE TABLE `sale_order_items` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `sale_order_id` bigint(20) unsigned NOT NULL COMMENT '销售订单ID',
  `product_id` bigint(20) unsigned NOT NULL COMMENT '商品ID',
  `sku_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL COMMENT '销售数量',
  `delivered_quantity` int(11) NOT NULL DEFAULT '0' COMMENT '已出库数量',
  `price` decimal(10,2) NOT NULL COMMENT '销售单价',
  `total_amount` decimal(10,2) NOT NULL COMMENT '总金额',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` timestamp NULL DEFAULT NULL COMMENT '软删除',
  `returned_quantity` int(11) NOT NULL DEFAULT '0' COMMENT '已退货数量',
  `returned_amount` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT '已退货金额',
  PRIMARY KEY (`id`),
  KEY `idx_order` (`sale_order_id`),
  KEY `idx_product` (`product_id`),
  KEY `sku_id` (`sku_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='销售订单明细表';


-- ==================================================
-- 表: sale_orders
-- ==================================================
CREATE TABLE `sale_orders` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `order_no` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '订单编号',
  `customer_id` bigint(20) unsigned NOT NULL COMMENT '客户ID',
  `warehouse_id` bigint(20) unsigned NOT NULL COMMENT '出库仓库ID',
  `total_amount` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT '订单总金额',
  `discount_amount` decimal(10,2) DEFAULT '0.00' COMMENT '折扣金额',
  `final_amount` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT '实收金额',
  `paid_amount` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT '已收金额',
  `status` tinyint(4) NOT NULL DEFAULT '0' COMMENT '状态：0-待审核 1-已审核 2-部分出库 3-已完成 4-已取消',
  `remark` text COLLATE utf8mb4_unicode_ci COMMENT '备注',
  `created_by` bigint(20) unsigned NOT NULL COMMENT '创建人',
  `audit_by` bigint(20) unsigned DEFAULT NULL COMMENT '审核人',
  `audit_time` datetime DEFAULT NULL COMMENT '审核时间',
  `expected_date` date DEFAULT NULL COMMENT '预计交货日期',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `order_no` (`order_no`),
  KEY `idx_customer` (`customer_id`),
  KEY `idx_warehouse` (`warehouse_id`),
  KEY `idx_status` (`status`),
  KEY `idx_order_no` (`order_no`),
  KEY `idx_sale_orders_customer` (`customer_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='销售订单表';


-- ==================================================
-- 表: sale_stock_items
-- ==================================================
CREATE TABLE `sale_stock_items` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `sale_stock_id` bigint(20) unsigned NOT NULL COMMENT '销售出库ID',
  `product_id` bigint(20) unsigned NOT NULL COMMENT '商品ID',
  `sku_id` bigint(20) unsigned NOT NULL COMMENT 'SKU_ID',
  `quantity` int(11) NOT NULL COMMENT '出库数量',
  `price` decimal(10,2) NOT NULL COMMENT '出库单价',
  `total_amount` decimal(10,2) NOT NULL COMMENT '总金额',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `returned_quantity` int(11) NOT NULL DEFAULT '0' COMMENT '已退货数量',
  PRIMARY KEY (`id`),
  KEY `idx_stock` (`sale_stock_id`),
  KEY `idx_product` (`product_id`),
  KEY `sku_id` (`sku_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='销售出库明细表';


-- ==================================================
-- 表: sale_stocks
-- ==================================================
CREATE TABLE `sale_stocks` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `stock_no` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '出库单号',
  `sale_order_id` bigint(20) unsigned DEFAULT NULL COMMENT '销售订单ID',
  `customer_id` bigint(20) unsigned NOT NULL COMMENT '客户ID',
  `warehouse_id` bigint(20) unsigned NOT NULL COMMENT '出库仓库ID',
  `total_amount` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT '出库总金额',
  `status` tinyint(4) NOT NULL DEFAULT '0' COMMENT '状态：0-待审核 1-已审核 2-已取消',
  `remark` text COLLATE utf8mb4_unicode_ci COMMENT '备注',
  `created_by` bigint(20) unsigned NOT NULL COMMENT '创建人',
  `audit_by` bigint(20) unsigned DEFAULT NULL COMMENT '审核人',
  `audit_time` datetime DEFAULT NULL COMMENT '审核时间',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `stock_no` (`stock_no`),
  KEY `idx_customer` (`customer_id`),
  KEY `idx_warehouse` (`warehouse_id`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='销售出库表';


-- ==================================================
-- 表: stock_take_items
-- ==================================================
CREATE TABLE `stock_take_items` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `stock_take_id` bigint(20) unsigned NOT NULL COMMENT '盘点单ID',
  `product_id` bigint(20) unsigned NOT NULL COMMENT '商品ID',
  `sku_id` bigint(20) unsigned NOT NULL,
  `system_quantity` int(11) NOT NULL COMMENT '系统库存数量',
  `actual_quantity` int(11) NOT NULL COMMENT '实际盘点数量',
  `difference_quantity` int(11) NOT NULL COMMENT '差异数量',
  `cost_price` decimal(10,2) NOT NULL COMMENT '成本单价',
  `difference_amount` decimal(10,2) NOT NULL COMMENT '差异金额',
  `reason` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '差异原因',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_take` (`stock_take_id`),
  KEY `idx_product` (`product_id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='库存盘点明细表';


-- ==================================================
-- 表: stock_takes
-- ==================================================
CREATE TABLE `stock_takes` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `take_no` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '盘点单号',
  `warehouse_id` bigint(20) unsigned NOT NULL COMMENT '仓库ID',
  `total_difference` decimal(10,2) DEFAULT '0.00' COMMENT '总差异金额',
  `status` tinyint(4) NOT NULL DEFAULT '0' COMMENT '状态：0-待盘点,1-盘点中,2-已完成,3-已取消',
  `remark` text COLLATE utf8mb4_unicode_ci COMMENT '备注',
  `created_by` bigint(20) unsigned NOT NULL COMMENT '创建人',
  `audit_by` bigint(20) unsigned DEFAULT NULL COMMENT '审核人',
  `audit_time` datetime DEFAULT NULL COMMENT '审核时间',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `take_no` (`take_no`),
  KEY `idx_warehouse` (`warehouse_id`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='库存盘点表';


-- ==================================================
-- 表: stock_transfer_items
-- ==================================================
CREATE TABLE `stock_transfer_items` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `stock_transfer_id` bigint(20) unsigned NOT NULL COMMENT '调拨单ID',
  `product_id` bigint(20) unsigned NOT NULL COMMENT '商品ID',
  `sku_id` bigint(20) unsigned NOT NULL,
  `quantity` int(11) NOT NULL COMMENT '调拨数量',
  `cost_price` decimal(10,2) NOT NULL COMMENT '成本单价',
  `total_amount` decimal(10,2) NOT NULL COMMENT '总金额',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_transfer` (`stock_transfer_id`),
  KEY `idx_product` (`product_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='库存调拨明细表';


-- ==================================================
-- 表: stock_transfers
-- ==================================================
CREATE TABLE `stock_transfers` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `transfer_no` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '调拨单号',
  `from_warehouse_id` bigint(20) unsigned NOT NULL COMMENT '调出仓库ID',
  `to_warehouse_id` bigint(20) unsigned NOT NULL COMMENT '调入仓库ID',
  `total_amount` decimal(10,2) DEFAULT '0.00' COMMENT '调拨总金额',
  `status` tinyint(4) NOT NULL DEFAULT '0' COMMENT '状态：0-待调拨,1-调拨中,2-已完成,3-已取消',
  `remark` text COLLATE utf8mb4_unicode_ci COMMENT '备注',
  `created_by` bigint(20) unsigned NOT NULL COMMENT '创建人',
  `audit_by` bigint(20) unsigned DEFAULT NULL COMMENT '审核人',
  `audit_time` datetime DEFAULT NULL COMMENT '审核时间',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `transfer_no` (`transfer_no`),
  KEY `idx_from_warehouse` (`from_warehouse_id`),
  KEY `idx_to_warehouse` (`to_warehouse_id`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='库存调拨表';


-- ==================================================
-- 表: stocks
-- ==================================================
CREATE TABLE `stocks` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `sku_id` int(11) NOT NULL,
  `warehouse_id` bigint(20) unsigned NOT NULL COMMENT '仓库ID',
  `quantity` int(11) NOT NULL DEFAULT '0' COMMENT '库存数量',
  `cost_price` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT '成本价',
  `total_amount` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT '总金额',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_product_warehouse` (`sku_id`,`warehouse_id`),
  KEY `idx_warehouse` (`warehouse_id`),
  KEY `idx_stocks_product` (`sku_id`),
  KEY `idx_stocks_warehouse` (`warehouse_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='库存表';


-- ==================================================
-- 表: suppliers
-- ==================================================
CREATE TABLE `suppliers` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '供应商名称',
  `contact_person` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '联系人',
  `phone` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '联系电话',
  `email` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '邮箱',
  `address` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '地址',
  `bank_account` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '银行账户',
  `tax_number` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '税号',
  `arrears_amount` decimal(10,2) DEFAULT '0.00' COMMENT '欠款金额,我们欠供应商的金额',
  `status` tinyint(4) DEFAULT '1' COMMENT '状态：0-禁用 1-启用',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `payable_balance` decimal(10,2) DEFAULT '0.00' COMMENT '应付账款余额',
  PRIMARY KEY (`id`),
  KEY `idx_name` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='供应商表';


-- ==================================================
-- 表: system_configs
-- ==================================================
CREATE TABLE `system_configs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `config_key` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '配置键',
  `config_value` text COLLATE utf8mb4_unicode_ci COMMENT '配置值',
  `config_name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '配置名称',
  `config_group` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT 'default' COMMENT '配置分组',
  `remark` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '备注',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `config_key` (`config_key`),
  KEY `idx_key` (`config_key`),
  KEY `idx_group` (`config_group`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='系统配置表';


-- ==================================================
-- 表: users
-- ==================================================
CREATE TABLE `users` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `username` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '用户名',
  `password` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '密码',
  `real_name` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '真实姓名',
  `phone` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '手机号',
  `email` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '邮箱',
  `avatar` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '头像',
  `role_id` bigint(20) unsigned NOT NULL COMMENT '角色ID',
  `department` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '部门',
  `status` tinyint(4) DEFAULT '1' COMMENT '状态：0-禁用 1-启用',
  `last_login_time` datetime DEFAULT NULL COMMENT '最后登录时间',
  `last_login_ip` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '最后登录IP',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` timestamp NULL DEFAULT NULL COMMENT '软删除时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  KEY `idx_username` (`username`),
  KEY `idx_role` (`role_id`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='用户表';


-- ==================================================
-- 表: warehouses
-- ==================================================
CREATE TABLE `warehouses` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '仓库名称',
  `code` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '仓库编码',
  `address` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '仓库地址',
  `manager` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '负责人',
  `phone` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '联系电话',
  `capacity` decimal(10,2) DEFAULT NULL COMMENT '仓库容量',
  `status` tinyint(4) DEFAULT '1' COMMENT '状态：0-禁用 1-启用',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='仓库表';



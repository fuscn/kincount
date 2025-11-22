-- 创建数据库
CREATE DATABASE IF NOT EXISTS kincount CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE kincount;

-- 1. 用户表（员工/操作员）
CREATE TABLE users (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE COMMENT '用户名',
    password VARCHAR(255) NOT NULL COMMENT '密码',
    real_name VARCHAR(50) NOT NULL COMMENT '真实姓名',
    phone VARCHAR(20) COMMENT '手机号',
    email VARCHAR(100) COMMENT '邮箱',
    avatar VARCHAR(255) COMMENT '头像',
    role_id BIGINT UNSIGNED NOT NULL COMMENT '角色ID',
    department VARCHAR(50) COMMENT '部门',
    status TINYINT DEFAULT 1 COMMENT '状态：0-禁用 1-启用',
    last_login_time DATETIME COMMENT '最后登录时间',
    last_login_ip VARCHAR(45) COMMENT '最后登录IP',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL COMMENT '软删除时间',
    INDEX idx_username (username),
    INDEX idx_role (role_id),
    INDEX idx_status (status)
) COMMENT = '用户表';

-- 2. 角色表
CREATE TABLE roles (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL UNIQUE COMMENT '角色名称',
    description VARCHAR(255) COMMENT '角色描述',
    permissions JSON COMMENT '权限配置(JSON格式)',
    status TINYINT DEFAULT 1 COMMENT '状态：0-禁用 1-启用',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL
) COMMENT = '角色表';

-- 3. 商品分类表
CREATE TABLE categories (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL COMMENT '分类名称',
    parent_id BIGINT UNSIGNED DEFAULT 0 COMMENT '父级ID',
    sort INT DEFAULT 0 COMMENT '排序',
    description TEXT COMMENT '分类描述',
    status TINYINT DEFAULT 1 COMMENT '状态：0-禁用 1-启用',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL,
    INDEX idx_parent (parent_id),
    INDEX idx_sort (sort)
) COMMENT = '商品分类表';

-- 4. 商品品牌表
CREATE TABLE brands (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL COMMENT '品牌名称',
    logo VARCHAR(255) COMMENT '品牌Logo',
    description TEXT COMMENT '品牌描述',
    status TINYINT DEFAULT 1 COMMENT '状态：0-禁用 1-启用',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL
) COMMENT = '品牌表';

-- 5. 商品表
CREATE TABLE products (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    product_no VARCHAR(50) NOT NULL UNIQUE COMMENT '商品编号',
    name VARCHAR(200) NOT NULL COMMENT '商品名称',
    category_id BIGINT UNSIGNED NOT NULL COMMENT '分类ID',
    brand_id BIGINT UNSIGNED COMMENT '品牌ID',
    spec VARCHAR(100) COMMENT '规格',
    unit VARCHAR(20) NOT NULL COMMENT '单位',
    cost_price DECIMAL(10,2) NOT NULL DEFAULT 0 COMMENT '成本价',
    sale_price DECIMAL(10,2) NOT NULL DEFAULT 0 COMMENT '销售价',
    wholesale_price DECIMAL(10,2) COMMENT '批发价',
    min_stock INT DEFAULT 0 COMMENT '最低库存预警',
    max_stock INT DEFAULT 0 COMMENT '最高库存预警',
    images JSON COMMENT '商品图片(JSON数组)',
    description TEXT COMMENT '商品描述',
    status TINYINT DEFAULT 1 COMMENT '状态：0-下架 1-上架',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL,
    INDEX idx_category (category_id),
    INDEX idx_brand (brand_id),
    INDEX idx_product_no (product_no),
    INDEX idx_status (status)
) COMMENT = '商品表';

-- 6. 仓库表
CREATE TABLE warehouses (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL COMMENT '仓库名称',
    code VARCHAR(50) NOT NULL UNIQUE COMMENT '仓库编码',
    address VARCHAR(255) COMMENT '仓库地址',
    manager VARCHAR(50) COMMENT '负责人',
    phone VARCHAR(20) COMMENT '联系电话',
    capacity DECIMAL(10,2) COMMENT '仓库容量',
    status TINYINT DEFAULT 1 COMMENT '状态：0-禁用 1-启用',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL
) COMMENT = '仓库表';

-- 7. 客户表
CREATE TABLE customers (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(200) NOT NULL COMMENT '客户名称',
    type TINYINT DEFAULT 1 COMMENT '客户类型：1-个人 2-公司',
    contact_person VARCHAR(50) COMMENT '联系人',
    phone VARCHAR(20) COMMENT '联系电话',
    email VARCHAR(100) COMMENT '邮箱',
    address VARCHAR(255) COMMENT '地址',
    level TINYINT DEFAULT 1 COMMENT '客户等级：1-普通 2-银牌 3-金牌',
    discount DECIMAL(3,2) DEFAULT 1.00 COMMENT '折扣率',
    credit_amount DECIMAL(10,2) DEFAULT 0 COMMENT '信用额度',
    arrears_amount DECIMAL(10,2) DEFAULT 0 COMMENT '欠款金额',
    status TINYINT DEFAULT 1 COMMENT '状态：0-禁用 1-启用',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL,
    INDEX idx_name (name),
    INDEX idx_phone (phone)
) COMMENT = '客户表';

-- 8. 供应商表
CREATE TABLE suppliers (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(200) NOT NULL COMMENT '供应商名称',
    contact_person VARCHAR(50) COMMENT '联系人',
    phone VARCHAR(20) COMMENT '联系电话',
    email VARCHAR(100) COMMENT '邮箱',
    address VARCHAR(255) COMMENT '地址',
    bank_account VARCHAR(100) COMMENT '银行账户',
    tax_number VARCHAR(50) COMMENT '税号',
    arrears_amount DECIMAL(10,2) DEFAULT 0 COMMENT '欠款金额',
    status TINYINT DEFAULT 1 COMMENT '状态：0-禁用 1-启用',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL,
    INDEX idx_name (name)
) COMMENT = '供应商表';

-- 9. 库存表
CREATE TABLE stocks (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    product_id BIGINT UNSIGNED NOT NULL COMMENT '商品ID',
    warehouse_id BIGINT UNSIGNED NOT NULL COMMENT '仓库ID',
    quantity INT NOT NULL DEFAULT 0 COMMENT '库存数量',
    cost_price DECIMAL(10,2) NOT NULL DEFAULT 0 COMMENT '成本价',
    total_amount DECIMAL(10,2) NOT NULL DEFAULT 0 COMMENT '总金额',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL,
    UNIQUE KEY uk_product_warehouse (product_id, warehouse_id),
    INDEX idx_warehouse (warehouse_id)
) COMMENT = '库存表';

-- 10. 采购订单表
CREATE TABLE purchase_orders (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_no VARCHAR(50) NOT NULL UNIQUE COMMENT '订单编号',
    supplier_id BIGINT UNSIGNED NOT NULL COMMENT '供应商ID',
    warehouse_id BIGINT UNSIGNED NOT NULL COMMENT '入库仓库ID',
    total_amount DECIMAL(10,2) NOT NULL DEFAULT 0 COMMENT '订单总金额',
    paid_amount DECIMAL(10,2) NOT NULL DEFAULT 0 COMMENT '已付金额',
    status TINYINT NOT NULL DEFAULT 1 COMMENT '状态：1-待审核 2-已审核 3-部分入库 4-已完成 5-已取消',
    remark TEXT COMMENT '备注',
    created_by BIGINT UNSIGNED NOT NULL COMMENT '创建人',
    audit_by BIGINT UNSIGNED COMMENT '审核人',
    audit_time DATETIME COMMENT '审核时间',
    expected_date DATE COMMENT '预计到货日期',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL,
    INDEX idx_supplier (supplier_id),
    INDEX idx_warehouse (warehouse_id),
    INDEX idx_status (status),
    INDEX idx_order_no (order_no)
) COMMENT = '采购订单表';

-- 11. 采购订单明细表
CREATE TABLE purchase_order_items (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    purchase_order_id BIGINT UNSIGNED NOT NULL COMMENT '采购订单ID',
    product_id BIGINT UNSIGNED NOT NULL COMMENT '商品ID',
    quantity INT NOT NULL COMMENT '采购数量',
    received_quantity INT NOT NULL DEFAULT 0 COMMENT '已入库数量',
    price DECIMAL(10,2) NOT NULL COMMENT '采购单价',
    total_amount DECIMAL(10,2) NOT NULL COMMENT '总金额',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_order (purchase_order_id),
    INDEX idx_product (product_id)
) COMMENT = '采购订单明细表';

-- 12. 采购入库表
CREATE TABLE purchase_stocks (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    stock_no VARCHAR(50) NOT NULL UNIQUE COMMENT '入库单号',
    purchase_order_id BIGINT UNSIGNED COMMENT '采购订单ID',
    supplier_id BIGINT UNSIGNED NOT NULL COMMENT '供应商ID',
    warehouse_id BIGINT UNSIGNED NOT NULL COMMENT '入库仓库ID',
    total_amount DECIMAL(10,2) NOT NULL DEFAULT 0 COMMENT '入库总金额',
    status TINYINT NOT NULL DEFAULT 1 COMMENT '状态：1-待审核 2-已审核 3-已取消',
    remark TEXT COMMENT '备注',
    created_by BIGINT UNSIGNED NOT NULL COMMENT '创建人',
    audit_by BIGINT UNSIGNED COMMENT '审核人',
    audit_time DATETIME COMMENT '审核时间',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL,
    INDEX idx_supplier (supplier_id),
    INDEX idx_warehouse (warehouse_id),
    INDEX idx_status (status)
) COMMENT = '采购入库表';

-- 13. 采购入库明细表
CREATE TABLE purchase_stock_items (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    purchase_stock_id BIGINT UNSIGNED NOT NULL COMMENT '采购入库ID',
    product_id BIGINT UNSIGNED NOT NULL COMMENT '商品ID',
    quantity INT NOT NULL COMMENT '入库数量',
    price DECIMAL(10,2) NOT NULL COMMENT '入库单价',
    total_amount DECIMAL(10,2) NOT NULL COMMENT '总金额',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_stock (purchase_stock_id),
    INDEX idx_product (product_id)
) COMMENT = '采购入库明细表';

-- 14. 销售订单表
CREATE TABLE sale_orders (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_no VARCHAR(50) NOT NULL UNIQUE COMMENT '订单编号',
    customer_id BIGINT UNSIGNED NOT NULL COMMENT '客户ID',
    warehouse_id BIGINT UNSIGNED NOT NULL COMMENT '出库仓库ID',
    total_amount DECIMAL(10,2) NOT NULL DEFAULT 0 COMMENT '订单总金额',
    discount_amount DECIMAL(10,2) DEFAULT 0 COMMENT '折扣金额',
    final_amount DECIMAL(10,2) NOT NULL DEFAULT 0 COMMENT '实收金额',
    paid_amount DECIMAL(10,2) NOT NULL DEFAULT 0 COMMENT '已收金额',
    status TINYINT NOT NULL DEFAULT 1 COMMENT '状态：1-待审核 2-已审核 3-部分出库 4-已完成 5-已取消',
    remark TEXT COMMENT '备注',
    created_by BIGINT UNSIGNED NOT NULL COMMENT '创建人',
    audit_by BIGINT UNSIGNED COMMENT '审核人',
    audit_time DATETIME COMMENT '审核时间',
    expected_date DATE COMMENT '预计交货日期',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL,
    INDEX idx_customer (customer_id),
    INDEX idx_warehouse (warehouse_id),
    INDEX idx_status (status),
    INDEX idx_order_no (order_no)
) COMMENT = '销售订单表';

-- 15. 销售订单明细表
CREATE TABLE sale_order_items (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    sale_order_id BIGINT UNSIGNED NOT NULL COMMENT '销售订单ID',
    product_id BIGINT UNSIGNED NOT NULL COMMENT '商品ID',
    quantity INT NOT NULL COMMENT '销售数量',
    delivered_quantity INT NOT NULL DEFAULT 0 COMMENT '已出库数量',
    price DECIMAL(10,2) NOT NULL COMMENT '销售单价',
    total_amount DECIMAL(10,2) NOT NULL COMMENT '总金额',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_order (sale_order_id),
    INDEX idx_product (product_id)
) COMMENT = '销售订单明细表';

-- 16. 销售出库表
CREATE TABLE sale_stocks (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    stock_no VARCHAR(50) NOT NULL UNIQUE COMMENT '出库单号',
    sale_order_id BIGINT UNSIGNED COMMENT '销售订单ID',
    customer_id BIGINT UNSIGNED NOT NULL COMMENT '客户ID',
    warehouse_id BIGINT UNSIGNED NOT NULL COMMENT '出库仓库ID',
    total_amount DECIMAL(10,2) NOT NULL DEFAULT 0 COMMENT '出库总金额',
    status TINYINT NOT NULL DEFAULT 1 COMMENT '状态：1-待审核 2-已审核 3-已取消',
    remark TEXT COMMENT '备注',
    created_by BIGINT UNSIGNED NOT NULL COMMENT '创建人',
    audit_by BIGINT UNSIGNED COMMENT '审核人',
    audit_time DATETIME COMMENT '审核时间',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL,
    INDEX idx_customer (customer_id),
    INDEX idx_warehouse (warehouse_id),
    INDEX idx_status (status)
) COMMENT = '销售出库表';

-- 17. 销售出库明细表
CREATE TABLE sale_stock_items (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    sale_stock_id BIGINT UNSIGNED NOT NULL COMMENT '销售出库ID',
    product_id BIGINT UNSIGNED NOT NULL COMMENT '商品ID',
    quantity INT NOT NULL COMMENT '出库数量',
    price DECIMAL(10,2) NOT NULL COMMENT '出库单价',
    total_amount DECIMAL(10,2) NOT NULL COMMENT '总金额',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_stock (sale_stock_id),
    INDEX idx_product (product_id)
) COMMENT = '销售出库明细表';

-- 18. 库存盘点表
CREATE TABLE stock_takes (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    take_no VARCHAR(50) NOT NULL UNIQUE COMMENT '盘点单号',
    warehouse_id BIGINT UNSIGNED NOT NULL COMMENT '仓库ID',
    total_difference DECIMAL(10,2) DEFAULT 0 COMMENT '总差异金额',
    status TINYINT NOT NULL DEFAULT 1 COMMENT '状态：1-盘点中 2-已完成 3-已取消',
    remark TEXT COMMENT '备注',
    created_by BIGINT UNSIGNED NOT NULL COMMENT '创建人',
    audit_by BIGINT UNSIGNED COMMENT '审核人',
    audit_time DATETIME COMMENT '审核时间',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL,
    INDEX idx_warehouse (warehouse_id),
    INDEX idx_status (status)
) COMMENT = '库存盘点表';

-- 19. 库存盘点明细表
CREATE TABLE stock_take_items (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    stock_take_id BIGINT UNSIGNED NOT NULL COMMENT '盘点单ID',
    product_id BIGINT UNSIGNED NOT NULL COMMENT '商品ID',
    system_quantity INT NOT NULL COMMENT '系统库存数量',
    actual_quantity INT NOT NULL COMMENT '实际盘点数量',
    difference_quantity INT NOT NULL COMMENT '差异数量',
    cost_price DECIMAL(10,2) NOT NULL COMMENT '成本单价',
    difference_amount DECIMAL(10,2) NOT NULL COMMENT '差异金额',
    reason VARCHAR(255) COMMENT '差异原因',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_take (stock_take_id),
    INDEX idx_product (product_id)
) COMMENT = '库存盘点明细表';

-- 20. 库存调拨表
CREATE TABLE stock_transfers (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    transfer_no VARCHAR(50) NOT NULL UNIQUE COMMENT '调拨单号',
    from_warehouse_id BIGINT UNSIGNED NOT NULL COMMENT '调出仓库ID',
    to_warehouse_id BIGINT UNSIGNED NOT NULL COMMENT '调入仓库ID',
    total_amount DECIMAL(10,2) DEFAULT 0 COMMENT '调拨总金额',
    status TINYINT NOT NULL DEFAULT 1 COMMENT '状态：1-待调拨 2-调拨中 3-已完成 4-已取消',
    remark TEXT COMMENT '备注',
    created_by BIGINT UNSIGNED NOT NULL COMMENT '创建人',
    audit_by BIGINT UNSIGNED COMMENT '审核人',
    audit_time DATETIME COMMENT '审核时间',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL,
    INDEX idx_from_warehouse (from_warehouse_id),
    INDEX idx_to_warehouse (to_warehouse_id),
    INDEX idx_status (status)
) COMMENT = '库存调拨表';

-- 21. 库存调拨明细表
CREATE TABLE stock_transfer_items (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    stock_transfer_id BIGINT UNSIGNED NOT NULL COMMENT '调拨单ID',
    product_id BIGINT UNSIGNED NOT NULL COMMENT '商品ID',
    quantity INT NOT NULL COMMENT '调拨数量',
    cost_price DECIMAL(10,2) NOT NULL COMMENT '成本单价',
    total_amount DECIMAL(10,2) NOT NULL COMMENT '总金额',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_transfer (stock_transfer_id),
    INDEX idx_product (product_id)
) COMMENT = '库存调拨明细表';

-- 22. 财务收支表
CREATE TABLE financial_records (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    record_no VARCHAR(50) NOT NULL UNIQUE COMMENT '收支单号',
    type TINYINT NOT NULL COMMENT '类型：1-收入 2-支出',
    category VARCHAR(50) NOT NULL COMMENT '收支类别',
    amount DECIMAL(10,2) NOT NULL COMMENT '金额',
    payment_method VARCHAR(20) COMMENT '支付方式',
    remark TEXT COMMENT '备注',
    record_date DATE NOT NULL COMMENT '收支日期',
    created_by BIGINT UNSIGNED NOT NULL COMMENT '创建人',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL,
    INDEX idx_type (type),
    INDEX idx_date (record_date),
    INDEX idx_category (category)
) COMMENT = '财务收支表';

-- 23. 账款记录表
CREATE TABLE account_records (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    type TINYINT NOT NULL COMMENT '类型：1-应收(客户) 2-应付(供应商)',
    target_id BIGINT UNSIGNED NOT NULL COMMENT '目标ID(客户ID/供应商ID)',
    related_id BIGINT UNSIGNED NOT NULL COMMENT '关联业务ID',
    related_type VARCHAR(50) NOT NULL COMMENT '关联业务类型',
    amount DECIMAL(10,2) NOT NULL COMMENT '金额',
    paid_amount DECIMAL(10,2) DEFAULT 0 COMMENT '已收/已付金额',
    balance_amount DECIMAL(10,2) NOT NULL COMMENT '余额',
    status TINYINT NOT NULL DEFAULT 1 COMMENT '状态：1-未结清 2-已结清',
    due_date DATE COMMENT '到期日',
    remark TEXT COMMENT '备注',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL,
    INDEX idx_type_target (type, target_id),
    INDEX idx_related (related_type, related_id),
    INDEX idx_status (status)
) COMMENT = '账款记录表';

-- 24. 系统配置表
CREATE TABLE system_configs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    config_key VARCHAR(100) NOT NULL UNIQUE COMMENT '配置键',
    config_value TEXT COMMENT '配置值',
    config_name VARCHAR(100) NOT NULL COMMENT '配置名称',
    config_group VARCHAR(50) DEFAULT 'default' COMMENT '配置分组',
    remark VARCHAR(255) COMMENT '备注',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_key (config_key),
    INDEX idx_group (config_group)
) COMMENT = '系统配置表';

-- 插入测试数据

-- 角色数据
INSERT INTO roles (name, description, permissions, status) VALUES
('超级管理员', '拥有所有权限', '["*"]', 1),
('仓库管理员', '负责库存管理', '["stock:view","stock:manage","product:view"]', 1),
('销售员', '负责销售业务', '["sale:view","sale:manage","customer:view"]', 1);

-- 用户数据（密码都是123456，使用bcrypt加密）
INSERT INTO users (username, password, real_name, phone, role_id, department, status) VALUES
('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '系统管理员', '13800138000', 1, '管理部', 1),
('warehouse', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '仓库管理员', '13800138001', 2, '仓库部', 1),
('sales', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '销售员', '13800138002', 3, '销售部', 1);

-- 商品分类数据（装修行业相关）
INSERT INTO categories (name, parent_id, sort, description, status) VALUES
('基础建材', 0, 1, '基础建筑材料', 1),
('装饰材料', 0, 2, '装饰装修材料', 1),
('厨卫用品', 0, 3, '厨房卫生间用品', 1),
('电工电料', 0, 4, '电工电料产品', 1),
('水泥砂浆', 1, 1, '水泥砂浆类产品', 1),
('砖瓦石材', 1, 2, '砖瓦石材类产品', 1),
('墙面材料', 2, 1, '墙面装饰材料', 1),
('地面材料', 2, 2, '地面装饰材料', 1);

-- 品牌数据
INSERT INTO brands (name, description, status) VALUES
('东方雨虹', '防水材料知名品牌', 1),
('多乐士', '涂料油漆知名品牌', 1),
('马可波罗', '瓷砖知名品牌', 1),
('圣象', '地板知名品牌', 1),
('西门子', '电工电料知名品牌', 1);

-- 商品数据（装修行业相关商品）
INSERT INTO products (product_no, name, category_id, brand_id, spec, unit, cost_price, sale_price, min_stock, max_stock, status) VALUES
('P2024001', '普通硅酸盐水泥', 5, NULL, '42.5R 50kg/袋', '袋', 25.00, 30.00, 100, 1000, 1),
('P2024002', '中粗砂', 5, NULL, '建筑用砂', '吨', 80.00, 100.00, 10, 100, 1),
('P2024003', '多乐士墙面漆', 7, 2, '18L 白色', '桶', 180.00, 220.00, 20, 200, 1),
('P2024004', '马可波罗地砖', 8, 3, '800x800mm', '片', 45.00, 60.00, 50, 500, 1),
('P2024005', '圣象复合地板', 8, 4, '1210x165x12mm', '平方', 85.00, 120.00, 100, 1000, 1),
('P2024006', '西门子开关插座', 4, 5, '五孔 10A', '个', 8.50, 12.00, 100, 1000, 1);

-- 仓库数据
INSERT INTO warehouses (name, code, address, manager, phone, status) VALUES
('总仓库', 'WH001', '工业园区A区1号', '张经理', '13800138003', 1),
('城南分仓', 'WH002', '城南建材市场B区', '李主管', '13800138004', 1),
('城北分仓', 'WH003', '城北装饰城C区', '王主任', '13800138005', 1);

-- 客户数据
INSERT INTO customers (name, type, contact_person, phone, address, level, discount, credit_amount, status) VALUES
('张三装修公司', 2, '张三', '13900139001', '人民路100号', 3, 0.95, 50000.00, 1),
('李四装饰工程', 2, '李四', '13900139002', '解放路200号', 2, 0.98, 30000.00, 1),
('王五个人客户', 1, '王五', '13900139003', '中山路300号', 1, 1.00, 0.00, 1);

-- 供应商数据
INSERT INTO suppliers (name, contact_person, phone, address, bank_account, tax_number, status) VALUES
('东方雨虹建材公司', '刘经理', '13700137001', '高新区科技园', '62220210000001', '91370100MA0001', 1),
('多乐士涂料总代理', '陈总', '13700137002', '化工园区B座', '62220210000002', '91370100MA0002', 1),
('马可波罗瓷砖厂家', '赵厂长', '13700137003', '陶瓷工业园', '62220210000003', '91370100MA0003', 1);

-- 库存数据
INSERT INTO stocks (product_id, warehouse_id, quantity, cost_price, total_amount) VALUES
(1, 1, 500, 25.00, 12500.00),
(2, 1, 50, 80.00, 4000.00),
(3, 1, 100, 180.00, 18000.00),
(4, 2, 300, 45.00, 13500.00),
(5, 2, 800, 85.00, 68000.00),
(6, 3, 1000, 8.50, 8500.00);

-- 系统配置数据
INSERT INTO system_configs (config_key, config_value, config_name, config_group) VALUES
('company_name', '简库存管理系统', '公司名称', 'base'),
('company_address', '某省某市某区某路某号', '公司地址', 'base'),
('company_phone', '400-123-4567', '公司电话', 'base'),
('low_stock_warning', '1', '低库存预警开关', 'stock'),
('auto_audit_order', '0', '自动审核订单', 'order');

-- 创建视图用于统计报表
CREATE VIEW v_stock_summary AS
SELECT 
    p.id as product_id,
    p.product_no,
    p.name as product_name,
    c.name as category_name,
    b.name as brand_name,
    p.unit,
    p.cost_price,
    p.sale_price,
    COALESCE(SUM(s.quantity), 0) as total_quantity,
    COALESCE(SUM(s.total_amount), 0) as total_amount
FROM products p
LEFT JOIN categories c ON p.category_id = c.id
LEFT JOIN brands b ON p.brand_id = b.id
LEFT JOIN stocks s ON p.id = s.product_id
WHERE p.deleted_at IS NULL
GROUP BY p.id;

CREATE VIEW v_financial_summary AS
SELECT 
    'income' as type,
    SUM(amount) as total_amount,
    COUNT(*) as record_count
FROM financial_records 
WHERE type = 1 AND deleted_at IS NULL
UNION ALL
SELECT 
    'expense' as type,
    SUM(amount) as total_amount,
    COUNT(*) as record_count
FROM financial_records 
WHERE type = 2 AND deleted_at IS NULL;

-- 创建索引优化查询性能
CREATE INDEX idx_products_category ON products(category_id);
CREATE INDEX idx_products_brand ON products(brand_id);
CREATE INDEX idx_stocks_product ON stocks(product_id);
CREATE INDEX idx_stocks_warehouse ON stocks(warehouse_id);
CREATE INDEX idx_purchase_orders_supplier ON purchase_orders(supplier_id);
CREATE INDEX idx_sale_orders_customer ON sale_orders(customer_id);
CREATE INDEX idx_financial_records_date ON financial_records(record_date);
CREATE INDEX idx_account_records_status ON account_records(status);
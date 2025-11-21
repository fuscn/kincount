<?php

use think\facade\Route;

// JWT认证中间件
$jwtMiddleware = \app\kincount\middleware\JwtAuth::class;

// 测试路由 - 使用正确的控制器和方法名
Route::get('a/test', 'TestController/index');

// Route::get('stock/index', 'StockController/index');                         // 库存列表
// Route::get('stock/debug', 'StockController/debug');                         // 库存列表

Route::post('auth/refresh', 'AuthController/refresh'); // 新增刷新接口

// Route::get('stocks/warning', 'StockController/warning');


// 公开路由 - 不需要JWT认证
Route::group(function () {
    // 认证相关

    Route::post('auth/login', 'AuthController/login');
    Route::post('auth/refresh', 'AuthController/refresh');
    Route::post('auth/register', 'AuthController/register');

    // 系统状态检查
    Route::get('system/status', 'SystemController/status');
});

// 需要JWT认证的路由组
Route::group(function () use ($jwtMiddleware) {

    Route::pattern(['id' => '\d+', 'item_id' => '\d+']); // 整组生效
    // ==================== 仪表盘模块 ====================
    Route::group('dashboard', function () {
        Route::get('overview', 'DashboardController/overview');           // 数据概览
        Route::get('statistics', 'DashboardController/statistics');       // 统计信息
        Route::get('alerts', 'DashboardController/alerts');               // 预警信息
        Route::get('quick-actions', 'DashboardController/quickActions');  // 快捷操作
    });

    // ==================== 认证模块 ====================
    Route::group('auth', function () {
        // Route::post('refresh', 'AuthController/refresh'); // 新增刷新接口
        Route::post('logout', 'AuthController/logout');                   // 退出登录
        Route::get('userinfo', 'AuthController/userinfo');                // 用户信息
        Route::put('profile', 'AuthController/updateProfile');            // 更新资料
        Route::put('password', 'AuthController/changePassword');          // 修改密码
    });

    // ==================== 基础资料模块 ====================

    // 商品管理
    Route::group('products', function () {
        Route::get('/', 'ProductController/index');                       // 商品列表
        Route::post('/', 'ProductController/save');                       // 添加商品
        Route::get('/:id', 'ProductController/read');                     // 商品详情
        Route::put('/:id', 'ProductController/update');                   // 更新商品
        Route::delete('/:id', 'ProductController/delete');                // 删除商品
        Route::post('/batch', 'ProductController/batch');                 // 批量操作
        Route::get('/search/select', 'ProductController/selectSearch');   // 选择搜索
        Route::get('/export', 'ProductController/export');                // 导出商品
        Route::post('/import', 'ProductController/import');               // 导入商品
        Route::get('/:id/aggregate', 'ProductController/aggregate');
        Route::post('/aggregate', 'ProductController/saveAggregate');
        Route::put('/:id/aggregate', 'ProductController/updateAggregate');
    });
    # 新增 SKU 维度路由组（放在原采购、销售、库存分组之前即可）
    Route::group('skus', function () {
        Route::get('/', 'ProductController/skuIndex');          # SKU 分页列表
        Route::get('/:id', 'ProductController/skuRead');        # SKU 详情
        Route::post('/', 'ProductController/skuSave');          # 新增 SKU
        Route::put('/:id', 'ProductController/skuUpdate');      # 编辑 SKU
        Route::delete('/:id', 'ProductController/skuDelete');   # 删除 SKU
        Route::get('/select', 'ProductController/skuSelect');   # SKU 下拉/搜索
    });


    // 分类管理
    Route::group('categories', function () {
        Route::get('/', 'CategoryController/index');                      // 分类列表
        Route::post('/', 'CategoryController/save');                      // 添加分类
        Route::get('/:id', 'CategoryController/read');                    // 分类详情
        Route::put('/:id', 'CategoryController/update');                  // 更新分类
        Route::delete('/:id', 'CategoryController/delete');               // 删除分类
        Route::get('/tree', 'CategoryController/tree');                   // 树形结构
        Route::get('/options', 'CategoryController/options');             // 分类选项
    });

    // 品牌管理
    Route::group('brands', function () {
        Route::get('/', 'BrandController/index');                         // 品牌列表
        Route::post('/', 'BrandController/save');                         // 添加品牌
        Route::get('/options', 'BrandController/options');                // 品牌选项
        Route::get('/:id', 'BrandController/read');                       // 品牌详情
        Route::put('/:id', 'BrandController/update');                     // 更新品牌
        Route::delete('/:id', 'BrandController/delete');                  // 删除品牌

    });

    // 客户管理
    Route::group('customers', function () {
        Route::get('/', 'CustomerController/index');                      // 客户列表
        Route::post('/', 'CustomerController/save');                      // 添加客户
        Route::get('/:id', 'CustomerController/read');                    // 客户详情
        Route::put('/:id', 'CustomerController/update');                  // 更新客户
        Route::delete('/:id', 'CustomerController/delete');               // 删除客户
        Route::get('/search/select', 'CustomerController/selectSearch');  // 选择搜索
        Route::get('/:id/arrears', 'CustomerController/arrears');         // 客户欠款
        Route::post('/:id/arrears', 'CustomerController/updateArrears');  // 更新欠款
        Route::get('/statistics', 'CustomerController/statistics');       // 客户统计
    });

    // 供应商管理
    Route::group('suppliers', function () {
        Route::get('/', 'SupplierController/index');                      // 供应商列表
        Route::post('/', 'SupplierController/save');                      // 添加供应商
        Route::get('/:id', 'SupplierController/read');                    // 供应商详情
        Route::put('/:id', 'SupplierController/update');                  // 更新供应商
        Route::delete('/:id', 'SupplierController/delete');               // 删除供应商
        Route::get('/search/select', 'SupplierController/selectSearch');  // 选择搜索
        Route::get('/:id/arrears', 'SupplierController/arrears');         // 供应商欠款
        Route::post('/:id/arrears', 'SupplierController/updateArrears');  // 更新欠款
    });

    // 仓库管理
    Route::group('warehouses', function () {
        Route::get('/', 'WarehouseController/index');                     // 仓库列表
        Route::post('/', 'WarehouseController/save');                     // 添加仓库
        Route::get('/:id', 'WarehouseController/read');                   // 仓库详情
        Route::put('/:id', 'WarehouseController/update');                 // 更新仓库
        Route::delete('/:id', 'WarehouseController/delete');              // 删除仓库
        Route::get('/options', 'WarehouseController/options');            // 仓库选项
        Route::get('/:id/statistics', 'WarehouseController/statistics');  // 仓库统计
    });

    // ==================== 采购管理模块 ====================

    // 采购订单
    Route::group('purchase/orders', function () {
        Route::get('/', 'PurchaseOrderController/index');                 // 订单列表
        Route::post('/', 'PurchaseOrderController/save');                 // 创建订单
        Route::get('/:id', 'PurchaseOrderController/read');               // 订单详情
        Route::put('/:id', 'PurchaseOrderController/update');             // 更新订单
        Route::delete('/:id', 'PurchaseOrderController/delete');          // 删除订单
        Route::post('/:id/audit', 'PurchaseOrderController/audit');       // 审核订单
        Route::post('/:id/cancel', 'PurchaseOrderController/cancel');     // 取消订单
        Route::post('/:id/complete', 'PurchaseOrderController/complete'); // 完成订单
        Route::get('/:id/items', 'PurchaseOrderController/items');        // 订单明细
        Route::post('/:id/items', 'PurchaseOrderController/addItem');     // 添加明细
        Route::put('/:id/items/:item_id', 'PurchaseOrderController/updateItem'); // 更新明细
        Route::delete('/:id/items/:item_id', 'PurchaseOrderController/deleteItem'); // 删除明细
    });

    // 采购入库
    Route::group('purchase/stocks', function () {
        Route::get('/', 'PurchaseStockController/index');                 // 入库列表
        Route::post('/', 'PurchaseStockController/save');                 // 创建入库
        Route::get('/:id', 'PurchaseStockController/read');               // 入库详情
        Route::put('/:id', 'PurchaseStockController/update');             // 更新入库
        Route::delete('/:id', 'PurchaseStockController/delete');          // 删除入库
        Route::post('/:id/audit', 'PurchaseStockController/audit');       // 审核入库
        Route::post('/:id/cancel', 'PurchaseStockController/cancel');     // 取消入库
        Route::get('/:id/items', 'PurchaseStockController/items');        // 入库明细
        Route::post('/:id/items', 'PurchaseStockController/addItem');     // 添加明细
        Route::put('/:id/items/:item_id', 'PurchaseStockController/updateItem'); // 更新明细
        Route::delete('/:id/items/:item_id', 'PurchaseStockController/deleteItem'); // 删除明细
    });

    // ==================== 销售管理模块 ====================

    // 销售订单
    Route::group('sale/orders', function () {
        Route::get('/', 'SaleOrderController/index');                     // 订单列表
        Route::post('/', 'SaleOrderController/save');                     // 创建订单
        Route::get('/:id', 'SaleOrderController/read');                   // 订单详情
        Route::put('/:id', 'SaleOrderController/update');                 // 更新订单
        Route::delete('/:id', 'SaleOrderController/delete');              // 删除订单
        Route::post('/:id/audit', 'SaleOrderController/audit');           // 审核订单
        Route::post('/:id/cancel', 'SaleOrderController/cancel');         // 取消订单
        Route::post('/:id/complete', 'SaleOrderController/complete');     // 完成订单
        Route::get('/:id/items', 'SaleOrderController/items');            // 订单明细
        Route::post('/:id/items', 'SaleOrderController/addItem');         // 添加明细
        Route::put('/:id/items/:item_id', 'SaleOrderController/updateItem'); // 更新明细
        Route::delete('/:id/items/:item_id', 'SaleOrderController/deleteItem'); // 删除明细
    });

    // 销售出库
    Route::group('sale/stocks', function () {
        Route::get('/', 'SaleStockController/index');                     // 出库列表
        Route::post('/', 'SaleStockController/save');                     // 创建出库
        Route::get('/:id', 'SaleStockController/read');                   // 出库详情
        Route::put('/:id', 'SaleStockController/update');                 // 更新出库
        Route::delete('/:id', 'SaleStockController/delete');              // 删除出库
        Route::post('/:id/audit', 'SaleStockController/audit');           // 审核出库
        Route::post('/:id/cancel', 'SaleStockController/cancel');         // 取消出库
        Route::get('/:id/items', 'SaleStockController/items');            // 出库明细
        Route::post('/:id/items', 'SaleStockController/addItem');         // 添加明细
        Route::put('/:id/items/:item_id', 'SaleStockController/updateItem'); // 更新明细
        Route::delete('/:id/items/:item_id', 'SaleStockController/deleteItem'); // 删除明细
        Route::post('/:id/complete', 'SaleStockController/complete');       // 完成出库
    });

    // ==================== 库存管理模块 ====================

    # 库存模块改为 SKU 维度
    Route::group('stocks', function () {
        Route::get('/skus', 'StockController/skuIndex');                    # 库存列表
        Route::get('/skus/:id/warehouses', 'StockController/skuWarehouse'); # 分仓库存
        Route::put('/skus/:id', 'StockController/skuUpdate');               # 库存调整
        Route::get('/skus/warning', 'StockController/skuWarning');          # 库存预警
        Route::get('/skus/statistics', 'StockController/skuStatistics');    # 库存统计
    });


    // // 库存查询
    // Route::group('stocks', function () {
    //     Route::get('/', 'StockController/index');                         // 库存列表
    //     Route::get('/:id', 'StockController/read');                       // 库存详情
    //     Route::get('/warning', 'StockController/warning');                // 库存预警
    //     Route::get('/statistics', 'StockController/statistics');          // 库存统计
    //     Route::get('/:product_id/warehouses', 'StockController/warehouseStocks'); // 商品在各仓库库存
    // });

    // 库存盘点
    Route::group('stock/takes', function () {
        Route::get('/', 'StockTakeController/index');                     // 盘点列表
        Route::post('/', 'StockTakeController/save');                     // 创建盘点
        Route::get('/:id', 'StockTakeController/read');                   // 盘点详情
        Route::put('/:id', 'StockTakeController/update');                 // 更新盘点
        Route::delete('/:id', 'StockTakeController/delete');              // 删除盘点
        Route::post('/:id/audit', 'StockTakeController/audit');           // 审核盘点
        Route::post('/:id/complete', 'StockTakeController/complete');     // 完成盘点
        Route::post('/:id/cancel', 'StockTakeController/cancel');         // 取消盘点
        Route::get('/:id/items', 'StockTakeController/items');            // 盘点明细
        Route::post('/:id/items', 'StockTakeController/addItem');         // 添加明细
        Route::put('/:id/items/:item_id', 'StockTakeController/updateItem'); // 更新明细
        Route::delete('/:id/items/:item_id', 'StockTakeController/deleteItem'); // 删除明细
    });

    // 库存调拨
    Route::group('stock/transfers', function () {
        Route::get('/', 'StockTransferController/index');                 // 调拨列表
        Route::post('/', 'StockTransferController/save');                 // 创建调拨
        Route::get('/:id', 'StockTransferController/read');               // 调拨详情
        Route::put('/:id', 'StockTransferController/update');             // 更新调拨
        Route::delete('/:id', 'StockTransferController/delete');          // 删除调拨
        Route::post('/:id/audit', 'StockTransferController/audit');       // 审核调拨
        Route::post('/:id/complete', 'StockTransferController/complete'); // 完成调拨
        Route::post('/:id/cancel', 'StockTransferController/cancel');     // 取消调拨
        Route::get('/:id/items', 'StockTransferController/items');        // 调拨明细
        Route::post('/:id/items', 'StockTransferController/addItem');     // 添加明细
        Route::put('/:id/items/:item_id', 'StockTransferController/updateItem'); // 更新明细
        Route::delete('/:id/items/:item_id', 'StockTransferController/deleteItem'); // 删除明细
    });

    // ==================== 财务管理模块 ====================

    // 财务收支
    Route::group('financial/records', function () {
        Route::get('/', 'FinancialRecordController/index');               // 收支列表
        Route::post('/', 'FinancialRecordController/save');               // 添加收支
        Route::get('/:id', 'FinancialRecordController/read');             // 收支详情
        Route::put('/:id', 'FinancialRecordController/update');           // 更新收支
        Route::delete('/:id', 'FinancialRecordController/delete');        // 删除收支
        Route::get('/categories', 'FinancialRecordController/categories'); // 收支类别
        Route::get('/statistics', 'FinancialRecordController/statistics'); // 收支统计
    });

    // 账款管理
    Route::group('account/records', function () {
        Route::get('/', 'AccountRecordController/index');                 // 账款列表
        Route::get('/summary', 'AccountRecordController/summary');        // 账款汇总
        Route::post('/:id/pay', 'AccountRecordController/pay');           // 支付账款
        Route::get('/receivable', 'AccountRecordController/receivable');  // 应收款项
        Route::get('/payable', 'AccountRecordController/payable');        // 应付款项
        Route::get('/statistics', 'AccountRecordController/statistics');  // 账款统计
    });

    // 财务报表
    Route::group('financial/reports', function () {
        Route::get('/profit', 'FinancialReportController/profit');        // 利润报表
        Route::get('/cashflow', 'FinancialReportController/cashflow');    // 资金流水
        Route::get('/sales', 'FinancialReportController/sales');          // 销售报表
        Route::get('/purchase', 'FinancialReportController/purchase');    // 采购报表
        Route::get('/inventory', 'FinancialReportController/inventory');  // 库存报表
    });

    // ==================== 系统管理模块 ====================

    // 用户管理
    Route::group('users', function () {
        Route::get('/', 'UserController/index');                          // 用户列表
        Route::post('/', 'UserController/save');                          // 添加用户
        Route::get('/:id', 'UserController/read');                        // 用户详情
        Route::put('/:id', 'UserController/update');                      // 更新用户
        Route::delete('/:id', 'UserController/delete');                   // 删除用户
        Route::post('/:id/status', 'UserController/setStatus');           // 设置状态
        Route::post('/:id/reset-password', 'UserController/resetPassword'); // 重置密码
    });

    // 角色管理
    Route::group('roles', function () {
        Route::get('/', 'RoleController/index');                          // 角色列表
        Route::post('/', 'RoleController/save');                          // 添加角色
        Route::get('/:id', 'RoleController/read');                        // 角色详情
        Route::put('/:id', 'RoleController/update');                      // 更新角色
        Route::delete('/:id', 'RoleController/delete');                   // 删除角色
        Route::get('/options', 'RoleController/options');                 // 角色选项
        Route::get('/permissions', 'RoleController/permissions');         // 权限列表
    });

    // 系统配置
    Route::group('system', function () {
        Route::get('/configs', 'SystemController/configs');               // 配置列表
        Route::post('/configs', 'SystemController/saveConfigs');          // 保存配置
        Route::get('/configs/:group', 'SystemController/configGroup');    // 分组配置
        Route::get('/info', 'SystemController/info');                     // 系统信息
        Route::get('/logs', 'SystemController/logs');                     // 系统日志
        Route::delete('/logs', 'SystemController/clearLogs');             // 清理日志
    });

    // ==================== 工具和辅助接口 ====================

    // 通用工具
    Route::group('utils', function () {
        Route::get('number/generate/:type', 'UtilsController/generateNumber'); // 生成编号
        Route::post('upload/image', 'UtilsController/uploadImage');       // 图片上传
        Route::post('upload/file', 'UtilsController/uploadFile');         // 文件上传
        Route::get('regions', 'UtilsController/regions');                 // 地区数据
        Route::get('units', 'UtilsController/units');                     // 单位数据
    });

    // 数据统计
    Route::group('analytics', function () {
        Route::get('sales', 'AnalyticsController/sales');                 // 销售分析
        Route::get('purchase', 'AnalyticsController/purchase');           // 采购分析
        Route::get('inventory', 'AnalyticsController/inventory');         // 库存分析
        Route::get('financial', 'AnalyticsController/financial');         // 财务分析
        Route::get('customer', 'AnalyticsController/customer');           // 客户分析
        Route::get('supplier', 'AnalyticsController/supplier');           // 供应商分析
    });
})->middleware([$jwtMiddleware]);

// 默认路由和测试路由
Route::get('/', 'IndexController/index');
Route::get('test', 'TestController/index');
Route::get('debug/routes', 'DebugController/routes');
Route::get('debug/app', 'DebugController/appInfo');

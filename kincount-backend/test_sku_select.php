<?php

// 测试 skuSelect 方法
require __DIR__ . '/vendor/autoload.php';

// 初始化 ThinkPHP 应用
$app = new \think\App();
$app->initialize();

try {
    echo 'Testing skuSelect method...\n';
    
    // 获取 ProductController 实例
    $controller = new \app\kincount\controller\ProductController();
    
    // 模拟请求参数
    $request = \think\facade\Request::create('/kincount/product/skuSelect', 'GET', [
        'keyword' => 'test',
        'category_id' => '',
        'brand_id' => '',
        'limit' => 20,
        'page' => 1
    ]);
    
    // 执行方法
    $result = $controller->skuSelect();
    
    echo 'Success! Result: ' . var_export($result, true) . '\n';
    
} catch (\Exception $e) {
    echo 'Error: ' . $e->getMessage() . '\n';
    echo 'Trace: ' . $e->getTraceAsString() . '\n';
}

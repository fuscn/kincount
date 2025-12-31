<?php

// 加载ThinkPHP框架
require __DIR__ . '/vendor/autoload.php';

use think\App;
use think\facade\Request;
use app\kincount\controller\ProductController;

// 初始化应用
$app = new App();
$app->initialize();

// 创建模拟请求
$request = Request::instance();
$request->withGet([
    'keyword' => 'test',
    'category_id' => null,
    'brand_id' => null
]);

try {
    echo "Testing skuSelect method directly...\n\n";
    
    // 创建ProductController实例
    $controller = new ProductController();
    
    // 调用skuSelect方法
    $result = $controller->skuSelect();
    
    echo "Success! Result: \n";
    print_r($result);
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
}
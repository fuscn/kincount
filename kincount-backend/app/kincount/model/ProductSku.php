<?php

namespace app\kincount\model;

use think\facade\Db;
use think\Model;

class ProductSku extends BaseModel
{
    // 自动将spec字段序列化为JSON，查询时反序列化为数组
    protected $json = ['spec'];

    // 关联商品主表（product表）
    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    // 关联库存表（stock表）
    public function stocks()
    {
        return $this->hasMany(Stock::class, 'sku_id', 'id');
    }

    // 关联仓库表（warehouse表）
    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_id');
    }

    // 预定义颜色简码映射
    private static $colorCodeMap = [
        '红色' => 'R',
        '蓝色' => 'B',
        '黑色' => 'BK',
        '白色' => 'W',
        '黄色' => 'Y',
        '绿色' => 'G',
        '紫色' => 'P',
        '橙色' => 'O',
        '灰色' => 'GR',
        '无颜色' => 'N'
    ];

    // 注册模型事件 - TP8 新方式
    public static function onBeforeInsert($model)
    {
        // 1. 校验核心属性
        if (empty($model->product_id)) {
            throw new \Exception('商品ID不能为空，无法生成SKU编码');
        }
        if (empty($model->spec)) {
            throw new \Exception('规格信息不能为空，无法提取颜色');
        }

        // 2. 生成SKU编码
        $model->sku_code = self::generateSkuCode($model->product_id, $model->spec);

        // 3. 检测条码是否为空，为空则自动补全
        if (empty($model->barcode) || $model->barcode === '') {
            $model->barcode = self::generateBarcode();
        }
    }

    public static function onBeforeUpdate($model)
    {
        // 禁止修改SKU编码
        if ($model->getOrigin('sku_code') !== $model->sku_code) {
            throw new \Exception('SKU编码不允许修改');
        }

        // 更新时若条码被清空，自动重新生成
        if (empty($model->barcode) || $model->barcode === '') {
            $model->barcode = self::generateBarcode();
        }
    }

    /**
     * 生成全数字唯一条码
     */
    public static function generateBarcode()
    {
        $timestamp = time();
        $random = mt_rand(1000, 9999);
        $barcode = (string)$timestamp . (string)$random;

        // 修复：使用 find() 方法替代 exists() 来检查条码是否存在
        while (self::where('barcode', $barcode)->whereNull('deleted_at')->find()) {
            $random = mt_rand(1000, 9999);
            $barcode = (string)$timestamp . (string)$random;
        }

        return $barcode;
    }

    /**
     * 生成SKU编码
     */
    public static function generateSkuCode($productId, $spec)
    {
        $prefix = 'SKU';

        // 1. 通过商品ID查询商品主表
        $product = Product::find($productId);
        if (!$product || empty($product->product_no)) {
            throw new \Exception("商品ID{$productId}不存在或未配置商品编号，无法生成SKU");
        }
        $productNo = $product->product_no;

        // 2. 剥离商品编号的前缀P
        $productNoMain = preg_replace('/^P/i', '', $productNo);
        if (empty($productNoMain)) {
            throw new \Exception("商品编号{$productNo}格式错误，无法提取主体部分");
        }

        // 3. 从规格数组中自动提取颜色
        $color = $spec['颜色'] ?? '无颜色';

        // 4. 获取颜色简码
        $colorCode = self::$colorCodeMap[$color] ?? 'N';

        // 5. 生成两位递增序列号
        $serialNo = self::getSerialNoByProductAndColor($productNoMain, $colorCode);
        $serialNoStr = str_pad($serialNo, 2, '0', STR_PAD_LEFT);

        return $prefix . $productNoMain . $colorCode . $serialNoStr;
    }

    /**
     * 获取基于商品编号+颜色的递增序列号
     */
    private static function getSerialNoByProductAndColor($productNoMain, $colorCode)
    {
        $skuPrefix = 'SKU' . $productNoMain . $colorCode;
        $count = self::where('sku_code', 'like', $skuPrefix . '%')->count();
        return $count + 1;
    }

    /**
     * 获取规格文本
     */
    public function getSpecTextAttr()
    {
        $spec = $this->spec ?? [];
        $specText = [];
        foreach ($spec as $k => $v) {
            $specText[] = "$k:$v";
        }
        return implode(' | ', $specText);
    }
}

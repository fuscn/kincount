<?php

namespace app\kincount\model;

use think\exception\ValidateException;
use think\facade\Validate;

class ProductSku extends BaseModel
{

    // 定义验证规则
    protected $rule = [
        'sku_code'    => 'require|unique:product_skus,sku_code^deleted_at',
        'product_id'  => 'require|integer|gt:0',
        'spec'        => 'require|array',
        'cost_price'  => 'require|float|egt:0',
        'sale_price'  => 'require|float|egt:0',
        'barcode'     => 'unique:product_skus,barcode^deleted_at',
        'unit'        => 'require|max:10',
        'status'      => 'require|in:0,1',
    ];
    // 定义错误信息
    protected $message = [
        'sku_code.require'    => 'SKU编码不能为空',
        'sku_code.unique'     => 'SKU编码已存在',
        'product_id.require'  => '商品ID不能为空',
        'product_id.integer'  => '商品ID必须是整数',
        'product_id.gt'       => '商品ID必须大于0',
        'spec.require'        => '规格不能为空',
        'spec.array'          => '规格必须是数组格式',
        'cost_price.require'  => '成本价不能为空',
        'cost_price.float'    => '成本价必须是数字',
        'cost_price.egt'      => '成本价必须大于等于0',
        'sale_price.require'  => '销售价不能为空',
        'sale_price.float'    => '销售价必须是数字',
        'sale_price.egt'      => '销售价必须大于等于0',
        'barcode.unique'      => '条码已存在',
        'unit.require'        => '单位不能为空',
        'unit.max'            => '单位长度不能超过10个字符',
        'status.require'      => '状态不能为空',
        'status.in'           => '状态值只能是0或1',
    ];
    // 定义验证场景
    protected $scene = [
        'create' => [
            'sku_code',
            'product_id',
            'spec',
            'cost_price',
            'sale_price',
            'barcode',
            'unit',
            'status'
        ],
        'update' => [
            'sku_code',
            'spec',
            'cost_price',
            'sale_price',
            'barcode',
            'unit',
            'status'
        ],
        'batch_create' => [
            'product_id',
            'spec',
            'cost_price',
            'sale_price',
            'unit'
        ],
    ];

    /**
     * 验证数据 - 使用独立验证器
     */
    public function validateData($data, $scene = '')
    {
        $validate = Validate::rule($this->rule);
        $validate->message($this->message);

        // 如果有场景，可以设置场景
        if ($scene && isset($this->scene[$scene])) {
            $validate->scene($scene, $this->scene[$scene]);
        }

        if (!$validate->check($data)) {
            throw new ValidateException($validate->getError());
        }

        return true;
    }

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
        '红色' => 'RE',
        '蓝色' => 'BL',
        '黑色' => 'BK',
        '白色' => 'WH',
        '黄色' => 'YE',
        '绿色' => 'GN',
        '紫色' => 'PU',
        '粉色' => 'PI',
        '橙色' => 'OR',
        '灰色' => 'GY',
        '无颜色' => 'NC'
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
     * 批量创建SKU时的验证
     */
    public function validateBatchCreate($skusData, $productId)
    {
        foreach ($skusData as $index => $sku) {
            $data = array_merge($sku, ['product_id' => $productId]);

            if (!$this->validate('batch_create')->check($data)) {
                throw new ValidateException("第 " . ($index + 1) . " 个SKU验证失败: " . $this->getError());
            }
        }
        return true;
    }
    /**
     * 生成全数字唯一条码 - 修复版本，避免重复
     */
    public static function generateBarcode($attempt = 1)
    {
        $timestamp = time();
        $random = mt_rand(1000, 9999);
        $barcode = (string)$timestamp . (string)$random;

        // 检查条码是否已存在（包括软删除的记录）
        $exists = self::withTrashed()
            ->where('barcode', $barcode)
            ->find();

        if ($exists && $attempt < 10) {
            // 如果存在且尝试次数小于10次，递归调用并增加尝试次数
            return self::generateBarcode($attempt + 1);
        } elseif ($exists) {
            // 如果尝试10次后仍然重复，使用更复杂的方法生成
            $microtime = microtime(true);
            $random2 = mt_rand(10000, 99999);
            $barcode = substr((string)($microtime * 1000000) . $random2, 0, 15);

            // 再次检查
            $existsAgain = self::withTrashed()
                ->where('barcode', $barcode)
                ->find();

            if ($existsAgain) {
                // 如果还是重复，使用UUID的一部分
                $barcode = substr(uniqid('', true) . mt_rand(1000, 9999), 0, 15);
            }
        }

        return $barcode;
    }

    /**
     * 生成SKU编码 - 完全修复版本
     */
    public static function generateSkuCode($productId, $spec, $attempt = 1)
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

        // 5. 生成两位递增序列号 - 修复逻辑，正确处理软删除记录
        $serialNo = self::getSerialNoByProductAndColor($productId, $productNoMain, $colorCode, $attempt);
        $serialNoStr = str_pad($serialNo, 2, '0', STR_PAD_LEFT);

        $skuCode = $prefix . $productNoMain . $colorCode . $serialNoStr;

        // 6. 检查生成的SKU编码是否已存在（包括软删除的记录）
        $exists = self::withTrashed()
            ->where('sku_code', $skuCode)
            ->find();

        if ($exists && $attempt < 10) {
            // 如果存在且尝试次数小于10次，递归调用并增加尝试次数
            return self::generateSkuCode($productId, $spec, $attempt + 1);
        } elseif ($exists) {
            // 如果尝试10次后仍然重复，抛出异常
            throw new \Exception("无法生成唯一的SKU编码，最后尝试的编码: {$skuCode}");
        }

        return $skuCode;
    }

    /**
     * 获取基于商品编号+颜色的递增序列号 - 完全修复版本
     * 正确处理软删除记录，确保序列号正确递增
     */
    private static function getSerialNoByProductAndColor($productId, $productNoMain, $colorCode, $attempt = 1)
    {
        $skuPrefix = 'SKU' . $productNoMain . $colorCode;

        // 查询该商品下该颜色的所有SKU（包括软删除的）
        $skus = self::withTrashed()
            ->where('product_id', $productId)
            ->where('sku_code', 'like', $skuPrefix . '%')
            ->order('id', 'desc')
            ->select();

        if ($skus->isEmpty()) {
            return $attempt; // 没有找到，从尝试次数开始
        }

        $maxSerial = 0;
        foreach ($skus as $sku) {
            // 从SKU编码中提取序列号部分
            $code = $sku->sku_code;
            $serialPart = substr($code, -2);
            if (is_numeric($serialPart)) {
                $serial = (int) $serialPart;
                if ($serial > $maxSerial) {
                    $maxSerial = $serial;
                }
            }
        }

        // 基础序列号是最大序列号 + 尝试次数
        return $maxSerial + $attempt;
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

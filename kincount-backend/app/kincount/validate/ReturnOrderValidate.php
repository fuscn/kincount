<?php
namespace app\kincount\validate;

use think\Validate;

class ReturnOrderValidate extends Validate
{
    protected $rule = [
        'type' => 'require|in:0,1',
        'target_id' => 'require|integer|gt:0',
        'warehouse_id' => 'require|integer|gt:0',
        'source_order_id' => 'integer',
        'source_stock_id' => 'integer',
        'return_date' => 'date',
        'return_type' => 'in:0,1,2,3',
        'return_reason' => 'max:500',
        'remark' => 'max:500',
        'items' => 'require|array|min:1',
        'items.*.sku_id' => 'require|integer|gt:0',
        'items.*.product_id' => 'require|integer|gt:0',
        'items.*.return_quantity' => 'require|integer|min:1',
        'items.*.price' => 'require|float|min:0.01',
    ];
    
    protected $message = [
        'type.require' => '退货类型不能为空',
        'type.in' => '退货类型错误（0:销售退货, 1:采购退货）',
        'target_id.require' => '目标客户/供应商不能为空',
        'target_id.integer' => '目标客户/供应商格式错误',
        'target_id.gt' => '目标客户/供应商必须大于0',
        'warehouse_id.require' => '仓库不能为空',
        'warehouse_id.integer' => '仓库格式错误',
        'warehouse_id.gt' => '仓库必须大于0',
        'return_date.date' => '退货日期格式错误',
        'return_type.in' => '退货原因类型错误（0:质量问题, 1:数量问题, 2:客户/供应商取消, 3:其他）',
        'return_reason.max' => '退货原因不能超过500个字符',
        'remark.max' => '备注不能超过500个字符',
        'items.require' => '退货商品不能为空',
        'items.array' => '退货商品格式错误',
        'items.min' => '至少需要一个退货商品',
        'items.*.sku_id.require' => 'SKU ID不能为空',
        'items.*.sku_id.integer' => 'SKU ID格式错误',
        'items.*.sku_id.gt' => 'SKU ID必须大于0',
        'items.*.product_id.require' => '产品ID不能为空',
        'items.*.product_id.integer' => '产品ID格式错误',
        'items.*.product_id.gt' => '产品ID必须大于0',
        'items.*.return_quantity.require' => '退货数量不能为空',
        'items.*.return_quantity.integer' => '退货数量必须是整数',
        'items.*.return_quantity.min' => '退货数量必须大于0',
        'items.*.price.require' => '价格不能为空',
        'items.*.price.float' => '价格格式错误',
        'items.*.price.min' => '价格必须大于0',
    ];
    
    // 场景设置
    protected $scene = [
        'create' => [
            'type', 'target_id', 'warehouse_id', 'return_date', 'return_type',
            'return_reason', 'remark', 'items',
            'items.*.sku_id', 'items.*.product_id', 
            'items.*.return_quantity', 'items.*.price'
        ],
    ];
}
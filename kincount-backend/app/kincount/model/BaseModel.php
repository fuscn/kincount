<?php

namespace app\kincount\model;

use think\Model;
use think\model\concern\SoftDelete;

class BaseModel extends Model
{
    use SoftDelete;

    protected $deleteTime = 'deleted_at';
    protected $defaultSoftDelete = null;

    protected $autoWriteTimestamp = true;
    protected $createTime = 'created_at';
    protected $updateTime = 'updated_at';


    /**
     * 生成唯一编号
     */
    protected function generateUniqueNo($prefix, $field = 'order_no')
    {
        do {
            $number = $prefix . date('YmdHis') . str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);
        } while ($this->where($field, $number)->find());

        return $number;
    }

    /**
     * 获取状态选项
     */
    public static function getStatusOptions()
    {
        return [
            0 => '禁用',
            1 => '启用'
        ];
    }

    /**
     * 获取状态文本
     */
    public function getStatusTextAttr($value, $data)
    {
        $status = $data['status'] ?? 0;
        $options = static::getStatusOptions();
        return $options[$status] ?? '未知';
    }
    /**
     * 自动设置表名：模型名转小写+复数
     * 例如：Warehouse 模型 → warehouses 表
     */
    public function __construct(array $data = [])
    {
        parent::__construct($data);

        // 动态设置表名
        if (empty($this->table)) {
            $className = basename(str_replace('\\', '/', static::class));

            // 将驼峰转换为下划线
            $tableName = strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $className));

            // 确保表名是复数形式
            if (!str_ends_with($tableName, 's')) {
                $tableName .= 's';
            }

            $this->table = $tableName;
        }
    }
}

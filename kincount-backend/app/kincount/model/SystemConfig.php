<?php
namespace app\kincount\model;


class SystemConfig extends BaseModel
{
    // 禁用软删除
    protected $deleteTime = false;
    
    /**
     * 获取配置值 - 重命名避免冲突
     */
    public static function getConfigValue($key, $default = null)
    {
        $config = self::where('config_key', $key)->find();
        return $config ? $config->config_value : $default;
    }
    
    /**
     * 设置配置值 - 重命名避免冲突
     */
    public static function setConfigValue($key, $value, $name = null, $group = 'default')
    {
        $config = self::where('config_key', $key)->find();
        if ($config) {
            $config->config_value = $value;
            $config->config_name = $name ?: $config->config_name;
            $config->config_group = $group ?: $config->config_group;
            return $config->save();
        } else {
            return self::create([
                'config_key' => $key,
                'config_value' => $value,
                'config_name' => $name ?: $key,
                'config_group' => $group
            ]);
        }
    }
    
    /**
     * 获取分组配置
     */
    public static function getGroupConfigs($group)
    {
        return self::where('config_group', $group)
            ->column('config_value', 'config_key');
    }
    
    /**
     * 批量设置配置
     */
    public static function setConfigs($configs, $group = 'default')
    {
        foreach ($configs as $key => $value) {
            self::setConfigValue($key, $value, null, $group);
        }
        return true;
    }
    
    /**
     * 获取公司名称
     */
    public static function getCompanyName()
    {
        return self::getConfigValue('company_name', '简库存管理系统');
    }
    
    /**
     * 获取公司地址
     */
    public static function getCompanyAddress()
    {
        return self::getConfigValue('company_address', '');
    }
    
    /**
     * 获取公司电话
     */
    public static function getCompanyPhone()
    {
        return self::getConfigValue('company_phone', '');
    }
    
    /**
     * 是否开启低库存预警
     */
    public static function isLowStockWarningEnabled()
    {
        return self::getConfigValue('low_stock_warning', '1') == '1';
    }
    
    /**
     * 是否自动审核订单
     */
    public static function isAutoAuditOrderEnabled()
    {
        return self::getConfigValue('auto_audit_order', '0') == '1';
    }
}
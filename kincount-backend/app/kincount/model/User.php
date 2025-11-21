<?php

namespace app\kincount\model;



class User extends BaseModel
{

    protected $type = [
        'role_id' => 'integer',
        'status' => 'integer'
    ];

    // 关联角色
    public function role()
    {
        return $this->belongsTo(Role::class);
    }

    // 获取器 - 状态文本
    public function getStatusTextAttr($value, $data)
    {
        $status = $data['status'] ?? 0;
        return $status == 1 ? '启用' : '禁用';
    }

    // 修改器 - 密码加密
    public function setPasswordAttr($value)
    {
        return password_hash($value, PASSWORD_BCRYPT);
    }

    // 验证密码
    public function checkPassword($password)
    {
        return password_verify($password, $this->password);
    }
}

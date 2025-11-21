<?php

namespace app\kincount\model;

class Role extends BaseModel
{
    
    protected $type = [
        'status' => 'integer',
        'permissions' => 'json'
    ];

    protected $json = ['permissions'];

    // 关联用户
    public function users()
    {
        return $this->hasMany(User::class);
    }
}

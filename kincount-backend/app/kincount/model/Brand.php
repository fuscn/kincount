<?php
namespace app\kincount\model;

class Brand extends BaseModel
{
    
   
    protected $type = [
        'status' => 'integer'
    ];
    
    // 关联商品
    public function products()
    {
        return $this->hasMany(Product::class);
    }
}
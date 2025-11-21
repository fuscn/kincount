<?php
namespace app\kincount\model;

class Category extends BaseModel
{
//    protected $table = 'categories';//默认数据表
    protected $type = [
        'parent_id' => 'integer',
        'sort' => 'integer',
        'status' => 'integer'
    ];
    
    // 关联商品
    public function products()
    {
        return $this->hasMany(Product::class);
    }
    
    // 关联父级分类
    public function parent()
    {
        return $this->belongsTo(Category::class, 'parent_id');
    }
    
    // 关联子分类
    public function children()
    {
        return $this->hasMany(Category::class, 'parent_id');
    }
    
    // 获取完整路径
    public function getFullPathAttr()
    {
        $path = [];
        $category = $this;
        
        while ($category) {
            $path[] = $category->name;
            $category = $category->parent;
        }
        
        return implode(' > ', array_reverse($path));
    }
}
<?php

declare(strict_types=1);

namespace app\kincount\controller;

use app\kincount\model\Category;
use app\kincount\model\Product;

class CategoryController extends BaseController
{
    public function index()
    {
        $kw     = input('keyword', '');
        $status = input('status', '');
        $query  = Category::where('deleted_at', null);
        if ($kw) $query->whereLike('name', "%{$kw}%");
        if ($status !== '') $query->where('status', $status);

        $flat = $query->order('sort')->order('id')->select()->toArray();
        return $this->success($this->buildTree($flat));
    }

    public function read($id)
    {
        $cate = Category::where('deleted_at', null)->find($id);
        if (!$cate) return $this->error('分类不存在');
        $cate['parent_name'] = $cate->parent->name ?? '顶级分类';
        return $this->success($cate);
    }

    public function save()
    {
        $post = input('post.');
        $validate = new \think\Validate(['name' => 'require', 'parent_id' => 'integer']);
        if (!$validate->check($post)) return $this->error($validate->getError());

        $id = Category::create($post)->id;
        return $this->success(['id' => $id], '分类添加成功');
    }

    public function update($id)
    {
        $cate = Category::where('deleted_at', null)->find($id);
        if (!$cate) return $this->error('分类不存在');
        $post = input('post.');
        if (isset($post['parent_id']) && $post['parent_id'] == $id) return $this->error('不能设置自己为父级');

        $cate->save($post);
        return $this->success([], '分类更新成功');
    }

    public function delete($id)
    {
        $cate = Category::where('deleted_at', null)->find($id);
        if (!$cate) return $this->error('分类不存在');
        if (Category::where('parent_id', $id)->find()) return $this->error('存在子分类，无法删除');
        if (Product::where('category_id', $id)->find()) return $this->error('分类下已有商品，无法删除');

        $cate->delete();
        return $this->success([], '分类删除成功');
    }

    public function tree()
    {
        $flat = Category::where('status', 1)
            ->where('deleted_at', null)
            ->order('sort')
            ->order('id')
            ->select()
            ->toArray();

        return $this->success($this->buildTree($flat));
    }

    public function options()
    {
        $flat = Category::where('status', 1)->where('deleted_at', null)
            ->field('id, name, parent_id')->order('sort')->select()->toArray();
        return $this->success($this->buildTreeOptions($flat));
    }

    /* ---------- 内部工具 ---------- */
    private function buildTree($items, $pid = 0)
    {
        $tree = [];
        foreach ($items as $item) {
            if ($item['parent_id'] == $pid) {
                $children = $this->buildTree($items, $item['id']);
                if ($children) $item['children'] = $children;
                $tree[] = $item;
            }
        }
        return $tree;
    }

    private function buildTreeOptions($items, $pid = 0, $level = 0)
    {
        $opts = [];
        foreach ($items as $item) {
            if ($item['parent_id'] == $pid) {
                $opts[] = ['value' => $item['id'], 'label' => str_repeat('--', $level) . $item['name']];
                $opts   = array_merge($opts, $this->buildTreeOptions($items, $item['id'], $level + 1));
            }
        }
        return $opts;
    }
}

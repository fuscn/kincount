<?php
declare (strict_types = 1);

namespace app\kincount\controller;

use app\kincount\model\Brand;
use app\kincount\model\Product;

class BrandController extends BaseController
{
    public function index()
    {
        $page  = (int)input('page', 1);
        $limit = (int)input('limit', 15);
        $kw    = input('keyword', '');
        $status= input('status', '');

        $query = Brand::where('deleted_at', null);
        if ($kw !== '') $query->whereLike('name|code', "%{$kw}%");
        if ($status !== '') $query->where('status', $status);

        return $this->paginate($query->order('sort')->order('id', 'desc')
                                   ->paginate(['list_rows' => $limit, 'page' => $page]));
    }

    public function read($id)
    {
        $brand = Brand::where('deleted_at', null)->find($id);
        \think\facade\Log::info('ddddddddd'.$brand);
        if (!$brand) return $this->error('品牌不存在1');
        return $this->success($brand);
    }

    public function save()
    {
        $post = input('post.');
        $validate = new \think\Validate(['name' => 'require', 'code' => 'require|unique:brands']);
        if (!$validate->check($post)) return $this->error($validate->getError());

        $id = Brand::create($post)->id;
        return $this->success(['id' => $id], '品牌添加成功');
    }

    public function update($id)
    {
        $brand = Brand::where('deleted_at', null)->find($id);
        if (!$brand) return $this->error('品牌不存在');

        $post = input('post.');
        if (isset($post['code']) && $post['code'] != $brand['code']) {
            if (Brand::where('code', $post['code'])->where('id', '<>', $id)->find()) {
                return $this->error('品牌编码已存在');
            }
        }
        $brand->save($post);
        return $this->success([], '品牌更新成功');
    }

    public function delete($id)
    {
        $brand = Brand::where('deleted_at', null)->find($id);
        if (!$brand) return $this->error('品牌不存在');
        if (Product::where('brand_id', $id)->find()) return $this->error('该品牌下已有商品，无法删除');

        $brand->delete();
        return $this->success([], '品牌删除成功');
    }

    public function options()
    {
        return $this->success(
            Brand::where('status', 1)->where('deleted_at', null)
                 ->field('id, name, code')->order('sort')->select()
        );
    }

    public function batch()
    {
        $action = input('action');
        $ids    = input('ids/a', []);
        if (empty($ids)) return $this->error('请选择要操作的品牌');

        switch ($action) {
            case 'enable':  Brand::whereIn('id', $ids)->update(['status' => 1]); break;
            case 'disable': Brand::whereIn('id', $ids)->update(['status' => 0]); break;
            case 'delete':
                if (Product::whereIn('brand_id', $ids)->find()) return $this->error('品牌下存在商品，无法删除');
                Brand::destroy($ids);
                break;
            default: return $this->error('未知操作');
        }
        return $this->success([], '操作成功');
    }
}
<?php

declare(strict_types=1);

namespace app\kincount\controller;

use think\facade\Filesystem;

class UtilsController extends BaseController
{
    /** 生成编号 */
    public function generateNumber($type)
    {
        $prefix = [
            'product'   => 'P',
            'purchase'  => 'PO',
            'sale'      => 'SO',
            'stock'     => 'PS',
            'transfer'  => 'TR',
            'take'      => 'ST',
            'financial' => 'FR',
        ][$type] ?? 'NO';

        $date = date('YmdHis');
        $rand = str_pad((string)mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);
        return $this->success(['number' => $prefix . $date . $rand]);
    }

    /** 图片上传 */
    public function uploadImage()
    {
        $file = request()->file('file');
        if (!$file) return $this->error('未上传文件');

        try {
            validate(['file' => 'filesize:5120|fileExt:jpg,jpeg,png,gif'])
                ->check(['file' => $file]);
            $savename = Filesystem::disk('public')->putFile('image', $file);
            return $this->success(['url' => '/storage/' . str_replace('\\', '/', $savename)]);
        } catch (\think\exception\ValidateException $e) {
            return $this->error($e->getMessage());
        }
    }

    /** 文件上传 */
    public function uploadFile()
    {
        $file = request()->file('file');
        if (!$file) return $this->error('未上传文件');

        try {
            validate(['file' => 'filesize:10240|fileExt:zip,rar,xls,xlsx,doc,docx,pdf'])
                ->check(['file' => $file]);
            $savename = Filesystem::disk('public')->putFile('file', $file);
            return $this->success([
                'url'  => '/storage/' . str_replace('\\', '/', $savename),
                'name' => $file->getOriginalName(),
            ]);
        } catch (\think\exception\ValidateException $e) {
            return $this->error($e->getMessage());
        }
    }

    /** 省市区数据（静态） */
    public function regions()
    {
        $data = [
            ['value' => '110000', 'label' => '北京市', 'children' => [
                ['value' => '110100', 'label' => '北京市', 'children' => [
                    ['value' => '110101', 'label' => '东城区'],
                    ['value' => '110102', 'label' => '西城区'],
                ]],
            ]],
            // 可继续扩展或接入真实库
        ];
        return $this->success($data);
    }

    /** 计量单位 */
    public function units()
    {
        return $this->success([
            '个',
            '件',
            '箱',
            '袋',
            '桶',
            '升',
            '千克',
            '吨',
            '米',
            '平方米',
            '立方米',
        ]);
    }
    /** 统一下拉数据 */
    public function options($module)
    {
        $map = [
            'category' => fn() => \app\kincount\model\Category::where('status', 1)->column('name', 'id'),
            'brand'    => fn() => \app\kincount\model\Brand::where('status', 1)->column('name', 'id'),
            'unit'     => fn() => ['个', '件', '箱', '袋', '桶', '升', 'kg', '吨', '米', '㎡', 'm³'],
            'warehouse' => fn() => \app\kincount\model\Warehouse::where('status', 1)->column('name', 'id'),
            'supplier' => fn() => \app\kincount\model\Supplier::where('status', 1)->column('name', 'id'),
            'customer' => fn() => \app\kincount\model\Customer::where('status', 1)->column('name', 'id'),
            'role'     => fn() => \app\kincount\model\Role::where('status', 1)->column('name', 'id'),
        ];

        if (!isset($map[$module])) return $this->error('未知模块');
        return $this->success($map[$module]());
    }
}

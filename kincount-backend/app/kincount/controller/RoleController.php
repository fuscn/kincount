<?php
declare (strict_types = 1);

namespace app\kincount\controller;

use app\kincount\model\Role;

class RoleController extends BaseController
{
    /** 角色列表 */
    public function index()
    {
        $page  = (int)input('page', 1);
        $limit = (int)input('limit', 15);
        $kw    = input('keyword', '');

        $query = Role::where('deleted_at', null);
        if ($kw) $query->whereLike('name', "%{$kw}%");

        return $this->paginate($query->order('id', 'desc')
                                   ->paginate(['list_rows' => $limit, 'page' => $page]));
    }

    /** 单条详情 */
    public function read($id)
    {
        $role = Role::where('deleted_at', null)->find($id);
        if (!$role) return $this->error('角色不存在');
        return $this->success($role);
    }

    /** 新增角色 */
    public function save()
    {
        $post = input('post.');
        $validate = new \think\Validate([
            'name' => 'require|unique:roles',
            'permissions' => 'array'
        ]);
        if (!$validate->check($post)) return $this->error($validate->getError());

        $id = Role::create([
            'name'        => $post['name'],
            'description' => $post['description'] ?? '',
            'permissions' => $post['permissions'] ?? [],
            'status'      => 1,
        ])->id;

        return $this->success(['id' => $id], '角色添加成功');
    }

    /** 更新角色 */
    public function update($id)
    {
        $role = Role::where('deleted_at', null)->find($id);
        if (!$role) return $this->error('角色不存在');

        $post = input('post.');
        if (isset($post['name']) && $post['name'] != $role->name) {
            if (Role::where('name', $post['name'])->find()) {
                return $this->error('角色名称已存在');
            }
        }
        $role->save([
            'name'        => $post['name'] ?? $role->name,
            'description' => $post['description'] ?? $role->description,
            'permissions' => $post['permissions'] ?? $role->permissions,
            'status'      => $post['status'] ?? $role->status,
        ]);
        return $this->success([], '角色更新成功');
    }

    /** 删除角色（软删） */
    public function delete($id)
    {
        $role = Role::where('deleted_at', null)->find($id);
        if (!$role) return $this->error('角色不存在');
        if (\app\kincount\model\User::where('role_id', $id)->find()) {
            return $this->error('角色下存在用户，无法删除');
        }
        $role->delete();
        return $this->success([], '角色删除成功');
    }

    /** 角色下拉（仅 id/name） */
    public function options()
    {
        return $this->success(
            Role::where('status', 1)
                ->where('deleted_at', null)
                ->field('id, name')
                ->order('id')
                ->select()
        );
    }

    /** 权限清单（前端勾选） */
    public function permissions()
    {
        return $this->success([
            'dashboard' => ['name' => '仪表盘', 'perms' => ['dashboard:view']],
            'product'   => ['name' => '商品资料', 'perms' => ['product:view', 'product:add', 'product:edit', 'product:delete']],
            'purchase'  => ['name' => '采购管理', 'perms' => ['purchase:view', 'purchase:add', 'purchase:audit', 'purchase:delete']],
            'sale'      => ['name' => '销售管理', 'perms' => ['sale:view', 'sale:add', 'sale:audit', 'sale:delete']],
            'stock'     => ['name' => '库存管理', 'perms' => ['stock:view', 'stock:take', 'stock:transfer', 'stock:warning']],
            'finance'   => ['name' => '财务管理', 'perms' => ['finance:view', 'finance:add', 'finance:report']],
            'system'    => ['name' => '系统管理', 'perms' => ['user:manage', 'role:manage', 'config:manage']],
        ]);
    }
}
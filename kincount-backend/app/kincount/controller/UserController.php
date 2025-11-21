<?php

declare(strict_types=1);

namespace app\kincount\controller;

use app\kincount\model\User;
use app\kincount\model\Role;
use think\facade\Db;

class UserController extends BaseController
{
    public function index()
    {
        $page  = (int)input('page', 1);
        $limit = (int)input('limit', 15);
        $kw    = input('keyword', '');
        $roleId = (int)input('role_id', 0);
        $status = input('status', '');

        $query = User::with(['role'])->where('deleted_at', null);
        if ($kw) $query->whereLike('username|real_name|phone|email', "%{$kw}%");
        if ($roleId) $query->where('role_id', $roleId);
        if ($status !== '') $query->where('status', $status);

        return $this->paginate($query->order('id', 'desc')
            ->paginate(['list_rows' => $limit, 'page' => $page]));
    }

    public function read($id)
    {
        $user = User::with(['role'])->where('deleted_at', null)->find($id);
        if (!$user) return $this->error('用户不存在');
        unset($user['password']);
        return $this->success($user);
    }

    public function save()
    {
        $post = input('post.');
        $validate = new \think\Validate([
            'username' => 'require|unique:users',
            'password' => 'require|min:6',
            'real_name' => 'require',
            'role_id'  => 'require|integer'
        ]);
        if (!$validate->check($post)) return $this->error($validate->getError());

        if (!Role::where('id', $post['role_id'])->where('deleted_at', null)->find()) {
            return $this->error('角色不存在');
        }

        $post['password'] = password_hash($post['password'], PASSWORD_BCRYPT);
        $id = User::create($post)->id;
        return $this->success(['id' => $id], '用户添加成功');
    }

    public function update($id)
    {
        $user = User::where('deleted_at', null)->find($id);
        if (!$user) return $this->error('用户不存在');

        $post = input('post.');
        if (isset($post['username']) && $post['username'] != $user->username) {
            if (User::where('username', $post['username'])->where('id', '<>', $id)->find()) {
                return $this->error('用户名已存在');
            }
        }
        if (isset($post['password']) && $post['password'] !== '') {
            if (strlen($post['password']) < 6) return $this->error('密码长度不能少于6位');
            $post['password'] = password_hash($post['password'], PASSWORD_BCRYPT);
        } else {
            unset($post['password']);
        }
        if (isset($post['role_id'])) {
            if (!Role::where('id', $post['role_id'])->where('deleted_at', null)->find()) {
                return $this->error('角色不存在');
            }
        }

        $user->save($post);
        return $this->success([], '用户更新成功');
    }

    public function delete($id)
    {
        $user = User::where('deleted_at', null)->find($id);
        if (!$user) return $this->error('用户不存在');
        if ($user->id == $this->getUserId()) return $this->error('不能删除自己');

        $user->delete();
        return $this->success([], '用户删除成功');
    }

    public function resetPassword($id)
    {
        $user = User::where('deleted_at', null)->find($id);
        if (!$user) return $this->error('用户不存在');

        $newPwd = input('new_password', '123456');
        $confirm = input('confirm_password', '123456');
        if ($newPwd !== $confirm) return $this->error('两次密码不一致');
        if (strlen($newPwd) < 6) return $this->error('密码长度不能少于6位');

        $user->save(['password' => password_hash($newPwd, PASSWORD_BCRYPT)]);
        return $this->success([], '密码重置成功');
    }

    public function batch()
    {
        $action = input('action');
        $ids    = input('ids/a', []);
        if (empty($ids)) return $this->error('请选择要操作的用户');
        if (in_array($this->getUserId(), $ids)) return $this->error('不能操作自己');

        switch ($action) {
            case 'enable':
                User::whereIn('id', $ids)->update(['status' => 1]);
                return $this->success([], '批量启用成功');
            case 'disable':
                User::whereIn('id', $ids)->update(['status' => 0]);
                return $this->success([], '批量禁用成功');
            case 'delete':
                User::destroy($ids);
                return $this->success([], '批量删除成功');
            default:
                return $this->error('未知操作');
        }
    }

    public function statistics()
    {
        /* 角色分布 */
        $roleStats = Db::name('users')
            ->alias('u')
            ->join('roles r', 'u.role_id = r.id')
            ->where('u.deleted_at', null)
            ->field('r.name role_name, count(*) count')
            ->group('u.role_id')
            ->select();

        /* 状态分布 */
        $statusStats = Db::name('users')
            ->where('deleted_at', null)
            ->field('status, count(*) count')
            ->group('status')
            ->select();

        /* 总数 & 今日新增 */
        $total = Db::name('users')->where('deleted_at', null)->count();
        $today = Db::name('users')->whereRaw("DATE(created_at)=?", [date('Y-m-d')])->where('deleted_at', null)->count();

        return $this->success([
            'role_stats'   => $roleStats,
            'status_stats' => $statusStats,
            'total_users'  => $total,
            'today_new_users' => $today,
        ]);
    }
    /** 设置用户状态 */
    public function setStatus($id)
    {
        $user = User::where('deleted_at', null)->find($id);
        if (!$user) return $this->error('用户不存在');

        $status = input('status', 0);
        if (!in_array($status, [0, 1])) return $this->error('状态值无效');

        $user->save(['status' => $status]);
        return $this->success([], '状态更新成功');
    }
}

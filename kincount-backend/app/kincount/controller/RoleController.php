<?php

declare(strict_types=1);

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

        // 使用 withCount 统计每个角色的用户数量
        return $this->paginate($query->order('id', 'desc')
            ->withCount('users')
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
            // 仪表盘
            'dashboard' => [
                'name' => '仪表盘',
                'perms' => [
                    [
                        'key' => 'dashboard:view',
                        'name' => '查看仪表盘',
                        'description' => '查看系统仪表盘和统计数据'
                    ]
                ]
            ],

            // 商品资料
            'product' => [
                'name' => '商品资料',
                'perms' => [
                    [
                        'key' => 'product:view',
                        'name' => '查看商品',
                        'description' => '查看商品列表和详细信息'
                    ],
                    [
                        'key' => 'product:add',
                        'name' => '新增商品',
                        'description' => '创建新的商品资料'
                    ],
                    [
                        'key' => 'product:edit',
                        'name' => '编辑商品',
                        'description' => '修改商品资料信息'
                    ],
                    [
                        'key' => 'product:delete',
                        'name' => '删除商品',
                        'description' => '删除商品资料'
                    ]
                ]
            ],

            // 分类管理
            'category' => [
                'name' => '分类管理',
                'perms' => [
                    [
                        'key' => 'category:view',
                        'name' => '查看分类',
                        'description' => '查看商品分类列表'
                    ],
                    [
                        'key' => 'category:add',
                        'name' => '新增分类',
                        'description' => '创建新的商品分类'
                    ],
                    [
                        'key' => 'category:edit',
                        'name' => '编辑分类',
                        'description' => '修改商品分类信息'
                    ],
                    [
                        'key' => 'category:delete',
                        'name' => '删除分类',
                        'description' => '删除商品分类'
                    ]
                ]
            ],

            // 品牌管理
            'brand' => [
                'name' => '品牌管理',
                'perms' => [
                    [
                        'key' => 'brand:view',
                        'name' => '查看品牌',
                        'description' => '查看品牌列表'
                    ],
                    [
                        'key' => 'brand:add',
                        'name' => '新增品牌',
                        'description' => '创建新的品牌'
                    ],
                    [
                        'key' => 'brand:edit',
                        'name' => '编辑品牌',
                        'description' => '修改品牌信息'
                    ],
                    [
                        'key' => 'brand:delete',
                        'name' => '删除品牌',
                        'description' => '删除品牌'
                    ]
                ]
            ],

            // 客户管理
            'customer' => [
                'name' => '客户管理',
                'perms' => [
                    [
                        'key' => 'customer:view',
                        'name' => '查看客户',
                        'description' => '查看客户列表和详细信息'
                    ],
                    [
                        'key' => 'customer:add',
                        'name' => '新增客户',
                        'description' => '创建新的客户资料'
                    ],
                    [
                        'key' => 'customer:edit',
                        'name' => '编辑客户',
                        'description' => '修改客户资料信息'
                    ],
                    [
                        'key' => 'customer:delete',
                        'name' => '删除客户',
                        'description' => '删除客户资料'
                    ]
                ]
            ],

            // 供应商管理
            'supplier' => [
                'name' => '供应商管理',
                'perms' => [
                    [
                        'key' => 'supplier:view',
                        'name' => '查看供应商',
                        'description' => '查看供应商列表和详细信息'
                    ],
                    [
                        'key' => 'supplier:add',
                        'name' => '新增供应商',
                        'description' => '创建新的供应商资料'
                    ],
                    [
                        'key' => 'supplier:edit',
                        'name' => '编辑供应商',
                        'description' => '修改供应商资料信息'
                    ],
                    [
                        'key' => 'supplier:delete',
                        'name' => '删除供应商',
                        'description' => '删除供应商资料'
                    ]
                ]
            ],

            // 仓库管理
            'warehouse' => [
                'name' => '仓库管理',
                'perms' => [
                    [
                        'key' => 'warehouse:view',
                        'name' => '查看仓库',
                        'description' => '查看仓库列表和详细信息'
                    ],
                    [
                        'key' => 'warehouse:add',
                        'name' => '新增仓库',
                        'description' => '创建新的仓库'
                    ],
                    [
                        'key' => 'warehouse:edit',
                        'name' => '编辑仓库',
                        'description' => '修改仓库信息'
                    ],
                    [
                        'key' => 'warehouse:delete',
                        'name' => '删除仓库',
                        'description' => '删除仓库'
                    ]
                ]
            ],

            // 采购管理
            'purchase' => [
                'name' => '采购管理',
                'perms' => [
                    [
                        'key' => 'purchase:view',
                        'name' => '查看采购单',
                        'description' => '查看采购订单列表和详细信息'
                    ],
                    [
                        'key' => 'purchase:add',
                        'name' => '新增采购单',
                        'description' => '创建新的采购订单'
                    ],
                    [
                        'key' => 'purchase:audit',
                        'name' => '审核采购单',
                        'description' => '审核采购订单'
                    ],
                    [
                        'key' => 'purchase:delete',
                        'name' => '删除采购单',
                        'description' => '删除采购订单'
                    ]
                ]
            ],

            // 采购入库管理
            'purchase_stock' => [
                'name' => '采购入库',
                'perms' => [
                    [
                        'key' => 'purchase_stock:view',
                        'name' => '查看采购入库',
                        'description' => '查看采购入库记录'
                    ],
                    [
                        'key' => 'purchase_stock:add',
                        'name' => '新增入库',
                        'description' => '创建采购入库单'
                    ],
                    [
                        'key' => 'purchase_stock:audit',
                        'name' => '审核入库',
                        'description' => '审核采购入库单'
                    ],
                    [
                        'key' => 'purchase_stock:cancel',
                        'name' => '取消入库',
                        'description' => '取消采购入库操作'
                    ]
                ]
            ],

            // 销售管理
            'sale' => [
                'name' => '销售管理',
                'perms' => [
                    [
                        'key' => 'sale:view',
                        'name' => '查看销售单',
                        'description' => '查看销售订单列表和详细信息'
                    ],
                    [
                        'key' => 'sale:add',
                        'name' => '新增销售单',
                        'description' => '创建新的销售订单'
                    ],
                    [
                        'key' => 'sale:audit',
                        'name' => '审核销售单',
                        'description' => '审核销售订单'
                    ],
                    [
                        'key' => 'sale:delete',
                        'name' => '删除销售单',
                        'description' => '删除销售订单'
                    ]
                ]
            ],

            // 销售出库管理
            'sale_stock' => [
                'name' => '销售出库',
                'perms' => [
                    [
                        'key' => 'sale_stock:view',
                        'name' => '查看销售出库',
                        'description' => '查看销售出库记录'
                    ],
                    [
                        'key' => 'sale_stock:add',
                        'name' => '新增出库',
                        'description' => '创建销售出库单'
                    ],
                    [
                        'key' => 'sale_stock:audit',
                        'name' => '审核出库',
                        'description' => '审核销售出库单'
                    ],
                    [
                        'key' => 'sale_stock:cancel',
                        'name' => '取消出库',
                        'description' => '取消销售出库操作'
                    ]
                ]
            ],

            // 退货管理
            'return' => [
                'name' => '退货管理',
                'perms' => [
                    [
                        'key' => 'return:view',
                        'name' => '查看退货单',
                        'description' => '查看退货订单列表和详细信息'
                    ],
                    [
                        'key' => 'return:add',
                        'name' => '新增退货单',
                        'description' => '创建新的退货订单'
                    ],
                    [
                        'key' => 'return:audit',
                        'name' => '审核退货单',
                        'description' => '审核退货订单'
                    ],
                    [
                        'key' => 'return:delete',
                        'name' => '删除退货单',
                        'description' => '删除退货订单'
                    ]
                ]
            ],

            // 退货出入库管理
            'return_stock' => [
                'name' => '退货出入库',
                'perms' => [
                    [
                        'key' => 'return_stock:view',
                        'name' => '查看退货出入库',
                        'description' => '查看退货出入库记录'
                    ],
                    [
                        'key' => 'return_stock:create',
                        'name' => '创建退货出入库',
                        'description' => '创建退货出入库单'
                    ],
                    [
                        'key' => 'return_stock:audit',
                        'name' => '审核退货出入库',
                        'description' => '审核退货出入库单'
                    ],
                    [
                        'key' => 'return_stock:cancel',
                        'name' => '取消退货出入库',
                        'description' => '取消退货出入库操作'
                    ]
                ]
            ],

            // 库存管理
            'stock' => [
                'name' => '库存管理',
                'perms' => [
                    [
                        'key' => 'stock:view',
                        'name' => '查看库存',
                        'description' => '查看商品库存信息'
                    ],
                    [
                        'key' => 'stock:take',
                        'name' => '库存盘点',
                        'description' => '进行库存盘点操作'
                    ],
                    [
                        'key' => 'stock:transfer',
                        'name' => '库存调拨',
                        'description' => '进行库存调拨操作'
                    ],
                    [
                        'key' => 'stock:warning',
                        'name' => '库存预警',
                        'description' => '查看库存预警信息'
                    ]
                ]
            ],

            // 库存盘点
            'stock_take' => [
                'name' => '库存盘点',
                'perms' => [
                    [
                        'key' => 'stock_take:view',
                        'name' => '查看盘点记录',
                        'description' => '查看库存盘点记录'
                    ],
                    [
                        'key' => 'stock_take:add',
                        'name' => '新增盘点',
                        'description' => '创建新的库存盘点单'
                    ],
                    [
                        'key' => 'stock_take:audit',
                        'name' => '审核盘点',
                        'description' => '审核库存盘点结果'
                    ],
                    [
                        'key' => 'stock_take:cancel',
                        'name' => '取消盘点',
                        'description' => '取消库存盘点操作'
                    ]
                ]
            ],

            // 库存调拨
            'stock_transfer' => [
                'name' => '库存调拨',
                'perms' => [
                    [
                        'key' => 'stock_transfer:view',
                        'name' => '查看调拨记录',
                        'description' => '查看库存调拨记录'
                    ],
                    [
                        'key' => 'stock_transfer:add',
                        'name' => '新增调拨',
                        'description' => '创建新的库存调拨单'
                    ],
                    [
                        'key' => 'stock_transfer:audit',
                        'name' => '审核调拨',
                        'description' => '审核库存调拨申请'
                    ],
                    [
                        'key' => 'stock_transfer:cancel',
                        'name' => '取消调拨',
                        'description' => '取消库存调拨操作'
                    ]
                ]
            ],

            // 账款管理
            'account' => [
                'name' => '账款管理',
                'perms' => [
                    [
                        'key' => 'account_receivable:view',
                        'name' => '查看应收款',
                        'description' => '查看客户应收账款'
                    ],
                    [
                        'key' => 'account_receivable:settle',
                        'name' => '应收结算',
                        'description' => '进行应收账款结算'
                    ],
                    [
                        'key' => 'account_payable:view',
                        'name' => '查看应付款',
                        'description' => '查看供应商应付账款'
                    ],
                    [
                        'key' => 'account_payable:settle',
                        'name' => '应付结算',
                        'description' => '进行应付账款结算'
                    ],
                    [
                        'key' => 'settlement:view',
                        'name' => '查看结算记录',
                        'description' => '查看账款结算历史记录'
                    ]
                ]
            ],

            // 财务管理
            'finance' => [
                'name' => '财务管理',
                'perms' => [
                    [
                        'key' => 'finance:view',
                        'name' => '查看财务',
                        'description' => '查看财务报表和统计数据'
                    ],
                    [
                        'key' => 'finance:add',
                        'name' => '新增财务记录',
                        'description' => '添加财务收支记录'
                    ],
                    [
                        'key' => 'finance:report',
                        'name' => '财务报表',
                        'description' => '生成和查看财务报表'
                    ]
                ]
            ],

            // 系统管理
            'system' => [
                'name' => '系统管理',
                'perms' => [
                    // 用户管理
                    [
                        'key' => 'user:view',
                        'name' => '查看用户',
                        'description' => '查看系统用户列表'
                    ],
                    [
                        'key' => 'user:add',
                        'name' => '新增用户',
                        'description' => '创建新的系统用户'
                    ],
                    [
                        'key' => 'user:edit',
                        'name' => '编辑用户',
                        'description' => '修改用户信息'
                    ],
                    [
                        'key' => 'user:delete',
                        'name' => '删除用户',
                        'description' => '删除系统用户'
                    ],
                    [
                        'key' => 'user:reset_pwd',
                        'name' => '重置密码',
                        'description' => '重置用户登录密码'
                    ],

                    // 角色管理
                    [
                        'key' => 'role:view',
                        'name' => '查看角色',
                        'description' => '查看系统角色列表'
                    ],
                    [
                        'key' => 'role:add',
                        'name' => '新增角色',
                        'description' => '创建新的系统角色'
                    ],
                    [
                        'key' => 'role:edit',
                        'name' => '编辑角色',
                        'description' => '修改角色权限设置'
                    ],
                    [
                        'key' => 'role:delete',
                        'name' => '删除角色',
                        'description' => '删除系统角色'
                    ],

                    // 系统配置
                    [
                        'key' => 'config:view',
                        'name' => '查看配置',
                        'description' => '查看系统配置信息'
                    ],
                    [
                        'key' => 'config:edit',
                        'name' => '编辑配置',
                        'description' => '修改系统配置参数'
                    ],
                    
                    // 系统信息
                    [
                        'key' => 'system:info:view',
                        'name' => '查看系统信息',
                        'description' => '查看系统状态和环境信息'
                    ],
                    
                    // 系统日志
                    [
                        'key' => 'system:logs:view',
                        'name' => '查看系统日志',
                        'description' => '查看系统操作日志'
                    ]
                ]
            ]
        ]);
    }
}

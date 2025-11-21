<?php
use think\facade\Cache;
use think\Response;

/**
 * 成功返回
 */
function success($data = [], $msg = '操作成功', $code = 200)
{
    return json([
        'code' => $code,
        'msg' => $msg,
        'data' => $data
    ]);
}

/**
 * 错误返回
 */
function error($msg = '操作失败', $code = 400, $data = [])
{
    return json([
        'code' => $code,
        'msg' => $msg,
        'data' => $data
    ]);
}

/**
 * 生成订单号
 */
function generateOrderNo($prefix = 'PO')
{
    return $prefix . date('YmdHis') . str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);
}

/**
 * 密码加密
 */
function bcrypt($password)
{
    return password_hash($password, PASSWORD_BCRYPT);
}

/**
 * 验证密码
 */
function checkPassword($password, $hash)
{
    return password_verify($password, $hash);
}
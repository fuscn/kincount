<?php
return [
    // 默认应用
    'default_app'      => '',
    // 开启自动多模式
    'auto_multi_app'   => true,
    // 应用映射
    'app_map'         => [
        'k' => 'kincount',
    ],
    // 域名绑定
    'domain_bind'      => [],
    // 禁止访问的应用
    'deny_app_list'    => [],
    // 异常页面的模板文件
    'exception_tmpl'   => app()->getThinkPath() . 'tpl/think_exception.tpl',
    // 错误显示信息,非调试模式有效
    'error_message'    => '页面错误！请稍后再试～',
    // 显示错误信息
    'show_error_msg'   => true,
];

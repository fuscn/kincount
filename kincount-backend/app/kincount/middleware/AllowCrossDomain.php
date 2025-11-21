<?php
namespace app\kincount\middleware;

use think\Response;

class AllowCrossDomain
{
    public function handle($request, \Closure $next)
    {
        $response = $next($request);

        if ($response instanceof Response) {
            $response->header([
                'Access-Control-Allow-Origin'  => '*',
                'Access-Control-Allow-Methods' => 'GET,POST,PUT,DELETE,OPTIONS',
                'Access-Control-Allow-Headers' => 'Authorization,Content-Type,New-Authorization',
                'Access-Control-Expose-Headers'=> 'New-Authorization',
            ]);
        }

        // 如果是预检请求，直接返回 204
        if ($request->isOptions()) {
            return response('', 204);
        }

        return $response;
    }
}
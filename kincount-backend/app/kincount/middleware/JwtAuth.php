<?php
namespace app\kincount\middleware;

use think\facade\Db;
use think\facade\Cache;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class JwtAuth
{
    public function handle($request, \Closure $next)
    {
        
        // 获取token
        $token = $request->header('Authorization');
        
        if (empty($token)) {
            return json([
                'code' => 401,
                'msg' => '访问令牌不能为空',
                'data' => null
            ]);
        }
        
        // 移除Bearer前缀
        if (strpos($token, 'Bearer ') === 0) {
            $token = substr($token, 7);
        }
        
        try {
            // 解码JWT token
            $decoded = JWT::decode($token, new Key(config('jwt.secret'), config('jwt.algorithm')));
            
            // 验证用户是否存在且启用
            $user = $this->getUserFromToken($decoded);
                
            if (!$user) {
                return json([
                    'code' => 401,
                    'msg' => '用户不存在或已被禁用',
                    'data' => null
                ]);
            }
            
            // 将用户信息存入请求对象
            $request->user = $user;
            $request->user_id = $user['id'];
            
            // 检查是否需要刷新token（如果token剩余时间小于30分钟）
            $now = time();
            $exp = $decoded->exp;
            $refreshTime = config('jwt.auto_refresh', 7200); // 30分钟
            
            if (($exp - $now) < $refreshTime) {
                // 生成新的token
                $newToken = $this->generateToken($user);
                
                // 在响应头中添加新的token
                $response = $next($request);
                $response->header([
                    'New-Authorization' => $newToken,
                    'Access-Control-Expose-Headers' => 'New-Authorization' // 允许前端访问这个头
                ]);
                return $response;
            }
            
        } catch (\Firebase\JWT\ExpiredException $e) {
            // 如果是刷新接口，允许过期token通过
            if ($this->isRefreshEndpoint($request)) {
                try {
                    // 尝试解析过期token获取用户信息
                    $decoded = $this->decodeExpiredToken($token);
                    $user = $this->getUserFromToken($decoded);
                    
                    if ($user) {
                        $request->user = $user;
                        $request->user_id = $user['id'];
                        return $next($request);
                    }
                } catch (\Exception $ex) {
                    // 如果解析失败，返回错误
                    return json([
                        'code' => 401,
                        'msg' => '访问令牌已过期且无法刷新',
                        'data' => null
                    ]);
                }
            }
            
            return json([
                'code' => 401,
                'msg' => '访问令牌已过期',
                'data' => null
            ]);
        } catch (\Firebase\JWT\SignatureInvalidException $e) {
            return json([
                'code' => 401,
                'msg' => '访问令牌无效',
                'data' => null
            ]);
        } catch (\Exception $e) {
            return json([
                'code' => 401,
                'msg' => '访问令牌验证失败: ' . $e->getMessage(),
                'data' => null
            ]);
        }
        
        return $next($request);
    }
    
    /**
     * 根据token解码结果获取用户信息
     */
    private function getUserFromToken($decoded)
    {
        // 使用缓存提高性能
        $cacheKey = 'user_' . $decoded->user_id;
        $user = Cache::get($cacheKey);
        
        if (!$user) {
            $user = Db::name('users')
                ->where('id', $decoded->user_id)
                ->where('status', 1)
                ->where('deleted_at', null)
                ->find();
                
            if ($user) {
                // 缓存5分钟
                Cache::set($cacheKey, $user, 300);
            }
        }
        
        return $user;
    }
    
    /**
     * 检查是否是刷新token的接口
     */
    private function isRefreshEndpoint($request)
    {
        $path = $request->pathinfo();
        return strpos($path, 'auth/refresh') !== false;
    }
    
    /**
     * 解码过期的token（仅用于刷新）
     */
    private function decodeExpiredToken($token)
    {
        // 手动解析token，不验证过期时间
        $parts = explode('.', $token);
        if (count($parts) != 3) {
            throw new \Exception('Token格式错误');
        }
        
        list($headb64, $bodyb64, $cryptob64) = $parts;
        
        // 解码payload
        $payload = JWT::jsonDecode(JWT::urlsafeB64Decode($bodyb64));
        
        // 验证签名（重要！）
        $signature = JWT::urlsafeB64Decode($cryptob64);
        $expectedSignature = hash_hmac(
            'SHA256',
            "$headb64.$bodyb64",
            config('jwt.secret'),
            true
        );
        
        if (!hash_equals($signature, $expectedSignature)) {
            throw new \Exception('Token签名无效');
        }
        
        return $payload;
    }
    
    /**
     * 生成JWT Token
     */
    private function generateToken($user)
    {
        $payload = [
            'iss' => config('jwt.issuer', 'kincount-system'),
            'iat' => time(),
            'exp' => time() + config('jwt.access_ttl', 172800), // 过期时间(48小时)
            'user_id' => $user['id'],
            'username' => $user['username'],
            'role_id' => $user['role_id']
        ];
        
        return JWT::encode($payload, config('jwt.secret'), config('jwt.algorithm'));
    }
}
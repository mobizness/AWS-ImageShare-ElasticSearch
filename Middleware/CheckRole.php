<?php

namespace App\Http\Middleware;

use Closure;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
class CheckRole
{
    public function handle($request, Closure $next) 
    {
        $roles = $this->getRouteRoles($request->route());

        if(is_null($roles)) {
            return $next($request);
        }
        
        if(!is_null($request->user())) {
            if($request->user()->hasRole($roles)) {
                return $next($request);
            }
        }
        
        throw new UnauthorizedHttpException('roles', 'You do not have permission to perform this action', null, 401);
    }
    
    public function getRouteRoles($route)
    {
        $actions = $route->getAction();
        
        return isset($actions['roles']) ? $actions['roles'] : null;
    }
}
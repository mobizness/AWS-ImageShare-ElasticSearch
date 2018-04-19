<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Contracts\Support\Arrayable;
use Tymon\JWTAuth\Http\Middleware\BaseMiddleware;
use Tymon\JWTAuth\Exceptions\JWTException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Illuminate\Http\Request;

class ApiMiddleware extends BaseMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        config()->set('session.driver', 'array');

        if($request->has('page_size')) {
            $pageSize = $request->input('page_size');
            
            if(($pageSize < 0) || ($pageSize > 100)) {
                $pageSize = 100;
            }
            
        } else {
            $pageSize = 100;
        }
        
        $request['page_size'] = (int)$pageSize;
        
        $response = $next($request);
        
        // This to issue a new Token every request (needed for storing data in JWT via sessions)
        
        // $this->checkForToken($request);
        // $response = $this->createResponseToken($response);
        
        return $response;
        
    }
    
    protected function createResponseToken(Request $request, \Illuminate\Http\Response $response)
    {
        $this->checkForToken($request);
        
        try {
            $token = $this->auth->parseToken()->refresh();
        } catch (JWTException $e) {
            throw new UnauthorizedHttpException('jwt-auth', $e->getMessage(), $e, $e->getCode());
        }
        
        // send the refreshed token back to the client
        $response->headers->set('Authorization', 'Bearer '.$token);
        
        return $response;
    }
}

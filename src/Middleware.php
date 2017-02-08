<?php
/*
RedisCache.php - Redis cache middleware for Slim framework
Copyright 2015 abouvier <abouvier@student.42.fr>

Licensed under the Apache License, Version 2.0 (the "License");
you may not use this file except in compliance with the License.
You may obtain a copy of the License at

http://www.apache.org/licenses/LICENSE-2.0

Unless required by applicable law or agreed to in writing, software
distributed under the License is distributed on an "AS IS" BASIS,
WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
See the License for the specific language governing permissions and
limitations under the License.
 */

namespace SlimRedisCache;

use \Predis\ClientInterface;

class Middleware 
{
    protected $client;
    protected $settings;

    /**
     * corse  middleware invokable class
     *
     * @param  \Psr\Http\Message\ServerRequestInterface $request  PSR7 request
     * @param  \Psr\Http\Message\ResponseInterface      $response PSR7 response
     * @param  callable                                 $next     Next middleware
     *
     * @return \Psr\Http\Message\ResponseInterface
     */
    public function __invoke($request, $response, $next)
    {
        //Only Cache GET Requests
        if($request->getMethod() !== "GET"){ 
            $response = $next($request, $response);
            return $response;
        }
        
        //Configure Cache Key
        $key = $request->getUri()->getPath(); 
        if (!empty($request->getUri()->getQuery())){
            $key .= '?' . $request->getUri()->getQuery(); 
        }

        //If cache exists return response
        if ($this->client->exists($key)) {
            $cacheString  = $this->client->get($key);
            $cacheObject = unserialize($cacheString);
            foreach($cacheObject['headers'] as $header => $value){
                $response = $response->withHeader($header,$value);
            }

            $ttl = $this->client->ttl($key);
            $response = $response->withHeader("X-Powered-By","Cache");
            $response = $response->withHeader("Cache-TTL",$ttl);

            $body = $response->getBody();
            $body->write($cacheObject['body']);


            return $response;
        }


        //No cache, pass response
        $response = $next($request, $response);

        //As long as response is good, save to cache
        if ($response->getStatusCode() == 200) {

            $cacheObject = [
                "body" => (string) $response->getBody(), 
                "headers"=> $response->getHeaders()
            ];
            $cacheString = serialize($cacheObject);
            $this->client->set($key, $cacheString);
            if (array_key_exists('timeout', $this->settings)){
                $this->client->expire($key, $this->settings['timeout']);
            }
            $response = $response->withHeader("X-Powered-By","No-Cache");
        }

        return $response;
    }

    public function __construct(ClientInterface $client, array $settings = [])
    {
        $this->client = $client;
        $this->settings = $settings;
    }

    public function call()
    {

        $this->next->call();

        if ($response->getStatus() == 200) {
            $this->client->set($key, $response->getBody());
            if (array_key_exists('timeout', $this->settings))
                $this->client->expire($key, $this->settings['timeout']);
        }
    }
}

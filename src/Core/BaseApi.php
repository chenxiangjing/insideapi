<?php
/**
 * Created by PhpStorm.
 * User: mayunfeng
 * Date: 2017/11/15
 * Time: 15:18
 */

namespace InsideApi\Core;


use InsideApi\Support\Log;
use InsideApi\Support\Collection;
use Psr\Http\Message\RequestInterface;
use GuzzleHttp\Middleware;
/**
 * BaseApi use before login
 * Class BaseApi
 * @package common\library\api\core
 */
abstract class BaseApi
{

    const POST = 'post';

    const GET = 'get';

    /*
     * @var Http
     */
    protected $http;


    /**
     * Return the http instance.
     *
     * @return Http
     */
    public function getHttp()
    {
        if (is_null($this->http)) {
            $this->http = new Http();
        }

        if (count($this->http->getMiddlewares()) === 0) {
            $this->registerHttpMiddlewares();
        }

        return $this->http;
    }

    /**
     * Set the http instance.
     *
     * @param Http $http
     *
     * @return $this
     */
    public function setHttp(Http $http)
    {
        $this->http = $http;

        return $this;
    }


    /**
     * 注册中间件
     */
    protected function registerHttpMiddlewares()
    {

        $this->http->addMiddleware($this->accessTokenMiddleware());
        // log
        $this->http->addMiddleware($this->logMiddleware());
    }

    /**
     * Attache access token to request query.
     *
     * @return \Closure
     */
    protected function accessTokenMiddleware()
    {
        return function (callable $handler) {
            return function (RequestInterface $request, array $options) use ($handler) {
                $request = $request->withHeader('token',ApiConfig::TOKEN);
                $request = $request->withHeader('accesstoken',ApiConfig::ACCESS_KEY);
                return $handler($request, $options);
            };
        };
    }

    /**
     * Log the request.
     *
     * @return \Closure
     */
    protected function logMiddleware()
    {
        return Middleware::tap(function (RequestInterface $request, $options) {
            Log::debug("请求: {$request->getMethod()} {$request->getUri()} ".json_encode($options));
//            Log::debug('请求头:'.json_encode($request->getHeaders()));
        });
    }

    /**
     * Parse JSON from response and check error.
     *
     * @param string $method
     * @param array  $args
     *
     * @return string
     */
    public function parseJSON($method, array $args)
    {
        $http = $this->getHttp();

        $contents = $http->parseJSON(call_user_func_array([$http, $method], $args));

        if (isset($contents['Body']) && !empty($contents['Body'])) {
            $contents['Body'] = \GuzzleHttp\json_decode($contents['Body'],true);
        }
        $contents = $this->checkAndThrow($contents);

        return new Collection($contents);
    }


    /**
     * @param array $contents
     * @return array
     * @throws BadRequestHttpException
     */
    protected function checkAndThrow(array $contents)
    {
        if (isset($contents['Header']['Status']) && 0 !== $contents['Header']['Status']) {
            if (empty($contents['Header']['Falures'])) {
                $contents['Header']['Falures'][0]['Mess'] = '未知原因';
            }
            if (empty($contents['Header']['Falures'])) {
                $contents['Header']['Falures'][0]['Code'] = '-1';
            }
            $contents['Success'] = false;
            $contents['ErrCode'] = $contents['Header']['Falures'][0]['Code'];
            $contents['ErrMsg'] = ErrCode::getErrMsg($contents['Header']['Falures'][0]['Code']);
        } else {
            $contents['Success'] = true;
            $contents['ErrCode'] = 0;

        }

        return $contents;
    }

}
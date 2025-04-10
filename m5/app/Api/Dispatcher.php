<?php

namespace App\Api;


use Illuminate\Container\Container;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Illuminate\Support\Facades\Request as RequestFacade;

class Dispatcher
{
    /**
     * Illuminate container instance.
     *
     * @var \Illuminate\Container\Container
     */
    protected $container;

    /**
     * Internal request stack.
     *
     * @var array
     */
    protected $requestStack = [];


    /**
     * Request headers.
     *
     * @var array
     */
    protected $headers = [];

    /**
     * Request cookies.
     *
     * @var array
     */
    protected $cookies = [];

    /**
     * Request parameters.
     *
     * @var array
     */
    protected $parameters = [];

    /**
     * Request raw content.
     *
     * @var string
     */
    protected $content;

    /**
     * Request uploaded files.
     *
     * @var array
     */
    protected $uploads = [];


    /**
     * Indicates whether the returned response is the raw response object.
     *
     * @var bool
     */
    protected $raw = false;


    /**
     * Default format.
     *
     * @var string
     */
    protected $defaultFormat;

    /**
     * Create a new dispatcher instance.
     */
    public function __construct()
    {
        $this->container = Container::getInstance();
        $this->setupRequestStack();
    }

    /**
     *
     */
    protected function setupRequestStack()
    {
        $this->requestStack[] = $this->container['request'];
    }


    /**
     * @param $key
     * @param $value
     * @return $this
     */
    public function header($key, $value)
    {
        $this->headers[$key] = $value;

        return $this;
    }

    /**
     * @param array $cookie
     * @return $this
     */
    public function cookie(array $cookie)
    {
        $this->cookies = $cookie;

        return $this;
    }

    /**
     * @param Array $key, $uploads
     * @return $this
     */
    public function Uploads($key, $uploads)
    {
        $this->uploads[$key] = $uploads;
        return $this;
    }

    /**
     * @param $uri
     * @param array $parameters
     * @return mixed
     */
    public function get($uri, $parameters = [])
    {
        return $this->queueRequest('get', $uri, '', $parameters);
    }

    /**
     * @param $uri
     * @param array $parameters
     * @param string $content
     * @return mixed
     */
    public function post($uri, $content = '', $parameters = [])
    {
        return $this->queueRequest('post', $uri, $content, $parameters);
    }

    /**
     * @param $verb
     * @param $uri
     * @param $parameters
     * @param string $content
     * @return mixed
     */
    protected function queueRequest($verb, $uri, $content = '', $parameters = [])
    {
        if (!empty($content)) {
            $this->content = $content;
        }

        $this->requestStack[] = $request = $this->createRequest($verb, $uri, $parameters);

        //清空update
        $this->uploads  = [];

        return $this->dispatch($request);
    }

    /**
     * @param $verb
     * @param $uri
     * @param $parameters
     * @return Request
     */
    protected function createRequest($verb, $uri, $parameters)
    {
        $parameters = array_merge($this->parameters, (array)$parameters);

        $rootUrl = $this->getRootRequest()->root();
        if ((!parse_url($uri, PHP_URL_SCHEME)) && parse_url($rootUrl) !== false) {
            $uri = rtrim($rootUrl, '/') . '/' . ltrim($uri, '/');
        }

        $request = Request::create(
            $uri,
            $verb,
            $parameters,
            $this->cookies,
            $this->uploads,
            $this->container['request']->server->all(),
            $this->content
        );
        foreach ($this->headers as $header => $value) {
            $request->headers->set($header, $value, true);
        }

        return $request;
    }


    /**
     * @param Request $request
     * @return mixed
     */
    protected function dispatch(Request $request)
    {
        $this->clearCachedFacadeInstance();
        try {
            $this->container->instance('request', $request);
            $response = $this->container->dispatch($request);
        } catch (HttpException $exception) {
            $this->refreshRequestStack();
            throw $exception;
        }

        $this->refreshRequestStack();

        return $response;
    }

    /**
     *
     */
    protected function refreshRequestStack()
    {
        $this->replaceRequestInstance();

        $this->clearCachedFacadeInstance();

        $this->raw = false;
    }

    /**
     * Replace the request instance with the previous request instance.
     *
     * @return void
     */
    protected function replaceRequestInstance()
    {
        array_pop($this->requestStack);

        $this->container->instance('request', end($this->requestStack));
    }

    /**
     * Clear the cached facade instance.
     *
     * @return void
     */
    protected function clearCachedFacadeInstance()
    {
        RequestFacade::clearResolvedInstance('request');
    }

    /**
     * Get the root request instance.
     *
     * @return \Illuminate\Http\Request
     */
    protected function getRootRequest()
    {
        return reset($this->requestStack);
    }

}

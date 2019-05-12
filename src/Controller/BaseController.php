<?php

namespace App\Controller;

use Slim\Container;
use Slim\Http\Request;
use Slim\Http\Response;
use Spatie\ArrayToXml\ArrayToXml;

abstract class BaseController
{
    /**
     * @var Container
     */
    protected $container;

    /**
     * @var Request $request
     */
    protected $request;

    /**
     * @var Response $response
     */
    protected $response;

    /**
     * @var array
     */
    protected $args;

    protected function setParams(Request $request, Response $response, array $args)
    {
        $this->request = $request;
        $this->response = $response;
        $this->args = $args;
    }

    /**
     * @param string $status
     * @param mixed $message
     * @param int $code
     * @return Response
     */
    protected function response(string $status, $message, int $code): Response
    {
        $result = [
            'code' => $code,
            'status' => $status,
            'message' => $message,
        ];

        $acceptHeader = $this->request->getHeader('Accept');
        $this->container->get('logger')->info("response $result: " . json_encode($result));
        if (preg_match("/(javascript|json)$/", $acceptHeader[0])) {
            return $this->response->withJson($result, $code, JSON_PRETTY_PRINT);
        } else {
            $resArr = [];
            foreach ($message as $key => $value) {
                if (preg_match("/^\d*$/", $key)) {
                    $resArr['item'][] = $value;
                } else {
                    $resArr[$key] = $value;
                }
            }
            $result = ArrayToXml::convert($resArr);

            $xmlResponse = $this->response
                ->withStatus($code)
                ->withHeader('Content-Type', 'text/xml');
            $xmlResponse->getBody()->write($result);
            return $xmlResponse;
        }
    }

    /**
     * @return array
     */
    protected function getInput()
    {
        $body = $this->request->getParsedBody();
        $this->container->get('logger')->info("getInput body: ".json_encode($body));

        return $body;
    }

    protected function getRedisClient(): \Predis\Client
    {
        return $this->container->get('redis');
    }

    protected function useRedis(): bool
    {
        return $this->container->get('settings')['useRedisCache'];
    }

    /**
     * @param int $id
     * @return mixed
     */
    protected function getFromCache(int $id)
    {
        $redis = $this->getRedisClient();
        $key = $this::KEY . $id;
        $value = $redis->get($key);

        return json_decode($value);
    }

    /**
     * @param int $id
     * @param mixed $result
     */
    protected function saveInCache(int $id, $result)
    {
        $redis = $this->getRedisClient();
        $key = $this::KEY . $id;
        $redis->set($key, json_encode($result));
    }

    /**
     * @param int $id
     */
    protected function deleteFromCache(int $id)
    {
        $redis = $this->getRedisClient();
        $key = $this::KEY . $id;
        $redis->del($key);
    }
}

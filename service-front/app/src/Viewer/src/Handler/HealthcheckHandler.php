<?php

declare(strict_types=1);

namespace Viewer\Handler;

use Psr\Http\Server\RequestHandlerInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Zend\Diactoros\Response\JsonResponse;
use Http\Client\HttpClient;
use Zend\Diactoros\Request;

/**
 * Class HealthcheckHandler
 * @package Viewer\Handler
 */
class HealthcheckHandler implements RequestHandlerInterface
{
    protected $httpClient;

    public function __construct(HttpClient $http)
    {
        $this->httpClient = $http;
    }

    /**
     * @param ServerRequestInterface $request
     * @return ResponseInterface
     */
    public function handle(ServerRequestInterface $request) : ResponseInterface
    {
        return new JsonResponse([
            "healthy" => $this->isHealthy(),
            "version" => getenv("CONTAINER_VERSION") ? getenv("CONTAINER_VERSION") : "dev",
            "dependencies" => [
                "api" => $this->checkApiEndpoint()
            ]
        ]);
    }

    protected function isHealthy() : bool
    {
        return true;
    }

    protected function checkApiEndpoint() : array
    {
        $apiRequest = new Request("http://api-web/healthcheck");

        $response = $this->httpClient->sendRequest($apiRequest);

        return json_decode($response->getBody()->getContents(), true);
    }
}

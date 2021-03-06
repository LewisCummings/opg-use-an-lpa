<?php

namespace App\Middleware;

use App\Exception\AbstractApiException;
use Psr\Http\Server\RequestHandlerInterface as DelegateInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Zend\Diactoros\Response\JsonResponse;

/**
 * Class ProblemDetailsMiddleware
 * @package App\Middleware
 */
class ProblemDetailsMiddleware implements MiddlewareInterface
{
    /**
     * @param ServerRequestInterface $request
     * @param DelegateInterface $delegate
     * @return ResponseInterface|JsonResponse
     */
    public function process(ServerRequestInterface $request, DelegateInterface $delegate) : ResponseInterface
    {
        try {
            $response = $delegate->handle($request);

            return $response;
        } catch (AbstractApiException $ex) {
            //  Translate this exception type into response JSON
            $problem = [
                'title'   => $ex->getTitle(),
                'details' => $ex->getMessage(),
                'data'    => $ex->getAdditionalData(),
            ];

            return new JsonResponse($problem, $ex->getCode(), [
                'Content-Type' => 'application/problem+json',
            ]);
        }
    }
}

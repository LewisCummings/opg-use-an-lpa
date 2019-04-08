<?php

declare(strict_types=1);

namespace App;

use Aws;
use Zend;

/**
 * The configuration provider for the App module
 *
 * @see https://docs.zendframework.com/zend-component-installer/
 */
class ConfigProvider
{
    /**
     * Returns the configuration array
     *
     * To add a bit of a structure, each section is defined in a separate
     * method which returns an array with its configuration.
     *
     */
    public function __invoke() : array
    {
        return [
            'dependencies' => $this->getDependencies(),
            'templates'    => $this->getTemplates(),
        ];
    }

    /**
     * Returns the container dependencies
     */
    public function getDependencies() : array
    {
        return [
            'aliases' => [
                Zend\Expressive\Session\SessionPersistenceInterface::class => Service\Session\EncryptedCookie::class,
            ],
            'invokables' => [
                // Handlers
                Handler\PingHandler::class => Handler\PingHandler::class,
            ],

            'factories'  => [
                // Handlers
                Handler\HomePageHandler::class => Handler\HomePageHandlerFactory::class,
                Handler\EnterCodeHandler::class => Handler\EnterCodeHandlerFactory::class,

                // Services
                Aws\Sdk::class => Service\Aws\SdkFactory::class,
                Aws\SecretsManager\SecretsManagerClient::class => Service\Aws\SecretsManagerFactory::class,

            ] + $this->getConfigDependencies(),

            'autowires' => [

                // The below don't seem to be needed; seemingly the default is to attempt autowiring.

                // Middleware
                #Middleware\Session\General::class,

                // Services
                #Service\Session\Cookie::class,
                #Service\Session\KeyManager\Manager::class,
            ],

            'delegators' => [
                Zend\Stratigility\Middleware\ErrorHandler::class => [
                    Service\Log\LogStderrListenerDelegatorFactory::class,
                ],
            ],
        ];
    }

    public function getConfigDependencies() : array
    {
        // Add all Config classes here. All should point to the same factory, ConfigFactory::class
        return [
            Service\Session\KeyManager\Config::class => ConfigFactory::class,
        ];
    }

    /**
     * Returns the templates configuration
     */
    public function getTemplates() : array
    {
        return [
            'paths' => [
                'app'    => [__DIR__ . '/../templates/app'],
                'error'  => [__DIR__ . '/../templates/error'],
                'layout' => [__DIR__ . '/../templates/layout'],
            ],
        ];
    }
}

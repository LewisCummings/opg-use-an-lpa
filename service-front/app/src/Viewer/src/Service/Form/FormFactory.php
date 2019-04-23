<?php

declare(strict_types=1);

namespace Viewer\Service\Form;

use Psr\Container\ContainerInterface;
use Symfony\Component\Form\Extension\Validator\ValidatorExtension;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\Forms;
use Symfony\Component\Validator\Validation;
use Viewer\Form\ShareCode;

/**
 * Class FormFactory
 *
 * Factory to create a ready to use FormFactory setup with the various forms that the
 * application uses.
 *
 * @package Viewer\Service\Form
 */
class FormFactory
{
    public function __invoke(ContainerInterface $container) : FormFactoryInterface
    {
        // Set up the Validator component
        $validator = Validation::createValidator();

        $ffb = Forms::createFormFactoryBuilder();
        $ffb->addExtension(new ValidatorExtension($validator));
        //$ffb->addExtension(new PHP7RequestHandler());

        $ffb->addType(new ShareCode());

        return $ffb->getFormFactory();
    }
}
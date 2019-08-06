<?php declare(strict_types=1);

namespace App\Recipes\Mail;

use Wf\DockerWorkflowBundle\Recipes\BaseRecipe;
use Symfony\Component\Config\Definition\Builder\NodeDefinition;

/**
 * Class Recipe
 *
 * E-mail sender.
 */
class MailRecipe extends BaseRecipe
{
    const NAME = 'mail';

    public function getName(): string
    {
        return static::NAME;
    }

    public function getConfig(): NodeDefinition
    {
        $rootNode = parent::getConfig();

        $rootNode
            ->info('<comment>SMTP e-mail sender.</comment>')
        ;

        return $rootNode;
    }
}

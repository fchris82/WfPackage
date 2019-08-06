<?php declare(strict_types=1);
/**
 * Created by IntelliJ IDEA.
 * User: chris
 * Date: 2018.03.14.
 * Time: 21:55
 */

namespace App\Wf\BaseSystemRecipesBundle\SystemRecipes\Base;

use Wf\DockerWorkflowBundle\Recipes\SystemRecipe;

/**
 * Class Recipe
 *
 * The BASE.
 */
class BaseRecipe extends SystemRecipe
{
    const NAME = 'base';

    public function getName(): string
    {
        return static::NAME;
    }
}

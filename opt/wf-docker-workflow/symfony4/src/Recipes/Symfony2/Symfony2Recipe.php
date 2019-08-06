<?php declare(strict_types=1);
/**
 * Created by IntelliJ IDEA.
 * User: chris
 * Date: 2018.03.14.
 * Time: 22:24
 */

namespace App\Recipes\Symfony2;

use App\Recipes\Symfony\AbstractSymfonyRecipe;

/**
 * Class Recipe
 *
 * Symfony 2 friendly environment
 */
class Symfony2Recipe extends AbstractSymfonyRecipe
{
    const NAME = 'symfony2';
    const SF_CONSOLE_COMMAND = 'app/console';
    const SF_BIN_DIR = 'bin';
    const DEFAULT_VERSION = 'php7.1';

    public static function getSkeletonParents(): array
    {
        return [AbstractSymfonyRecipe::class];
    }
}

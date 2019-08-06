<?php declare(strict_types=1);
/**
 * Created by IntelliJ IDEA.
 * User: chris
 * Date: 2018.03.14.
 * Time: 22:24
 */

namespace App\Recipes\Symfony3;

use App\Recipes\Symfony\AbstractSymfonyRecipe;

/**
 * Class Recipe
 *
 * Symfony 3 friendly environment
 */
class Symfony3Recipe extends AbstractSymfonyRecipe
{
    const NAME = 'symfony3';
    const SF_CONSOLE_COMMAND = 'bin/console';
    const SF_BIN_DIR = 'vendor/bin';
    const DEFAULT_VERSION = 'php7.1';

    public static function getSkeletonParents(): array
    {
        return [AbstractSymfonyRecipe::class];
    }
}

<?php declare(strict_types=1);
/**
 * Created by IntelliJ IDEA.
 * User: chris
 * Date: 2019.02.21.
 * Time: 14:19
 */

namespace App\Wf\ConfigEditorBundle\DefinitionDumper;

use Symfony\Component\Config\Definition\ArrayNode;
use Symfony\Component\Config\Definition\BaseNode;
use Symfony\Component\Config\Definition\ConfigurationInterface;
use Symfony\Component\Config\Definition\Dumper\YamlReferenceDumper;
use Symfony\Component\Config\Definition\NodeInterface;
use Symfony\Component\Config\Definition\PrototypedArrayNode;
use Symfony\Component\Config\Definition\ScalarNode;
use Symfony\Component\Yaml\Yaml;

class ArrayDumper
{
    /**
     * @var YamlReferenceDumper
     */
    protected $yamlReferenceDumper;

    public function __construct()
    {
        $this->yamlReferenceDumper = new YamlReferenceDumper();
    }

    public function dump(ConfigurationInterface $configuration): array
    {
        return $this->dumpNode($configuration->getConfigTreeBuilder()->buildTree());
    }

    public function dumpNode(NodeInterface $node): array
    {
        // Sometimes the $this->yamlReferenceDumper->dumpNode() command gets a Notice.
        error_reporting(E_WARNING);
        $base = [
            'name' => $node->getName(),
            'required' => $node->isRequired(),
            'info' => $node instanceof BaseNode ? $node->getInfo() : null,
            'example' => $node instanceof BaseNode ? $node->getExample() : null,
            'yaml_example' => $node instanceof BaseNode ? Yaml::dump($node->getExample()) : null,
            'path' => $node->getPath(),
            'reference' => $this->yamlReferenceDumper->dumpNode($node),
        ];
        $children = null;
        if ($node instanceof ArrayNode) {
            $children = $node->getChildren();

            if ($node instanceof PrototypedArrayNode) {
                $children = $this->getPrototypeChildren($node);
                $base['prototype'] = array_keys($children);
            }
        }

        if ($children) {
            $value = [];
            /** @var NodeInterface $childNode */
            foreach ($children as $name => $childNode) {
                $value[$name] = $this->dumpNode($childNode);
            }

            $base['children'] = $value;

            return $base;
        }

        $base['default'] = $node->hasDefaultValue()
            ? $node->getDefaultValue()
            : ($node instanceof ArrayNode ? '~' : '');

        return $base;
    }

    private function getPrototypeChildren(PrototypedArrayNode $node): array
    {
        $prototype = $node->getPrototype();
        $key = $node->getKeyAttribute();

        // Do not expand prototype if it isn't an array node nor uses attribute as key
        if (!$key && !$prototype instanceof ArrayNode) {
            return $node->getChildren();
        }

        if ($prototype instanceof ArrayNode) {
            $keyNode = new ArrayNode($key, $node);
            $children = $prototype->getChildren();

            if ($prototype instanceof PrototypedArrayNode && $prototype->getKeyAttribute()) {
                $children = $this->getPrototypeChildren($prototype);
            }

            // add children
            foreach ($children as $childNode) {
                $keyNode->addChild($childNode);
            }
        } else {
            $keyNode = new ScalarNode($key, $node);
        }

        $info = 'Prototype';
        if (null !== $prototype->getInfo()) {
            $info .= ': ' . $prototype->getInfo();
        }
        $keyNode->setInfo($info);

        return [sprintf('*%s', $key) => $keyNode];
    }
}

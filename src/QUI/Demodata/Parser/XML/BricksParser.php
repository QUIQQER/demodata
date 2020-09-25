<?php

namespace QUI\Demodata\Parser\XML;

use QUI\Utils\Text\XML;

/**
 * Class BricksParser
 */
class BricksParser
{
    /**
     * Parses the bricks from the given XML file
     *
     * @param $filePath
     *
     * @return array
     */
    public static function parseBricks($filePath)
    {
        $bricks     = [];
        $DOM        = XML::getDomFromXml($filePath);
        $brickLists = $DOM->getElementsByTagName('bricks');
        $brickList  = [];

        /** @var \DOMNode $brickList */
        foreach ($brickLists as $childNode) {
            if ($childNode->parentNode->nodeName !== 'data') {
                continue;
            }

            $brickList = $childNode;
        }

        /** @var \DOMNode $brickNode */
        foreach ($brickList->childNodes as $brickNode) {
            if ($brickNode->nodeName !== 'brick') {
                continue;
            }

            $brickAttributes = [];

            /** @var \DOMNode $childNode */
            foreach ($brickNode->childNodes as $childNode) {
                if ($childNode->nodeName !== 'attributes') {
                    continue;
                }

                /** @var \DOMNode $attributesNode */
                foreach ($childNode->childNodes as $attributesNode) {
                    if ($attributesNode->nodeName !== 'attribute') {
                        continue;
                    }

                    $name  = \trim($attributesNode->attributes->getNamedItem('name')->nodeValue);
                    $value = \trim($attributesNode->nodeValue);

                    if ($name === 'settings') {
                        $brickAttributes['settings'] = \json_decode($value, true);
                        continue;
                    }

                    $brickAttributes['attributes'][$name] = $value;
                }
            }

            $identifier          = $brickNode->attributes->getNamedItem('identifier')->nodeValue;
            $bricks[$identifier] = $brickAttributes;
        }

        return $bricks;
    }
}

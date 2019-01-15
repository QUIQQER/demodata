<?php

namespace QUI\Demodata\Parser\XML;

use QUI\Utils\Text\XML;

class BricksParser
{

    public static function parseBricks($filePath)
    {
        $bricks = [];
        $DOM = XML::getDomFromXml($filePath);
        $DOM->getElementsByTagName('bricks');
        return $bricks;
    }
}
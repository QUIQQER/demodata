<?php

namespace QUI\Demodata\Parser;

use QUI\Demodata\Exceptions\UnknownFileFormatException;
use QUI\Demodata\Parser\XML\BricksParser;
use QUI\Demodata\Parser\XML\ProjectParser;

class DemoDataParser
{
    /**
     * Attempts to parse the given file. It will select the appropriate parser by file extension.
     * **Returnformat:**
     * ```
     * [
     *  'bricks' => array,
     *  'projects' => array,
     * ]
     * ```
     * @param $filePath
     *
     * @return array
     * @throws UnknownFileFormatException
     */
    public function parse($filePath)
    {
        $fileExtension = pathinfo($filePath, PATHINFO_EXTENSION);

        switch ($fileExtension) {
            case 'xml':
                return $this->parseXML($filePath);
                break;
            default:
                throw new UnknownFileFormatException([
                    'quiqqer/demodata',
                    'exception.file.format.extension.unknown'
                ]);
        }

    }

    /**
     * Attempts to parse the given XML file. 
     * **Returnformat:**
     * ```
     * [
     *  'bricks' => array,
     *  'projects' => array,
     * ]
     * ```
     * @param $filePath
     *
     * @return array
     */
    protected function parseXML($filePath)
    {
        $data = [];

        $data['projects'] = ProjectParser::parseProjects($filePath);
        $data['bricks']   = BricksParser::parseBricks($filePath);

        return $data;
    }

}
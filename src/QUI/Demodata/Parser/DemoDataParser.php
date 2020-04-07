<?php

namespace QUI\Demodata\Parser;

use QUI\Demodata\Exceptions\UnknownFileFormatException;
use QUI\Demodata\Parser\XML\BricksParser;
use QUI\Demodata\Parser\XML\MediaParser;
use QUI\Demodata\Parser\XML\ProjectParser;
use QUI\Package\Package;

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
     *
     * @param Package $TemplatePackage
     *
     * @return array
     * @throws UnknownFileFormatException
     */
    public function parse(Package $TemplatePackage)
    {
        $demoDataFilePath = $TemplatePackage->getDir().'demodata.xml';
        $fileExtension    = pathinfo($demoDataFilePath, PATHINFO_EXTENSION);

        switch ($fileExtension) {
            case 'xml':
                return $this->parseXML($TemplatePackage);
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
     *
     * @param Package $TemplatePackage
     *
     * @return array
     */
    protected function parseXML(Package $TemplatePackage)
    {
        $filePath     = $TemplatePackage->getDir().'demodata.xml';
        $data         = [];
        $data['meta'] = [
            'file'     => $filePath,
            'template' => [
                'name' => $TemplatePackage->getName(),
                'path' => $TemplatePackage->getDir()
            ]
        ];

        $data['projects'] = ProjectParser::parseProjects($filePath);
        $data['bricks']   = BricksParser::parseBricks($filePath);

        return $data;
    }

}
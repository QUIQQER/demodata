<?php

namespace QUI\Demodata\Parser;

use QUI\Demodata\Exceptions\UnknownFileFormatException;
use QUI\Demodata\Parser\XML\BricksParser;
use QUI\Demodata\Parser\XML\ProjectParser;
use QUI\Demodata\Parser\XML\EventParser;
use QUI\Events\Event;
use QUI\Package\Package;
use QUI\Utils\Project;

/**
 * Class DemoDataParser
 * @package QUI\Demodata\Parser
 */
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
     * @param \QUI\Projects\Project $Project
     *
     * @return array
     * @throws UnknownFileFormatException
     */
    public function parse(Package $TemplatePackage, \QUI\Projects\Project $Project)
    {
        $demoDataFilePath = $TemplatePackage->getDir().'demodata.xml';
        $fileExtension    = \pathinfo($demoDataFilePath, PATHINFO_EXTENSION);

        switch ($fileExtension) {
            case 'xml':
                return $this->parseXML($TemplatePackage, $Project);

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
    protected function parseXML(Package $TemplatePackage, \QUI\Projects\Project $Project)
    {
        $filePath     = $TemplatePackage->getDir().'demodata.xml';
        $langFilePath = $TemplatePackage->getDir().'demodata_'.$Project->getLang().'.xml';
        $data         = [];

        if (file_exists($langFilePath)) {
            $filePath = $langFilePath;
        }

        $data['meta'] = [
            'file'     => $filePath,
            'template' => [
                'name' => $TemplatePackage->getName(),
                'path' => $TemplatePackage->getDir()
            ]
        ];

        $data['projects'] = ProjectParser::parseProjects($filePath);
        $data['bricks']   = BricksParser::parseBricks($filePath);
        $data['events']   = EventParser::parseEvents($filePath);

        return $data;
    }
}

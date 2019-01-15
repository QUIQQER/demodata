<?php

namespace QUI\Demodata\Parser\XML;



use QUI\Utils\Text\XML;

class ProjectParser
{
    /**
     * Parses the project nodes in the given XML file
     * @param $filePath - Absolute file path to the XML file.
     * 
     * @return array
     */
    public static function parseProjects($filePath)
    {
        $projects = [];

        $DOM          = XML::getDomFromXml($filePath);
        $projectNodes = $DOM->getElementsByTagName('project');
        foreach ($projectNodes as $projectNode) {
            $project = [];
            /** @var \DOMNode $childNode */
            foreach ($projectNode->childNodes as $childNode) {
                if ($childNode->nodeName === 'settings') {
                    $project['settings'] = self::parseSettings($childNode);
                }

                if ($childNode->nodeName === 'sites') {
                    foreach ($childNode->childNodes as $siteNode) {
                        if ($siteNode->nodeName !== 'site') {
                            continue;
                        }
                        $project['sites'][] = self::parseSite($siteNode);
                    }
                }
            }
            $projects[] = $project;
        }

        return $projects;
    }

    /**
     * Parses the settings from a `settings` node.
     * Returns an associative array with the settings name as key and the settings value as value.
     * **Returnformat**:
     * ```
     * [
     *  'setting1' => 'value',
     *  'setting2' => 'value',
     * ]
     * ```
     * @param \DOMNode $SettingsNode
     *
     * @return array
     */
    protected static function parseSettings(\DOMNode $SettingsNode)
    {
        $settings = [];

        /** @var \DOMNode $SettingNode */
        foreach ($SettingsNode->childNodes as $SettingNode) {
            if ($SettingNode->nodeName !== 'setting') {
                continue;
            }

            $settingName            = $SettingNode->attributes->getNamedItem('name')->nodeValue;
            $settingValue           = $SettingNode->attributes->getNamedItem('value')->nodeValue;
            $settings[$settingName] = $settingValue;
        }

        return $settings;
    }

    /**
     * Parses the given site node.
     * Returns an associative array.
     * **Returnformat**
     * ```
     * [
     *  'attributes' => [
     *     'attribute1' => 'value',
     *     'attribute2' => 'value',
     *   ],
     *   'children' => [
     *     0 => <site-array>,
     *     1 => <site-array>
     *   ]
     * ]
     * ```
     * @param \DOMNode $SiteNode
     *
     * @return array
     */
    protected static function parseSite(\DOMNode $SiteNode)
    {
        $site = [];

        // Parse attributes
        /** @var \DOMNode $childNode */
        foreach ($SiteNode->childNodes as $childNode) {
            // Parse Attributes
            if ($childNode->nodeName === 'attributes') {
                /** @var \DOMNode $AttributeNode */
                foreach ($childNode->childNodes as $AttributeNode) {
                    if ($AttributeNode->nodeName !== 'attribute') {
                        continue;
                    }
                    $site['attributes'][$AttributeNode->attributes->getNamedItem('name')->nodeValue] = trim($AttributeNode->textContent);
                }
            }

            // Parse Children
            if ($childNode->nodeName === 'children') {
                foreach ($childNode->childNodes as $ChildSiteNode) {
                    if ($ChildSiteNode->nodeName !== 'site') {
                        continue;
                    }
                    $site['children'][] = self::parseSite($ChildSiteNode);
                }
            }
        }

        return $site;
    }
}
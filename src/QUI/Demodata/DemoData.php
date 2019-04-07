<?php

namespace QUI\Demodata;

use QUI\Bricks\Brick;
use QUI\Bricks\Manager;
use QUI\Projects\Project;
use QUI\Projects\Site;

class DemoData
{

    protected $identifiers = [
        'sites'  => [],
        'bricks' => []
    ];

    /** @var Project */
    protected $Project;

    /**
     * Applies the given demodata to the project
     *
     * @param Project $Project
     * @param $demoDataArray
     *
     * @throws \QUI\Exception
     */
    public function apply(Project $Project, $demoDataArray)
    {
        if (isset($demoDataArray['bricks'])) {
            $this->identifiers['bricks'] = $this->addBricks($Project, $demoDataArray['bricks']);
        }

        // Set the project settings and create its children sites
        if (isset($demoDataArray['projects'])) {
            $this->Project = $this->configureProject($Project, $demoDataArray['projects'][0]);
        }

        $this->replaceBrickSettingPlaceholders();
    }

    /**
     * Sets the given project settings and creates the defined sites
     *
     * @param Project $Project
     * @param $projectData
     *
     * @return Project
     * @throws \QUI\Exception
     */
    protected function configureProject(Project $Project, $projectData)
    {
        $sites    = $projectData['sites'];
        $settings = $projectData['settings'];

        // Set the settings
        $ProjectsConfig = \QUI::getConfig('etc/projects.ini.php');
        foreach ($settings as $key => $value) {
            $ProjectsConfig->set($Project->getName(), $key, $value);
        }
        $ProjectsConfig->save();

        if (count($sites) > 0) {
            $identifier                              = array_keys($sites)[0];
            $this->identifiers['sites'][$identifier] = $this->configureStartpage($Project, reset($sites));
        }

        $this->Project = $Project;

        return $Project;
    }

    /**
     * Adds the defined bricks to QUIQQER
     *
     * @param Project $Project
     * @param $bricksData
     *
     * @return array
     * @throws \QUI\Exception
     */
    protected function addBricks(Project $Project, $bricksData)
    {
        $BrickManager  = Manager::init();
        $createdBricks = [];
        foreach ($bricksData as $brickIdentifier => $brickParams) {
            $Brick   = new Brick($brickParams['attributes']);
            $brickID = $BrickManager->createBrickForProject($Project, $Brick);

            // Set the attributes of the brick
            foreach ($brickParams['attributes'] as $key => $value) {
                if ($key === 'settings') {
                    continue;
                }

                $Brick->setAttribute($key, $value);
            }

            // Set the bricks settings
            $Brick->setSettings($brickParams['settings']);

            $brickParams['customfields'] = json_decode($brickParams['attributes']['customfields'], true);
            $BrickManager->saveBrick($brickID, $brickParams);
            $createdBricks[$brickIdentifier] = $brickID;
        }

        return $createdBricks;
    }

    /**
     * Creates a site and its children
     *
     * @param Site $Parent
     * @param $siteData
     *
     *
     * @return int - Returns the created sites ID
     * @throws \QUI\Exception
     */
    protected function createSite(Site $Parent, $siteData)
    {
        $EditSite  = $Parent->getEdit();
        $newSiteID = $EditSite->createChild(
            $siteData['attributes'],
            [],
            \QUI::getUsers()->getSystemUser()
        );

        $NewSite = new Site\Edit($Parent->getProject(), $newSiteID);
        $NewSite->activate(\QUI::getUsers()->getSystemUser());

        // Create children
        if (isset($siteData['children']) && !empty($siteData['children'])) {
            foreach ($siteData['children'] as $identifier => $childData) {
                $this->identifiers['sites'][$identifier] = $this->createSite($NewSite, $childData);
            }
        }

        // Add bricks to the site
        if (isset($siteData['bricks']) && !empty($siteData['bricks'])) {
            $this->addBrickToSite($NewSite, $siteData['bricks']);
        }

        return $newSiteID;
    }

    /**
     * Creates and configures the start page
     *
     * @param Project $Project
     * @param $siteData
     *
     * @return bool|int
     * @throws \QUI\Exception
     */
    protected function configureStartpage(Project $Project, $siteData)
    {
        $Site = $Project->firstChild();
        $Site = $Site->getEdit();

        // Create children
        if (isset($siteData['children']) && !empty($siteData['children'])) {
            foreach ($siteData['children'] as $identifier => $childData) {
                $this->identifiers['sites'][$identifier] = $this->createSite($Project->firstChild(), $childData);
            }
        }

        if (isset($siteData['attributes'])) {
            foreach ($siteData['attributes'] as $attribute => $value) {
                $Site->setAttribute($attribute, $value);
            }
            $Site->save(\QUI::getUsers()->getSystemUser());
        }

        // Add bricks to the site
        if (isset($siteData['bricks']) && !empty($siteData['bricks'])) {
            $this->addBrickToSite($Site, $siteData['bricks']);
        }

        return $Site->getId();
    }

    /**
     * Adds the given bricks to the given site
     *
     * @param Site $Site
     * @param $bricksData
     */
    protected function addBrickToSite($Site, $bricksData)
    {
        $siteBricks = [];
        foreach ($bricksData as $areaName => $areaBricks) {
            foreach ($areaBricks as $brickData) {
                if (!isset($this->identifiers['bricks'][$brickData['identifier']])) {
                    continue;
                }

                $siteBricks[$areaName][] = [
                    'brickId'      => $this->identifiers['bricks'][$brickData['identifier']],
                    'customfields' => json_encode($brickData['settings']),
                    'uid'          => ''
                ];
            }
        }

        $Site->setAttribute(
            'quiqqer.bricks.areas',
            json_encode($siteBricks)
        );
        $Site->save(\QUI::getUsers()->getSystemUser());
    }

    /**
     * Replaces all placeholders within the bricks settings
     */
    protected function replaceBrickSettingPlaceholders()
    {
        /** @var Site\Edit $Site */
        foreach ($this->Project->getSites() as $Site) {
            $siteBricks = json_decode($Site->getAttribute('quiqqer.bricks.areas'), true);
            if (empty($siteBricks)) {
                continue;
            }
            $updatedSiteBricks = [];
            foreach ($siteBricks as $brickArea => $bricks) {
                foreach ($bricks as $brickData) {
                    $brickSettings     = json_decode($brickData['customfields'], true);
                    $updatedSettings = [];
                    foreach ($brickSettings as $settingName => $settingValue) {
                        $updatedSettings[] = $this->replacePlaceholder($settingValue);
                    }
                    $updatedSiteBricks[$brickArea][] = $updatedSettings;
                }
            }

            $Site->setAttribute('quiqqer.bricks.areas', json_encode($updatedSiteBricks));
            $Site->save();
        }
    }

    /**
     * Replaces all placeholders within the given string.
     *
     * @param string|array $string
     *
     * @return string
     */
    protected function replacePlaceholder($string)
    {
        $placeholderPattern = [
            '/.*\$\{(site).([a-zA-Z0-9\.-_]+)\}.*/'
        ];

        if (is_array($string)) {
            $string = json_encode($string);
        }

        foreach ($placeholderPattern as $pattern) {
            if (!preg_match($pattern, $string, $matches)) {
                continue;
            }

            if (count($matches) !== 3) {
                continue;
            }

            $type       = $matches[1];
            $identifier = $matches[2];

            switch ($type) {
                case 'site':
                    if (isset($this->identifiers['sites'][$identifier])) {
                        $string = str_replace(
                            '${site.'.$identifier.'}',
                            $this->identifiers['sites'][$identifier],
                            $string
                        );
                    }
                    break;
            }
        }

        return $string;
    }
}

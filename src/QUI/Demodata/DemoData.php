<?php

namespace QUI\Demodata;

use QUI\Bricks\Brick;
use QUI\Bricks\Manager;
use QUI\Projects\Project;
use QUI\Projects\Site;

class DemoData
{

    protected $brickIdentifiers = [];
    
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
            $this->brickIdentifiers = $this->addBricks($Project, $demoDataArray['bricks']);
        }

        if (isset($demoDataArray['projects'])) {
            $this->configureProject($Project, $demoDataArray['projects'][0]);
        }
    }

    /**
     * Sets the given project settings and creates the defined sites
     *
     * @param Project $Project
     * @param $projectData
     *
     * @throws \QUI\Exception
     */
    public function configureProject(Project $Project, $projectData)
    {
        $sites    = $projectData['sites'];
        $settings = $projectData['settings'];

        // Set the settings
        $ProjectsConfig = \QUI::getConfig('etc/projects.ini.php');
        foreach ($settings as $key => $value) {
            $ProjectsConfig->set($Project->getName(), $key, $value);
        }
        $ProjectsConfig->save();

        // Create the pages
        foreach ($sites as $siteData) {
            $this->createSite($Project->firstChild(), $siteData,$this->brickIdentifiers);
        }
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
    public function addBricks(Project $Project, $bricksData)
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


            $brickParams['customfields'] = json_decode($brickParams['attributes']['customfields'],true);
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
     * @param array $brickIdentifers
     *
     * @throws \QUI\Exception
     */
    protected function createSite(Site $Parent, $siteData, $brickIdentifers=[])
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
            foreach ($siteData['children'] as $childData) {
                $this->createSite($NewSite, $childData);
            }
        }

        // Add bricks to the site
        if (isset($siteData['bricks']) && !empty($siteData['bricks'])) {
            $siteBricks = [];
            foreach($siteData['bricks'] as $areaName => $areaBricks){
                
                foreach($areaBricks as $brickData){
                    if(!isset($this->brickIdentifiers[$brickData['identifier']])){
                        continue;
                    }
                    
                    $siteBricks[$areaName][] = [
                        'brickId' => $this->brickIdentifiers[$brickData['identifier']],
                        'customfields' => json_encode($brickData['settings']),
                        'uid' => ''
                    ];
                }
            }

            $NewSite->setAttribute(
                'quiqqer.bricks.areas',
                json_encode($siteBricks)
            );
            $NewSite->save(\QUI::getUsers()->getSystemUser());
        }

    }
}
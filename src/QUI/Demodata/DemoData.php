<?php

namespace QUI\Demodata;

use QUI\Bricks\Brick;
use QUI\Bricks\Manager;
use QUI\Projects\Project;
use QUI\Projects\Site;
use QUI\System\Log;

class DemoData
{

    protected $identifiers = [
        'sites'  => [],
        'bricks' => [],
        'media'  => []
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
        // Create media area
        if (isset($demoDataArray['projects'][0]['media']) && !empty($demoDataArray['projects'][0]['media'])) {
            $Media                      = new Media();
            $this->identifiers['media'] = $Media->createMediaArea($Project, $demoDataArray);
        }

        if (isset($demoDataArray['bricks'])) {
            $this->identifiers['bricks'] = $this->addBricks($Project, $demoDataArray['bricks']);
        }

        // Set the project settings and create its children sites
        if (isset($demoDataArray['projects'])) {
            $this->Project = $this->configureProject($Project, $demoDataArray['projects'][0]);
        }

        $this->replacePlaceholdersInBrickSettings();
        $this->replacePlaceholdersInSiteSettings();
        $this->replacePlaceholdersInProjectSettings();
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
        // Workaround to force the project manager to reload the project data
        unset(\QUI\Projects\Manager::$projects[$Project->getName()]);

        if (count($sites) > 0) {
            $identifier                              = array_keys($sites)[0];
            $this->identifiers['sites'][$identifier] = $this->configureStartpage($Project, reset($sites));
        }

        $this->Project = $Project;

        return $Project;
    }
    
    //////////////////////
    //   Sites
    //////////////////////
    #region Sites
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
        $NewSite->setAttributes($siteData['attributes']);
        $NewSite->save();

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
    #endregion
    
    //////////////////////
    //   Bricks
    //////////////////////
    #region Bricks
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
     * Adds the given bricks to the given site
     *
     * @param Site\Edit $Site
     * @param $bricksData
     *
     * @throws \QUI\Exception
     */
    protected function addBrickToSite($Site, $bricksData)
    {
        $BrickManager = Manager::init();
        $siteBricks = [];
        foreach ($bricksData as $areaName => $areaBricks) {
            foreach ($areaBricks as $brickData) {
                if (!isset($this->identifiers['bricks'][$brickData['identifier']])) {
                    continue;
                }

                $quiBrickData = [
                    'brickId'      => $this->identifiers['bricks'][$brickData['identifier']],
                    'customfields' => json_encode($brickData['settings']),
                    'uid'          => ''
                ];
                
                $brickUid = $BrickManager->createUniqueSiteBrick($Site,$quiBrickData);
                $quiBrickData['uid'] = $brickUid;
                
                $siteBricks[$areaName][] = $quiBrickData;
            }
        }

        $Site->setAttribute(
            'quiqqer.bricks.areas',
            json_encode($siteBricks)
        );
        $Site->save(\QUI::getUsers()->getSystemUser());
    }
    #endregion
    
    //////////////////////
    //   Placeholders
    //////////////////////
    #region Placeholders
    /**
     * Replaces all placeholders within the bricks settings
     */
    protected function replacePlaceholdersInBrickSettings()
    {
        /*
         * Global brick settings
         */
        $BrickManager = new Manager();
        $Bricks       = $BrickManager->getBricksFromProject($this->Project);
        /** @var Brick $Brick */
        foreach ($Bricks as $Brick) {
            $updatedSettings = [];
            foreach ($Brick->getSettings() as $settingName => $settingValue) {
                $updatedSettings[$settingName] = $this->processPlaceholders($settingValue);
            }
            $Brick->setSettings($updatedSettings);
            $BrickManager->saveBrick($Brick->getAttribute('id'), $Brick->getAttributes());
        }

        /*
         * Site specific settings
         */
        /** @var Site\Edit $Site */
        foreach ($this->Project->getSites() as $Site) {
            $siteBricks = json_decode($Site->getAttribute('quiqqer.bricks.areas'), true);
            if (empty($siteBricks)) {
                continue;
            }
            $updatedSiteBricks = [];
            foreach ($siteBricks as $brickArea => $bricks) {
                foreach ($bricks as $brickData) {
                    $brickSettings   = json_decode($brickData['customfields'], true);
                    $updatedSettings = [];
                    foreach ($brickSettings as $settingName => $settingValue) {
                        $updatedSettings[] = $this->processPlaceholders($settingValue);
                    }
                    $brickData['customfields'] = $updatedSettings;
                    $updatedSiteBricks[$brickArea][] = $brickData;
                }
            }
            
            /*
             * Brick UID Table
             */
            $brickUIDRows = \QUI::getDataBase()->fetch([
                'from' => Manager::getUIDTable(),
                'where' => [
                    'project' => $this->Project->getName(),
                    'lang' => $this->Project->getLang()
                ]
            ]);
            
            foreach($brickUIDRows as $brickUIDRow){
                $customSettings = json_decode($brickUIDRow['customfields'],true);
                foreach($customSettings as $settingName => $settingValue){
                    $customSettings[$settingName] = $this->processPlaceholders($settingValue);
                }
                
                \QUI::getDataBase()->update(
                    Manager::getUIDTable(),
                    [
                        'customfields' => json_encode($customSettings)
                    ],[
                        'uid' => $brickUIDRow['uid']
                    ]
                );
            }

            $Site->setAttribute('quiqqer.bricks.areas', $updatedSiteBricks);
            $Site->save();
        }
    }

    /**
     * Replaces all palceholders within all sites settings
     */
    protected function replacePlaceholdersInSiteSettings()
    {
        /** @var Site\Edit $Site */
        foreach($this->Project->getSites() as $Site){
            $updatedSettings = [];
            foreach($Site->getAttributes() as $attributeName => $attributeValue){
                $updatedSettings[$attributeName] = $this->processPlaceholders($attributeValue);
            }
            $Site->setAttributes($updatedSettings);
            $Site->save();
        }
    }

    /**
     * Replaces all Placeholders within the projects settings
     * @throws \QUI\Exception
     */
    protected function replacePlaceholdersInProjectSettings(){
        
        $ProjectsConfig = \QUI::getConfig('etc/projects.ini.php');
        $settings = $ProjectsConfig->getSection($this->Project->getName());
        foreach ($settings as $key => $value) {
            $value = $this->processPlaceholders($value);
            $ProjectsConfig->set($this->Project->getName(), $key, $value);
        }
        $ProjectsConfig->save();
    }
    
    /**
     * Replaces all placeholders within the given string.
     * If an array is given, the method will convert the array into a json string and return the updated json string
     *
     * @param string|array $string
     *
     * @return string
     */
    protected function processPlaceholders($string)
    {
        $placeholderPattern = [
            '/.*\$\{(site\.)([a-zA-Z0-9\.\-_]+)\}.*/',
            '/.*\$\{(site):([a-zA-Z0-9\.\-_\/]+)\}.*/',
            '/.*\$\{(media):([a-zA-Z0-9\.\-_\/\\\\]+)\}.*/',
        ];

        if (is_array($string)) {
            $string = json_encode($string);
        }

        foreach ($placeholderPattern as $pattern) {
            $matches = [];
            
            while(preg_match($pattern, $string, $matches)){
                if (count($matches) !== 3) {
                    continue;
                }

                $type       = $matches[1];
                $identifier = $matches[2];

                switch ($type) {
                    case 'site.':
                        if (!isset($this->identifiers['sites'][$identifier])) {
                            Log::addDebug('Site Identifier "'.$identifier.'" was not found');
                            break;
                        }

                        $string = str_replace(
                            '${site.'.$identifier.'}',
                            $this->identifiers['sites'][$identifier],
                            $string
                        );
                        break;
                    case 'site':
                        if (!isset($this->identifiers['sites'][$identifier])) {
                            Log::addDebug('Site Identifier "'.$identifier.'" was not found');
                            break;
                        }

                        $string = str_replace(
                            '${site:'.$identifier.'}',
                            $this->identifiers['sites'][$identifier],
                            $string
                        );
                        
                        break;
                    case 'media':
                        $identifier = trim($identifier);
                        $identifier = ltrim($identifier,'/ ');
                        if(!isset($this->identifiers['media'][$identifier])){
                            Log::addDebug('Media Identifier "'.$identifier.'" was not found');
                            break;
                        }
                        $string = str_replace(
                            '${media:'.$identifier.'}',
                            'image.php?id='.$this->identifiers['media'][$identifier].'&project='.$this->Project->getName(),
                            $string
                        );
                        break;
                }
            }
        }

        return $string;
    }
    #endregion
}

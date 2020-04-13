<?php

namespace QUI\Demodata;

use QUI\Projects\Media\Folder;
use QUI\Projects\Media\Utils;
use QUI\Projects\Project;

/**
 * Class Media
 * @package QUI\Demodata
 */
class Media
{
    /** @var array - Keeps track of all created media. Key: MediaItem ID;  Value: Mediapath within bin directory */
    protected $createdMedia = [];

    /** @var array - Contains the parsed media data from the demodata.xml */
    protected $mediaSection = [];

    /** @var array - Contains the parsed media identifier data from the demodata.xml */
    protected $identifiers = [];

    /** @var string - path to the bin/media directory within the tempaltes install directory */
    protected $templateMediaBinDirectory;

    /** @var \QUI\Projects\Media */
    protected $MediaManager;

    /**
     * @return array
     */
    public function getIdentifiers()
    {
        return $this->identifiers;
    }

    /**
     * Creates the media entities from the given directory
     *
     * @param Project $Project
     * @param array $demoDataArray
     *
     * @return array
     * @throws \QUI\Exception
     */
    public function createMediaArea(Project $Project, array $demoDataArray)
    {
        $metaSection        = $demoDataArray['meta'];
        $this->mediaSection = $demoDataArray['projects'][0]['media'];
        $templatePath       = $metaSection['template']['path'];
        $mediaBinDirectory  = $templatePath.'/bin/media/';

        $this->templateMediaBinDirectory = $mediaBinDirectory;
        $this->MediaManager              = $Project->getMedia();

        if (!is_dir($mediaBinDirectory)) {
            throw new \InvalidArgumentException(
                "The template does not have a media directory. Media files should be placed in '$mediaBinDirectory'"
            );
        }

        // Scan dir
        $this->createMediaInFolder(
            $mediaBinDirectory,
            $Project->getMedia()->get(1)
        );

        return $this->createdMedia;
    }

    /**
     * @param string $directory - The full physical path to the directory containing the source files
     * @param Folder $MediaBaseFolder - The Base folder in the QUIQQER media area, which should contain the new items
     *
     * @throws \QUI\Exception
     */
    protected function createMediaInFolder($directory, Folder $MediaBaseFolder)
    {
        foreach (scandir($directory, SCANDIR_SORT_NONE) as $entity) {
            if ($entity === '.' || $entity === '..') {
                continue;
            }

            $fullPath = $directory.DIRECTORY_SEPARATOR.$entity;

            if (is_dir($fullPath)) {
                $SubFolder = $MediaBaseFolder->createFolder($entity);
                $this->createMediaInFolder($fullPath, $SubFolder);
                continue;
            }

            // Upload file
            $pathInSourceDir = str_replace($this->templateMediaBinDirectory, '', $fullPath);
            $pathInSourceDir = trim($pathInSourceDir, " \t\n\r\0\x0B/");

            $UploadedFile = $MediaBaseFolder->uploadFile($fullPath, Folder::FILE_OVERWRITE_TRUE);

            // Sanitize the filename to make sure it does not contain any invalid characters
            if (isset($this->mediaSection[$pathInSourceDir])) {
                $UploadedFile->setAttributes([
                    'name'     => str_replace(
                        '.',
                        '_',
                        Utils::stripMediaName($this->mediaSection[$pathInSourceDir]['name'])
                    ),
                    'title'    => $this->mediaSection[$pathInSourceDir]['title'],
                    'alt'      => $this->mediaSection[$pathInSourceDir]['alt'],
                    'priority' => $this->mediaSection[$pathInSourceDir]['priority'],
                    'short'    => $this->mediaSection[$pathInSourceDir]['description'],
                ]);
            }

            $UploadedFile->save();
            $UploadedFile->activate();

            $this->createdMedia[$pathInSourceDir] = $UploadedFile->getId();

            if (isset($this->mediaSection[$pathInSourceDir]['identifier'])) {
                $ident = $this->mediaSection[$pathInSourceDir]['identifier'];

                $this->identifiers[$ident] = $UploadedFile->getId();
            }
        }
    }
}

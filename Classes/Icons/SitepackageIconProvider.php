<?php

declare(strict_types=1);

namespace Taketool\Sitepackage\Icons;

use BK2K\BootstrapPackage\Icons\IconList;
use BK2K\BootstrapPackage\Icons\IconProviderInterface;
use BK2K\BootstrapPackage\Icons\SvgIcon;
use TYPO3\CMS\Core\Utility\GeneralUtility;

final class SitepackageIconProvider implements IconProviderInterface
{
    private const DIRECTORY = 'EXT:sitepackage/Resources/Public/Icons/Set/';

    public function getIdentifier(): string
    {
        return self::DIRECTORY;
    }

    public function getName(): string
    {
        return 'Sitepackage';
    }

    public function supports(string $identifier): bool
    {
        return $identifier === self::DIRECTORY;
    }

    public function getIconList(): IconList
    {
        $icons = new IconList();
        $path = GeneralUtility::getFileAbsFileName(self::DIRECTORY);
        if ($path === '' || !is_dir($path)) {
            return $icons;
        }

        $files = iterator_to_array(new \FilesystemIterator($path, \FilesystemIterator::KEY_AS_PATHNAME));
        ksort($files);

        foreach ($files as $fileinfo) {
            if ($fileinfo instanceof \SplFileInfo
                && $fileinfo->isFile()
                && strtolower($fileinfo->getExtension()) === 'svg'
            ) {
                $src = self::DIRECTORY . $fileinfo->getFilename();
                $icons->addIcon(
                    (new SvgIcon())
                        ->setSrc($src)
                        ->setIdentifier($src)
                        ->setName($fileinfo->getBasename('.' . $fileinfo->getExtension()))
                        ->setPreviewImage($src)
                );
            }
        }

        return $icons;
    }
}

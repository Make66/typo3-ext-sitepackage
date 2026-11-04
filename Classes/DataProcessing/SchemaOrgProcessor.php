<?php

declare(strict_types=1);

namespace Taketool\Sitepackage\DataProcessing;

use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Site\Entity\SiteInterface;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer;
use TYPO3\CMS\Frontend\ContentObject\DataProcessorInterface;

/**
 * Builds the site-wide schema.org nodes (Organization, WebSite, BreadcrumbList)
 * from the site settings sitepackage.schema.* and the current rootline.
 * See Documentation/SchemaOrg.md.
 */
final class SchemaOrgProcessor implements DataProcessorInterface
{
    public function process(ContentObjectRenderer $cObj, array $contentObjectConfiguration, array $processorConfiguration, array $processedData): array
    {
        if (ExtensionManagementUtility::isLoaded('schema')) {
            return $processedData;
        }
        $request = $cObj->getRequest();
        $site = $request->getAttribute('site');
        if (!$site instanceof Site) {
            return $processedData;
        }
        $settings = $site->getSettings();
        $get = static fn(string $key, mixed $default = ''): mixed => $settings->has('sitepackage.schema.' . $key)
            ? $settings->get('sitepackage.schema.' . $key)
            : $default;

        $baseUrl = rtrim((string)$site->getBase(), '/') . '/';
        $language = $request->getAttribute('language');
        $nodes = [];

        if ((bool)$get('enable', false) && trim((string)$get('name')) !== '') {
            $sameAs = array_values(array_filter(array_map('trim', explode("\n", (string)$get('sameAs')))));
            $nodes[] = [
                '@type' => (string)$get('type', 'Organization'),
                '@id' => $baseUrl . '#organization',
                'name' => $get('name'),
                'url' => $baseUrl,
                'logo' => $this->absoluteUrl((string)$get('logo'), $baseUrl),
                'telephone' => $get('telephone'),
                'email' => $get('email'),
                'address' => [
                    '@type' => 'PostalAddress',
                    'streetAddress' => $get('street'),
                    'postalCode' => $get('postalCode'),
                    'addressLocality' => $get('city'),
                    'addressCountry' => $get('country', 'DE'),
                ],
                'sameAs' => $sameAs,
            ];
            $nodes[] = [
                '@type' => 'WebSite',
                '@id' => $baseUrl . '#website',
                'url' => $baseUrl,
                'name' => $get('name'),
                'inLanguage' => $language?->getLocale()->getLanguageCode(),
                'publisher' => ['@id' => $baseUrl . '#organization'],
            ];
        }

        if ((bool)$get('breadcrumb', true)) {
            $breadcrumb = $this->breadcrumb($request, $site, $language);
            if (count($breadcrumb) > 1) {
                $nodes[] = ['@type' => 'BreadcrumbList', 'itemListElement' => $breadcrumb];
            }
        }

        $processedData['schemaOrgNodes'] = $nodes;
        return $processedData;
    }

    private function breadcrumb(object $request, SiteInterface $site, mixed $language): array
    {
        $pageInformation = $request->getAttribute('frontend.page.information');
        if ($pageInformation === null) {
            return [];
        }
        $items = [];
        $rootLine = $pageInformation->getRootLine();
        ksort($rootLine);
        $started = false;
        foreach ($rootLine as $page) {
            $started = $started || (int)($page['uid'] ?? 0) === $site->getRootPageId();
            if (!$started || (int)($page['doktype'] ?? 0) >= 199) {
                continue;
            }
            $title = trim((string)($page['nav_title'] ?? '')) !== '' ? $page['nav_title'] : ($page['title'] ?? '');
            $url = (string)$site->getRouter()->generateUri((int)$page['uid'], ['_language' => $language]);
            $items[] = ['page' => (int)$page['uid'], 'name' => $title, 'item' => $url];
        }
        $list = [];
        foreach ($items as $position => $item) {
            $list[] = [
                '@type' => 'ListItem',
                'position' => $position + 1,
                'name' => $item['name'],
                'item' => $item['item'],
            ];
        }
        return $list;
    }

    private function absoluteUrl(string $path, string $baseUrl): string
    {
        $path = trim($path);
        if ($path === '' || preg_match('#^https?://#i', $path)) {
            return $path;
        }
        return $baseUrl . ltrim($path, '/');
    }
}

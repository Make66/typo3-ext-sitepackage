<?php

declare(strict_types=1);

namespace Taketool\Sitepackage\ViewHelpers\SchemaOrg;

use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractViewHelper;

/**
 * Renders an array as a schema.org JSON-LD script tag. See Documentation/SchemaOrg.md.
 */
final class JsonLdViewHelper extends AbstractViewHelper
{
    protected $escapeOutput = false;

    public function initializeArguments(): void
    {
        $this->registerArgument('data', 'array', 'Schema.org node(s); @context is added automatically', true);
    }

    public function render(): string
    {
        if (ExtensionManagementUtility::isLoaded('schema')) {
            return '';
        }
        $data = self::clean($this->arguments['data']);
        if ($data === [] || !isset($data['@type'])) {
            return '';
        }
        $json = json_encode(
            ['@context' => 'https://schema.org'] + $data,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_PARTIAL_OUTPUT_ON_ERROR
        );
        return '<script type="application/ld+json">' . $json . '</script>';
    }

    private static function clean(array $node): array
    {
        $result = [];
        foreach ($node as $key => $value) {
            if (is_array($value)) {
                $value = self::clean($value);
            } elseif (is_string($value)) {
                $value = trim($value);
            } elseif (is_object($value) && method_exists($value, '__toString')) {
                $value = trim((string)$value);
            }
            if ($value === '' || $value === [] || $value === null) {
                continue;
            }
            $result[$key] = $value;
        }
        if (array_is_list($node)) {
            return array_values($result);
        }
        // A node left with only @type carries no information.
        if (array_keys($result) === ['@type']) {
            return [];
        }
        return $result;
    }
}

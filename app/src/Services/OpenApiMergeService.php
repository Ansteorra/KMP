<?php

declare(strict_types=1);

namespace App\Services;

use Cake\Core\Plugin;
use Cake\Log\Log;
use Symfony\Component\Yaml\Yaml;

/**
 * Merges the base OpenAPI spec with plugin-provided fragments.
 *
 * Each plugin may place an `openapi.yaml` in its `config/` directory.
 * The service deep-merges paths, tags, and component schemas into a
 * single spec that is served to Swagger UI.
 */
class OpenApiMergeService
{
    /**
     * Build the merged OpenAPI spec array.
     *
     * @return array The complete OpenAPI spec.
     */
    public function getMergedSpec(): array
    {
        $basePath = ROOT . DS . 'webroot' . DS . 'api-docs' . DS . 'openapi.yaml';
        $base = Yaml::parseFile($basePath);

        foreach (Plugin::loaded() as $pluginName) {
            $fragmentPath = Plugin::path($pluginName) . 'config' . DS . 'openapi.yaml';
            if (!file_exists($fragmentPath)) {
                continue;
            }

            try {
                $fragment = Yaml::parseFile($fragmentPath);
                $base = $this->merge($base, $fragment);
            } catch (\Exception $e) {
                Log::warning("OpenAPI: failed to merge fragment from {$pluginName}: {$e->getMessage()}");
            }
        }

        return $base;
    }

    /**
     * Deep-merge a plugin fragment into the base spec.
     *
     * @param array $base The accumulating spec.
     * @param array $fragment The plugin fragment.
     * @return array
     */
    private function merge(array $base, array $fragment): array
    {
        // Tags (append, avoid duplicates by name)
        if (!empty($fragment['tags'])) {
            $existing = array_column($base['tags'] ?? [], 'name');
            foreach ($fragment['tags'] as $tag) {
                if (!in_array($tag['name'], $existing, true)) {
                    $base['tags'][] = $tag;
                }
            }
        }

        // Paths (merge at HTTP-method level to avoid overwriting)
        if (!empty($fragment['paths'])) {
            foreach ($fragment['paths'] as $path => $definition) {
                if (isset($base['paths'][$path])) {
                    $base['paths'][$path] = array_merge($base['paths'][$path], $definition);
                } else {
                    $base['paths'][$path] = $definition;
                }
            }
        }

        // Components → schemas
        if (!empty($fragment['components']['schemas'])) {
            foreach ($fragment['components']['schemas'] as $name => $schema) {
                $base['components']['schemas'][$name] = $schema;
            }
        }

        // Components → parameters
        if (!empty($fragment['components']['parameters'])) {
            foreach ($fragment['components']['parameters'] as $name => $param) {
                $base['components']['parameters'][$name] = $param;
            }
        }

        // Components → responses
        if (!empty($fragment['components']['responses'])) {
            foreach ($fragment['components']['responses'] as $name => $resp) {
                $base['components']['responses'][$name] = $resp;
            }
        }

        return $base;
    }
}

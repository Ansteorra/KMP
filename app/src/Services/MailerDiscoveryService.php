<?php

declare(strict_types=1);

namespace App\Services;

use Cake\Core\App;
use Cake\Mailer\Mailer;
use DirectoryIterator;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;

/**
 * Service for discovering Mailer classes and their methods using reflection
 */
class MailerDiscoveryService
{
    /**
     * Discover all Mailer classes in the application and plugins
     *
     * @return array Array of mailer information
     */
    public function discoverAllMailers(): array
    {
        $mailers = [];

        // Discover core mailers
        $coreMailers = $this->discoverMailersInNamespace('App\\Mailer', APP . 'Mailer');
        $mailers = array_merge($mailers, $coreMailers);

        // Discover plugin mailers
        $pluginMailers = $this->discoverPluginMailers();
        $mailers = array_merge($mailers, $pluginMailers);

        return $mailers;
    }

    /**
     * Discover mailers in a specific namespace
     *
     * @param string $namespace The namespace to search
     * @param string $path The filesystem path to search
     * @return array
     */
    protected function discoverMailersInNamespace(string $namespace, string $path): array
    {
        $mailers = [];

        if (!is_dir($path)) {
            return $mailers;
        }

        $iterator = new DirectoryIterator($path);

        foreach ($iterator as $fileInfo) {
            if ($fileInfo->isDot() || !$fileInfo->isFile()) {
                continue;
            }

            $fileName = $fileInfo->getFilename();

            // Only process files ending with Mailer.php
            if (!preg_match('/Mailer\.php$/', $fileName)) {
                continue;
            }

            $className = $namespace . '\\' . substr($fileName, 0, -4);

            if (!class_exists($className)) {
                continue;
            }

            try {
                $reflection = new ReflectionClass($className);

                // Only include classes that extend Mailer
                if (!$reflection->isSubclassOf(Mailer::class)) {
                    continue;
                }

                // Skip abstract classes
                if ($reflection->isAbstract()) {
                    continue;
                }

                $mailerInfo = $this->analyzeMailerClass($className, $reflection);
                if (!empty($mailerInfo['methods'])) {
                    $mailers[] = $mailerInfo;
                }
            } catch (\Exception $e) {
                // Skip classes that can't be analyzed
                continue;
            }
        }

        return $mailers;
    }

    /**
     * Discover mailers in all plugins
     *
     * @return array
     */
    protected function discoverPluginMailers(): array
    {
        $mailers = [];
        $pluginsPath = ROOT . DS . 'plugins';

        if (!is_dir($pluginsPath)) {
            return $mailers;
        }

        $iterator = new DirectoryIterator($pluginsPath);

        foreach ($iterator as $fileInfo) {
            if ($fileInfo->isDot() || !$fileInfo->isDir()) {
                continue;
            }

            $pluginName = $fileInfo->getFilename();
            $mailerPath = $fileInfo->getPathname() . DS . 'src' . DS . 'Mailer';
            $namespace = $pluginName . '\\Mailer';

            $pluginMailers = $this->discoverMailersInNamespace($namespace, $mailerPath);
            $mailers = array_merge($mailers, $pluginMailers);
        }

        return $mailers;
    }

    /**
     * Analyze a mailer class and extract method information
     *
     * @param string $className Fully qualified class name
     * @param \ReflectionClass $reflection Reflection instance
     * @return array
     */
    protected function analyzeMailerClass(string $className, ReflectionClass $reflection): array
    {
        $methods = [];
        $publicMethods = $reflection->getMethods(ReflectionMethod::IS_PUBLIC);

        // Get list of methods from traits to exclude them
        $traitMethods = [];
        foreach ($reflection->getTraits() as $trait) {
            foreach ($trait->getMethods() as $traitMethod) {
                $traitMethods[] = $traitMethod->getName();
            }
        }

        foreach ($publicMethods as $method) {
            // Skip constructor and inherited methods
            if (
                $method->getName() === '__construct' ||
                $method->getDeclaringClass()->getName() !== $className
            ) {
                continue;
            }

            // Skip methods starting with underscore
            if (str_starts_with($method->getName(), '_')) {
                continue;
            }

            // Skip magic methods
            if (str_starts_with($method->getName(), '__')) {
                continue;
            }

            // Skip methods from traits (render, deliver, setTableLocator, etc.)
            if (in_array($method->getName(), $traitMethods)) {
                continue;
            }

            $methodInfo = $this->analyzeMailerMethod($className, $method);
            $methods[] = $methodInfo;
        }

        return [
            'class' => $className,
            'shortName' => $reflection->getShortName(),
            'namespace' => $reflection->getNamespaceName(),
            'filePath' => $reflection->getFileName(),
            'methods' => $methods,
        ];
    }

    /**
     * Analyze a mailer method and extract parameter and view var information
     *
     * @param string $className Mailer class name
     * @param \ReflectionMethod $method Method reflection
     * @return array
     */
    protected function analyzeMailerMethod(string $className, ReflectionMethod $method): array
    {
        $parameters = [];
        $availableVars = [];

        // Extract method parameters
        foreach ($method->getParameters() as $param) {
            $type = 'mixed';
            if ($param->getType() instanceof ReflectionNamedType) {
                $type = $param->getType()->getName();
            }

            $parameters[] = [
                'name' => $param->getName(),
                'type' => $type,
                'required' => !$param->isOptional(),
                'default' => $param->isDefaultValueAvailable() ? $param->getDefaultValue() : null,
            ];
        }

        // Try to extract view vars by analyzing the method source code
        $availableVars = $this->extractViewVarsFromMethod($method);

        // Get default subject if possible
        $defaultSubject = $this->extractSubjectFromMethod($method);

        return [
            'name' => $method->getName(),
            'parameters' => $parameters,
            'availableVars' => $availableVars,
            'defaultSubject' => $defaultSubject,
            'docComment' => $method->getDocComment() ?: null,
        ];
    }

    /**
     * Extract view vars from method by analyzing source code
     *
     * @param \ReflectionMethod $method
     * @return array
     */
    protected function extractViewVarsFromMethod(ReflectionMethod $method): array
    {
        $vars = [];
        $fileName = $method->getFileName();
        $startLine = $method->getStartLine();
        $endLine = $method->getEndLine();

        if (!$fileName || !$startLine || !$endLine) {
            return $vars;
        }

        try {
            $file = file($fileName);
            $methodSource = implode('', array_slice($file, $startLine - 1, $endLine - $startLine + 1));

            // Look for setViewVars calls
            if (preg_match('/->setViewVars\(\s*\[(.*?)\]\s*\)/s', $methodSource, $matches)) {
                $viewVarsArray = $matches[1];

                // Extract variable names from the array
                if (preg_match_all('/[\'"]([^\'"]+)[\'"]\s*=>/i', $viewVarsArray, $varMatches)) {
                    foreach ($varMatches[1] as $varName) {
                        $vars[] = [
                            'name' => $varName,
                            'description' => $this->generateVarDescription($varName),
                        ];
                    }
                }
            }
        } catch (\Exception $e) {
            // If we can't read the file, return empty array
            return [];
        }

        return $vars;
    }

    /**
     * Extract subject from method by analyzing source code
     *
     * @param \ReflectionMethod $method
     * @return string|null
     */
    protected function extractSubjectFromMethod(ReflectionMethod $method): ?string
    {
        $fileName = $method->getFileName();
        $startLine = $method->getStartLine();
        $endLine = $method->getEndLine();

        if (!$fileName || !$startLine || !$endLine) {
            return null;
        }

        try {
            $file = file($fileName);
            $methodSource = implode('', array_slice($file, $startLine - 1, $endLine - $startLine + 1));

            // Look for setSubject calls
            if (preg_match('/->setSubject\([\'"]([^\'"]+)[\'"]\)/i', $methodSource, $matches)) {
                return $matches[1];
            }

            // Look for setSubject calls with string concatenation
            if (preg_match('/->setSubject\(([^)]+)\)/i', $methodSource, $matches)) {
                return $matches[1]; // Return the raw expression
            }
        } catch (\Exception $e) {
            return null;
        }

        return null;
    }

    /**
     * Generate a human-readable description for a variable name
     *
     * @param string $varName
     * @return string
     */
    protected function generateVarDescription(string $varName): string
    {
        // Convert camelCase to Title Case
        $words = preg_split('/(?=[A-Z])/', $varName);
        $words = array_filter($words); // Remove empty elements
        $description = implode(' ', $words);

        return ucfirst(strtolower($description));
    }

    /**
     * Get information about a specific mailer class
     *
     * @param string $className
     * @return array|null
     */
    public function getMailerInfo(string $className): ?array
    {
        if (!class_exists($className)) {
            return null;
        }

        try {
            $reflection = new ReflectionClass($className);

            if (!$reflection->isSubclassOf(Mailer::class)) {
                return null;
            }

            return $this->analyzeMailerClass($className, $reflection);
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Get information about a specific mailer method
     *
     * @param string $className
     * @param string $methodName
     * @return array|null
     */
    public function getMailerMethodInfo(string $className, string $methodName): ?array
    {
        $mailerInfo = $this->getMailerInfo($className);

        if (!$mailerInfo) {
            return null;
        }

        foreach ($mailerInfo['methods'] as $method) {
            if ($method['name'] === $methodName) {
                return $method;
            }
        }

        return null;
    }
}
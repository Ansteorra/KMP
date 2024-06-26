<?php

declare(strict_types=1);

/**
 * Licensed under The MIT License
 * For full copyright and license information, please see the LICENSE file
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright   Copyright (c) Mikaël Capelle (https://typename.fr)
 * @license     https://opensource.org/licenses/mit-license.php MIT License
 * @link        https://holt59.github.io/cakephp3-bootstrap-helpers/
 */

namespace Bootstrap\View\Helper;

use Cake\Http\ServerRequest;
use Cake\Routing\Router;

/**
 * A trait that provides a method to compare url.
 */
trait UrlComparerTrait
{
    /**
     * Parts of the URL used for normalization.
     *
     * @var array
     */
    protected $_parts = ["plugin", "prefix", "controller", "action", "pass"];

    /**
     * Retrieve the relative path of the root URL from hostname.
     *
     * @return string The relative path.
     */
    protected function _relative(): string
    {
        return trim(Router::url("/"), "/");
    }

    /**
     * Retrieve the hostname (if any).
     *
     * @return string|null The hostname, or `null`.
     */
    protected function _hostname(): ?string
    {
        $components = parse_url(Router::url("/", true));
        if (isset($components["host"])) {
            return $components["host"];
        }

        return null;
    }

    /**
     * Checks if the given URL components match the current host.
     *
     * @param string $url URL to check.
     * @return bool `true` if the URL matches, `false` otherwise.
     */
    protected function _matchHost(string $url): bool
    {
        $components = parse_url($url);

        return !(
            isset($components["host"]) &&
            $components["host"] != $this->_hostname()
        );
    }

    /**
     * Checks if the given URL components match the current relative URL. This
     * methods only works with full URL, and do not check the host.
     *
     * @param string $url URL to check.
     * @return bool `true` if the URL matches, `false` otherwise.
     */
    protected function _matchRelative(string $url): bool
    {
        $relative = $this->_relative();
        if (!$relative) {
            return true;
        }
        $components = parse_url($url);
        if (!isset($components["host"])) {
            return true;
        }
        $path = trim($components["path"], "/");

        return strpos($path, $relative) === 0;
    }

    /**
     * Remove relative part an URL (if any).
     *
     * @param   string $url URL from which the relative part should be removed.
     * @return  string The new URL.
     */
    protected function _removeRelative(string $url): string
    {
        $components = parse_url($url);
        $relative = $this->_relative();
        $path = trim($components["path"], "/");
        if ($relative && strpos($path, $relative) === 0) {
            $path = trim(substr($path, strlen($relative)), "/");
        }

        return "/" . $path;
    }

    /**
     * Normalize an URL.
     *
     * @param string|array $url URL to normalize.
     * @param array $parts Include pass parameters.
     * @return string|null Normalized URL.
     */
    protected function _normalize($url, array $parts = []): ?string
    {
        if (!is_string($url)) {
            $url = Router::url($url);
        }
        if (!$this->_matchHost($url)) {
            return null;
        }
        if (!$this->_matchRelative($url)) {
            return null;
        }
        $url = Router::parseRequest(
            new ServerRequest(["url" => $this->_removeRelative($url)]),
        );
        $arr = [];
        foreach ($this->_parts as $part) {
            if (
                !isset($url[$part]) ||
                (isset($parts[$part]) && !$parts[$part])
            ) {
                continue;
            }
            if (is_array($url[$part])) {
                $url[$part] = implode("/", $url[$part]);
            }
            if ($part != "pass") {
                $url[$part] = strtolower($url[$part]);
            }
            $arr[] = $url[$part];
        }

        return $this->_removeRelative(
            Router::normalize("/" . implode("/", $arr)),
        );
    }

    /**
     * Check if first URL is a parent of the right URL, without regards to query
     * parameters or hash.
     *
     * @param string|array $lhs First URL to compare.
     * @param string|array $rhs Second URL to compare. Default is current URL (`Router::url()`).
     * @param array $parts Include pass parameters
     * @return bool `true` if both URL match, `false` otherwise.
     */
    public function compareUrls($lhs, $rhs = null, array $parts = []): bool
    {
        if ($rhs == null) {
            $rhs = Router::url();
        }
        $lhs = $this->_normalize($lhs, $parts);
        $rhs = $this->_normalize($rhs);

        return $lhs !== null && $rhs !== null && strpos($rhs, $lhs) === 0;
    }
}

<?php
declare(strict_types=1);

namespace App\KMP;

use Cake\Core\Configure;
use Cake\ORM\TableRegistry;
use Cake\Utility\Security;
use Exception;

class StaticHelpers
{
    /**
     * Ensure a directory exists
     *
     * @param string $dirname
     * @param int $visibility
     * @return void
     */
    static function ensureDirectoryExists(string $dirname, int $visibility): void
    {
        if (is_dir($dirname)) {
            return;
        }

        error_clear_last();

        if (!@mkdir($dirname, $visibility, true)) {
            $mkdirError = error_get_last();
        }

        clearstatcache(true, $dirname);

        if (!is_dir($dirname)) {
            $errorMessage = $mkdirError['message'] ?? '';

            throw new Exception($errorMessage);
        }
    }

    /**
     * Save a scaled image
     *
     * @param string $imageName
     * @param int $newWidth
     * @param int $newHeight
     * @param string $uploadDir
     * @param string $moveToDir
     * @return string
     */
    static function saveScaledImage(string $imageName, int $newWidth, int $newHeight, string $uploadDir, string $moveToDir): string
    {
        $path = $uploadDir . '/' . $imageName;

        $mime = getimagesize($path);

        switch ($mime['mime']) {
            case 'image/png':
                $src_img = imagecreatefrompng($path);
                break;
            case 'image/jpg':
            case 'image/jpeg':
            case 'image/pjpeg':
                $src_img = imagecreatefromjpeg($path);
                break;
        }

        $old_x = imageSX($src_img);
        $old_y = imageSY($src_img);

        // Calculate the scaling to fit the image inside our frame
        $scale = min($newWidth / $old_x, $newHeight / $old_y);

        // Calculate the new dimensions
        $thumb_w = toInt(round($old_x * $scale));
        $thumb_h = toInt(round($old_y * $scale));

        $dst_img        =   ImageCreateTrueColor($thumb_w, $thumb_h);

        imagecopyresampled($dst_img, $src_img, 0, 0, 0, 0, $thumb_w, $thumb_h, $old_x, $old_y);

        // New save location
        $new_thumb_loc = $moveToDir . $imageName;

        switch ($mime['mime']) {
            case 'image/png':
                $new_thumb_loc = $new_thumb_loc . '.png';
                $result = imagepng($dst_img, $new_thumb_loc, 8);
                break;
            case 'image/jpg':
            case 'image/jpeg':
            case 'image/pjpeg':
                $new_thumb_loc = $new_thumb_loc . '.jpg';
                $result = imagejpeg($dst_img, $new_thumb_loc, 80);
                break;
        }

        imagedestroy($dst_img);
        imagedestroy($src_img);
        if (!$result) {
            return '';
        }
        if ($new_thumb_loc != $path) {
            unlink($path);
        }

        return $new_thumb_loc;
    }

    /**
     * Generate a random token
     *
     * @param int $length
     * @return string
     */
    static function generateToken(int $length = 32): string
    {
        return Security::randomString($length);
    }

    /**
     * Delete a file
     *
     * @param string $path
     * @return bool
     */
    static function deleteFile(string $path): bool
    {

        if (!file_exists($path)) {
            return true;
        }

        error_clear_last();

        if (!@unlink($path)) {
            throw new Exception(error_get_last()['message']);

            return false;
        }

        return true;
    }

    /**
     * Get a value from an array using a path
     *
     * @param string $path
     * @param mixed $array
     * @return mixed
     */
    static function getValue(string $path, mixed $array, $minLength = 0, $fallback = null): mixed
    {
        $path = explode('->', $path);
        $temp = &$array;
        $prepend = '';
        $postpend = '';

        foreach ($path as $key) {
            if (strpos($key, '(') !== false) {
                $key = explode('(', $key);
                $prepend = $key[0];
                $key = explode(')', $key[1]);
                $postpend = $key[1];
                $key = $key[0];
            }
            $temp = &$temp[$key];
        }
        if ($prepend != '' && $postpend != '' && $temp != '') {
            $temp = $prepend . $temp . $postpend;
        }
        if ($temp === null) {
            return $fallback;
        }
        if (is_array($temp)) {
            return $temp;
        }
        if (is_string($temp) && (strlen($temp) < $minLength)) {
            return $fallback;
        }

        return $temp;
    }

    /**
     * Process a string replacing {{path}} with data from the $data array using the getValue method
     *
     * @param string $string
     * @param mixed $data
     * @return string
     */
    static function processTemplate(string $string, mixed $data, $minLength = 0, $missingValue = ''): string
    {
        $matches = [];
        preg_match_all('/{{(.*?)}}/', $string, $matches);
        foreach ($matches[1] as $match) {
            $string = str_replace('{{' . $match . '}}', self::getValue($match, $data, $minLength, $missingValue), $string);
        }

        return $string;
    }

    static function pluginEnabled($pluginName)
    {
        return self::getAppSetting('Plugin.' . $pluginName . '.Active', 'no') == 'yes';
    }

    /**
     * Get an app setting
     *
     * @param string $key
     * @param string $fallback
     * @return mixed
     */
    static function getAppSetting(string $key, ?string $fallback = null, $type = null, $required = false): mixed
    {
        try {
            //check config first for the key
            $value = Configure::read($key);
            if ($value !== null) {
                return $value;
            }
            //check the app settings table
            $AppSettings = TableRegistry::getTableLocator()->get('AppSettings');
            $value = $AppSettings->getAppSetting($key, $fallback, $type, $required);

            return $value;
        } catch (Exception $e) {
            // check if e is a Cake\Database\Exception\DatabaseException
            if (get_class($e) == "Cake\Database\Exception\DatabaseException") {
                return $fallback;
            }
            throw $e;
        }
    }

    static function getAppSettingsStartWith(string $key): array
    {
        try {
            $AppSettings = TableRegistry::getTableLocator()->get('AppSettings');
            $return = $AppSettings->getAllAppSettingsStartWith($key);

            return $return;
        } catch (Exception $e) {
            return [];
        }
    }

    static function deleteAppSetting(string $key, bool $forceDelete = false): bool
    {
        try {
            $AppSettings = TableRegistry::getTableLocator()->get('AppSettings');

            return $AppSettings->deleteAppSetting($key, $forceDelete);
        } catch (Exception $e) {
            // check if e is a Cake\Database\Exception\DatabaseException
            if (get_class($e) == "Cake\Database\Exception\DatabaseException") {
                return true;
            }
            throw $e;
        }
    }

    static function setAppSetting(string $key, $value, $type = null, $required = false): bool
    {
        try {
            $AppSettings = TableRegistry::getTableLocator()->get('AppSettings');

            return $AppSettings->setAppSetting($key, $value, $type, $required);
        } catch (Exception $e) {
            return false;
        }
    }

    static function makePathString($path)
    {
        $pathString = $path['controller'] . '/' . $path['action'];
        if (isset($path['plugin'])) {
            $pathString = $path['plugin'] . '/' . $pathString;
        }
        if (isset($path[0])) {
            $pathString .= '/' . $path[0];
        }

        return strtolower($pathString);
    }

    static function arrayToCsv(array $data, $delimiter = ',', $enclosure = '"', $escapeChar = '\\')
    {
        $csvString = '';
        $f = fopen('php://memory', 'r+');

        foreach ($data as $row) {
            fputcsv($f, $row, $delimiter, $enclosure, $escapeChar);
        }

        rewind($f);
        while (($line = fgets($f)) !== false) {
            $csvString .= $line;
        }

        fclose($f);

        return $csvString;
    }

    static function makeSafeForHtmlAttribute($string)
    {
        return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
    }
}

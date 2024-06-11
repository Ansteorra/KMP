<?php

namespace App\KMP;

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
            $errorMessage = isset($mkdirError['message']) ? $mkdirError['message'] : '';

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
    static function saveScaledImage($imageName, $newWidth, $newHeight, $uploadDir, $moveToDir)
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
        $thumb_w = round($old_x * $scale);
        $thumb_h = round($old_y * $scale);

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
            return false;
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
    static function generateToken(int $length = 32)
    {
        return bin2hex(random_bytes($length));
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
     * @param array $array
     * @return mixed
     */
    static function getValue($path, $array)
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
        return $temp;
    }
    /**
     * Process a string replacing {{path}} with data from the $data array using the getValue method
     *
     * @param string $string
     * @param array $data
     * @return string
     */
    static function processTemplate($string, $data)
    {
        $matches = [];
        preg_match_all('/{{(.*?)}}/', $string, $matches);
        foreach ($matches[1] as $match) {
            $string = str_replace('{{' . $match . '}}', self::getValue($match, $data), $string);
        }
        return $string;
    }
}
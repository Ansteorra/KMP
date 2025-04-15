<?php

/**
 * CakePHP(tm) : Rapid Development Framework (https://cakephp.org)
 * Copyright (c) Cake Software Foundation, Inc. (https://cakefoundation.org)
 *
 * Licensed under The MIT License
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright     Copyright (c) Cake Software Foundation, Inc. (https://cakefoundation.org)
 * @link          https://cakephp.org CakePHP(tm) Project
 * @since         3.0.0
 * @license       MIT License (https://opensource.org/licenses/mit-license.php)
 */

/*
 * Use the DS to separate the directories in other defines
 */
if (!defined("DS")) {
    define("DS", DIRECTORY_SEPARATOR);
}

/*
 * These defines should only be edited if you have cake installed in
 * a directory layout other than the way it is distributed.
 * When using custom settings be sure to use the DS and do not add a trailing DS.
 */

/*
 * The full path to the directory which holds "src", WITHOUT a trailing DS.
 */
if (!defined("ROOT")) {
    define("ROOT", dirname(__DIR__));
}

/*
 * The actual directory name for the application directory. Normally
 * named 'src'.
 */
if (!defined("APP_DIR")) {
    define("APP_DIR", "src");
}

/*
 * Path to the application's directory.
 */
if (!defined("APP")) {
    define("APP", ROOT . DS . APP_DIR . DS);
}

/*
 * Path to the config directory.
 */
if (!defined("CONFIG")) {
    define("CONFIG", ROOT . DS . "config" . DS);
}

/*
 * File path to the webroot directory.
 *
 * To derive your webroot from your webserver change this to:
 *
 * `define('WWW_ROOT', rtrim($_SERVER['DOCUMENT_ROOT'], DS) . DS);`
 */
if (!defined("WWW_ROOT")) {
    define("WWW_ROOT", ROOT . DS . "webroot" . DS);
}

/*
 * Path to the tests directory.
 */
if (!defined("TESTS")) {
    define("TESTS", ROOT . DS . "tests" . DS);
}

/*
 * Path to the temporary files directory.
 */
if (!defined("TMP")) {
    define("TMP", ROOT . DS . "tmp" . DS);
}

/*
 * Path to the logs directory.
 */
if (!defined("LOGS")) {
    define("LOGS", ROOT . DS . "logs" . DS);
}

/*
 * Path to the cache files directory. It can be shared between hosts in a multi-server setup.
 */
if (!defined("CACHE")) {
    define("CACHE", TMP . "cache" . DS);
}

/*
 * Path to the resources directory.
 */
if (!defined("RESOURCES")) {
    define("RESOURCES", ROOT . DS . "resources" . DS);
}

/*
 * The absolute path to the "cake" directory, WITHOUT a trailing DS.
 *
 * CakePHP should always be installed with composer, so look there.
 */
if (!defined("CAKE_CORE_INCLUDE_PATH")) {
    define(
        "CAKE_CORE_INCLUDE_PATH",
        ROOT . DS . "vendor" . DS . "cakephp" . DS . "cakephp",
    );
}

/*
 * Path to the cake directory.
 */
if (!defined("CORE_PATH")) {
    define("CORE_PATH", CAKE_CORE_INCLUDE_PATH . DS);
}
if (!defined("CAKE")) {
    define("CAKE", CORE_PATH . "src" . DS);
}

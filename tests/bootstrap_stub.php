<?php

/**
 * Minimal GLPI stubs so we can execute plugin helpers without a full GLPI install.
 */

declare(strict_types=1);

if (!defined('READ')) {
    define('READ', 1);
}
if (!defined('UPDATE')) {
    define('UPDATE', 2);
}

if (!function_exists('__')) {
    function __(string $msg, ?string $domain = null): string
    {
        return $msg;
    }
}

if (!class_exists('Session', false)) {
    class Session
    {
        public static function checkLoginUser(): void
        {
        }

        public static function checkRight(string $right, int $level): void
        {
        }

        public static function haveRight(string $right, int $level): bool
        {
            return true;
        }
    }
}

if (!class_exists('Plugin', false)) {
    class Plugin
    {
        /** @var string */
        public static $phpDir = '';

        /** @var string */
        public static $webDir = '/glpi/plugins/paineldebordo';

        public static function getPhpDir(string $plugin): string
        {
            return self::$phpDir !== '' ? self::$phpDir : dirname(__DIR__);
        }

        public static function getWebDir(string $plugin, bool $full = true): string
        {
            return self::$webDir;
        }

        public static function registerClass($class, $opts = []): void
        {
        }
    }
}

if (!class_exists('DB', false)) {
    class DB
    {
        public function escape(string $s): string
        {
            return addslashes($s);
        }

        public function TableExists(string $table): bool
        {
            return false;
        }

        public function fieldExists(string $table, string $field): bool
        {
            return false;
        }

        public function doQuery(string $sql)
        {
            return false;
        }

        public function numrows($r): int
        {
            return 0;
        }

        public function result($r, $row, $field)
        {
            return null;
        }

        public function fetchAssoc($r)
        {
            return false;
        }

        public function request(array $opts): array
        {
            return [];
        }
    }
}

if (!class_exists('Ticket', false)) {
    class Ticket
    {
        public static string $rightname = 'ticket';

        public const READALL = 1;
    }
}

if (!class_exists('Profile_User', false)) {
    class Profile_User
    {
        public static function getUserEntitiesForRight($users_id, $right, $level): array
        {
            return [0];
        }
    }
}

if (!class_exists('Html', false)) {
    class Html
    {
        public static function displayErrorAndDie(string $msg): void
        {
            throw new RuntimeException($msg);
        }
    }
}

$GLOBALS['DB'] = new DB();
$GLOBALS['CFG_GLPI'] = [
    'root_doc' => '/glpi',
    'language' => 'pt_BR',
];

$_SESSION = [
    'glpiID'            => 1,
    'glpiname'          => 'tester',
    'glpilanguage'      => 'pt_BR',
    'glpigroups'        => [1, 2],
    'glpiactiveprofile' => ['interface' => 'central', 'name' => 'Super-Admin'],
];

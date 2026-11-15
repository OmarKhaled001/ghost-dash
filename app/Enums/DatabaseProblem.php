<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Why a MySQL connection attempt failed, reduced to the cases the installer
 * can say something useful about.
 */
enum DatabaseProblem: string
{
    case Unreachable = 'unreachable';
    case AccessDenied = 'access_denied';
    case UnknownDatabase = 'unknown_database';
    case NoPrivilege = 'no_privilege';
    case Other = 'other';

    /**
     * MySQL client and server error numbers.
     */
    public static function fromErrorCode(int $code): self
    {
        return match ($code) {
            2002, 2003, 2005, 2006, 2013 => self::Unreachable,
            1045, 1698 => self::AccessDenied,
            1049 => self::UnknownDatabase,
            1044, 1142, 1227 => self::NoPrivilege,
            default => self::Other,
        };
    }

    /**
     * What to tell the person, and what to try. English on purpose: this is
     * terminal output for whoever is installing the application.
     */
    public function hint(string $host, string|int $port, string $database, string $username): string
    {
        return match ($this) {
            self::Unreachable => implode("\n", [
                "MySQL is not reachable at {$host}:{$port} (error 2002).",
                'Is the server running, and is that the right host and port?',
                '  macOS:    brew services start mysql   (or start it in DBngin / Herd)',
                '  Linux:    sudo systemctl start mysql',
                '  Windows:  net start MySQL84           (or start it in Laragon / XAMPP / Herd)',
                '  Docker:   docker run -d -p 3306:3306 -e MYSQL_ROOT_PASSWORD=secret mysql:8.4',
                "If MySQL runs on this machine, try 127.0.0.1 instead of localhost: 'localhost' uses a socket file.",
            ]),
            self::AccessDenied => implode("\n", [
                "MySQL refused the username or password for '{$username}' (error 1045).",
                'Check both. A fresh local MySQL usually has user "root" with an empty password.',
                'On Ubuntu and Debian, root signs in through the system account (sudo mysql) and has no password for TCP connections. Create a user for the application instead:',
                "  sudo mysql -e \"CREATE USER '{$username}'@'localhost' IDENTIFIED BY 'a-password'; GRANT ALL ON *.* TO '{$username}'@'localhost';\"",
            ]),
            self::UnknownDatabase => implode("\n", [
                "The database '{$database}' does not exist (error 1049).",
                'Let the installer create it (answer yes, or pass --create-db), or create it yourself:',
                "  mysql -u {$username} -p -e \"CREATE DATABASE `{$database}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci\"",
            ]),
            self::NoPrivilege => implode("\n", [
                "The MySQL user '{$username}' is not allowed to do that (error 1044).",
                'Ask whoever administers MySQL to create the database and grant access:',
                "  CREATE DATABASE `{$database}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;",
                "  GRANT ALL PRIVILEGES ON `{$database}`.* TO '{$username}'@'%';",
            ]),
            self::Other => 'MySQL reported an error the installer does not have advice for. The message above is from MySQL itself.',
        };
    }
}

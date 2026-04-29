<?php

namespace App;

class ShellHelper
{
    public static function buildCommand(string $binary, array $args = [], bool $useSudo = false, bool $captureStderr = true): string
    {
        $parts = [];
        if ($useSudo) {
            $parts[] = '/usr/bin/sudo';
        }

        $parts[] = escapeshellcmd($binary);

        foreach ($args as $arg) {
            $parts[] = escapeshellarg((string)$arg);
        }

        $command = implode(' ', $parts);
        if ($captureStderr) {
            $command .= ' 2>&1';
        }

        return $command;
    }

    public static function exec(string $binary, array $args = [], array &$output = null, int &$returnVar = null, bool $useSudo = true): string
    {
        $command = self::buildCommand($binary, $args, $useSudo, true);
        if ($output !== null) {
            $output = [];
        }
        exec($command, $output, $returnVar);
        return $command;
    }

    public static function shell(string $command, array &$output = null, int &$returnVar = null): string
    {
        $fullCommand = $command . ' 2>&1';
        exec($fullCommand, $output, $returnVar);
        return $fullCommand;
    }
}

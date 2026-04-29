<?php

namespace App;

require_once __DIR__ . '/ShellHelper.php';

/**
 * Classe responsável por extrair métricas do Sistema Operacional (uso de CPU, RAM, IO, Rede e Uptime).
 */
class ServerMonitor {

    public function getUptime(): string {
        if (PHP_OS_FAMILY === 'Windows') return "1d 2h 3m (Simulado)";
        $output = [];
        $ret = 0;
        \App\ShellHelper::exec('/bin/cat', ['/proc/uptime'], $output, $ret, false);
        $uptime = $output[0] ?? '';
        if ($uptime) {
             $uptime = explode(" ", $uptime)[0];
             $days = floor($uptime / 60 / 60 / 24);
             $hours = floor($uptime / 60 / 60) % 24;
             $mins = floor($uptime / 60) % 60;
             return "{$days}d {$hours}h {$mins}m";
        }
        return "Unknown";
    }

    public function getMemoryUsage(): array {
        $data = $this->getDetailedMemory();
        return [
            'total' => $data['total'],
            'used' => $data['used'],
            'percent' => $data['percent']
        ];
    }

    public function getDetailedMemory(): array {
        if (PHP_OS_FAMILY === 'Windows') {
            return ['total' => 8192, 'used' => 4096, 'percent' => 50.0, 'swap_total' => 0, 'swap_used' => 0, 'swap_percent' => 0, 'buffers_cache' => 0];
        }

        $base = ['total' => 0, 'used' => 0, 'percent' => 0, 'swap_total' => 0, 'swap_used' => 0, 'swap_percent' => 0, 'buffers_cache' => 0];
        $output = [];
        $ret = 0;
        \App\ShellHelper::exec('/usr/bin/free', ['-m'], $output, $ret, false);
        $free = implode("\n", $output);
        if ($free) {
            $lines = explode("\n", trim($free));
            foreach ($lines as $line) {
                if (str_starts_with($line, 'Mem:')) {
                    $parts = explode(" ", preg_replace('/\s+/', ' ', $line));
                    $base['total'] = (int)($parts[1] ?? 0);
                    $base['used'] = (int)($parts[2] ?? 0);
                    $base['buffers_cache'] = (int)($parts[5] ?? 0);
                    if ($base['total'] > 0) {
                        $base['percent'] = round(($base['used'] / $base['total']) * 100, 2);
                    }
                }
                if (str_starts_with($line, 'Swap:')) {
                    $parts = explode(" ", preg_replace('/\s+/', ' ', $line));
                    $base['swap_total'] = (int)($parts[1] ?? 0);
                    $base['swap_used'] = (int)($parts[2] ?? 0);
                    if ($base['swap_total'] > 0) {
                        $base['swap_percent'] = round(($base['swap_used'] / $base['swap_total']) * 100, 2);
                    }
                }
            }
        }
        return $base;
    }

    public function getCpuUsage(): float {
        $stats = $this->getDetailedCpu();
        return $stats['load1'];
    }

    public function getDetailedCpu(): array {
        if (PHP_OS_FAMILY === 'Windows') return ['load1' => 1.5, 'load5' => 1.0, 'load15' => 0.8];
        $load = sys_getloadavg();
        return [
            'load1' => isset($load[0]) ? round($load[0], 2) : 0.0,
            'load5' => isset($load[1]) ? round($load[1], 2) : 0.0,
            'load15' => isset($load[2]) ? round($load[2], 2) : 0.0,
        ];
    }

    public function getDiskUsage(): array {
        if (PHP_OS_FAMILY === 'Windows') return ['total' => 100, 'used' => 45, 'percent' => 45.0];

        $free = disk_free_space("/");
        $total = disk_total_space("/");
        $used = $total - $free;
        $percent = $total > 0 ? round(($used / $total) * 100, 2) : 0;

        return [
            'total' => round($total / 1024 / 1024 / 1024, 2), // GB
            'used' => round($used / 1024 / 1024 / 1024, 2),  // GB
            'percent' => $percent
        ];
    }

    public function getNetworkStats(): array {
        if (PHP_OS_FAMILY === 'Windows') return ['drops' => 0, 'errors' => 0];

        $lines = @file('/proc/net/dev');
        $stats = ['drops' => 0, 'errors' => 0];
        if ($lines) {
            foreach ($lines as $line) {
                if (strpos($line, ':') !== false) {
                    list($iface, $data) = explode(':', $line);
                    if (trim($iface) === 'lo') continue;
                    $cols = preg_split('/\s+/', trim($data));
                    $stats['drops'] += (int)($cols[3] ?? 0) + (int)($cols[11] ?? 0); // rx_drop + tx_drop
                    $stats['errors'] += (int)($cols[2] ?? 0) + (int)($cols[10] ?? 0); // rx_errs + tx_errs
                }
            }
        }
        return $stats;
    }
}

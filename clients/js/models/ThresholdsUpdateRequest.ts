/* generated using openapi-typescript-codegen -- do not edit */
/* istanbul ignore file */
/* tslint:disable */
/* eslint-disable */
/**
 * Aceita apenas os 6 thresholds conhecidos. Valores precisam ser >= 0.
 * Campos omitidos não são alterados (PATCH-style).
 */
export type ThresholdsUpdateRequest = {
    alert_threshold_cpu_load1?: (number | null);
    alert_threshold_mem_percent?: (number | null);
    alert_threshold_swap_percent?: (number | null);
    alert_threshold_disk_percent?: (number | null);
    alert_threshold_network_counters?: (number | null);
    alert_threshold_ssh_failed_day?: (number | null);
};


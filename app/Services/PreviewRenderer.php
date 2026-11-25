<?php

namespace App\Services;

final class PreviewRenderer
{
    private const LOWERCASE_WORDS = ['of', 'in', 'to', 'and', 'the', 'for', 'by', 'on', 'a', 'an', 'if', 'any'];

    private const SPECIAL_WORDS = [
        'nimcos' => 'NIMCOS', 'mou' => 'MoU', 'mous' => 'MoUs', 'midas' => 'MIDAS',
        'evisa' => 'e-Visa', 'dfu' => 'DFU', 'dofit' => 'DOFIT', 'ecowas' => 'ECOWAS', 'cerpac' => 'CERPAC',
        'lasc' => 'LASC', 'seme' => 'SEME', 'idbc' => 'IDBC', 'lspmc' => 'LSPMC', 'lapc' => 'LAPC',
        'mmia' => 'MMIA', 'ogsc' => 'OGSC', 'kdsc' => 'KDSC', 'knsc' => 'KNSC', 'itsk' => 'ITSK',
        'makia' => 'MAKIA', 'ktsc' => 'KTSC', 'jsbc' => 'JSBC', 'sosc' => 'SOSC', 'icsc' => 'ICSC',
        'illela' => 'ILLELA', 'zmsc' => 'ZMSC', 'jgsc' => 'JGSC', 'basc' => 'BASC', 'ybsc' => 'YBSC',
        'bosc' => 'BOSC', 'adsc' => 'ADSC', 'gmsc' => 'GMSC', 'plsc' => 'PLSC', 'fctc' => 'FCTC',
        'naia' => 'NAIA', 'kwsc' => 'KWSC', 'ngsc' => 'NGSC', 'kbsc' => 'KBSC', 'absc' => 'ABSC',
        'aksc' => 'AKSC', 'crsc' => 'CRSC', 'mfum' => 'MFUM', 'ebsc' => 'EBSC', 'imsc' => 'IMSC',
        'nitsol' => 'NITSOL', 'rvsc' => 'RVSC', 'phia' => 'PHIA', 'nitsa' => 'NITSA', 'rvmc' => 'RVMC',
        'oysc' => 'OYSC', 'ossc' => 'OSSC', 'odsc' => 'ODSC', 'eksc' => 'EKSC', 'ansc' => 'ANSC',
        'bysc' => 'BYSC', 'dtsc' => 'DTSC', 'ensc' => 'ENSC', 'aiia' => 'AIIA', 'edsc' => 'EDSC',
        'bnsc' => 'BNSC', 'kgsc' => 'KGSC', 'trsc' => 'TRSC', 'nasc' => 'NASC',
    ];

    private const DIRECTORATE_ORDER = [
        'hrm' => 'Human Resources Management',
        'prs' => 'Planning, Research, Statistics',
        'finance' => 'Finance & Accounts',
        'investigation' => 'Investigation & Compliance',
        'passport' => 'Passport & Travel Documents',
        'visa' => 'Visa & Residency',
        'migration' => 'Migration Directorate',
        'border' => 'Border Management',
        'ict' => 'ICT',
        'works-logistics' => 'Works & Logistics',
    ];

    private const KEY_TO_DIRECTORATE = [
        'hrm' => 'hrm',
        'prs' => 'prs',
        'finance' => 'finance',
        'local_revenue_passport' => 'finance',
        'local_revenue_visa' => 'finance',
        'local_revenue_e_pass' => 'finance',
        'local_revenue_other' => 'finance',
        'foreign_revenue_total' => 'finance',
        'expenditure_profile_total' => 'finance',
        'budget_amount' => 'finance',
        'release_amount' => 'finance',
        'finance_challenges' => 'finance',
        'investigation' => 'investigation',
        'breach' => 'investigation',
        'dfu' => 'investigation',
        'dofit' => 'investigation',
        'surveillance' => 'investigation',
        'interpol' => 'investigation',
        'citizenship' => 'investigation',
        'suspect' => 'investigation',
        'screening' => 'investigation',
        'deportation' => 'investigation',
        'passport' => 'passport',
        'visa' => 'visa',
        'migration' => 'migration',
        'smuggling_of_migrants' => 'migration',
        'trafficking_in_persons' => 'migration',
        'refugees_asylum_seekers' => 'migration',
        'mou_countries' => 'migration',
        'border' => 'border',
        'ict' => 'ict',
        'project_programme_activities' => 'ict',
        'systems_operational' => 'ict',
        'midas_sites_deployed' => 'ict',
        'software_delivered' => 'ict',
        'hardware_maintenance_cases' => 'ict',
        'data_breach_incidents' => 'ict',
        'cybersecurity_incidents' => 'ict',
        'security_controls_deployed' => 'ict',
        'ict_other_reports' => 'ict',
        'works' => 'works-logistics',
        'works-logistics' => 'works-logistics',
        'arms_summary' => 'works-logistics',
        'ammunition_summary' => 'works-logistics',
        'store_tailoring' => 'works-logistics',
        'energy_utilities' => 'works-logistics',
        'arms_returns' => 'works-logistics',
        'ammunition_returns' => 'works-logistics',
        'store_accessories' => 'works-logistics',
        'store_stationery' => 'works-logistics',
        'store_uniforms' => 'works-logistics',
        'store_oil_gas' => 'works-logistics',
        'transport_fleet' => 'works-logistics',
        'transport_procurement' => 'works-logistics',
        'works_infra_projects' => 'works-logistics',
        'works_intervention_projects' => 'works-logistics',
        'works_notable_activities' => 'works-logistics',
        'maintenance_log' => 'works-logistics',
    ];

    /**
     * Build an ordered list of preview sections from return data.
     *
     * @param array<string, mixed> $data
     * @param array<string, mixed> $options
     * @return array<int, array{label: string, html: string}>
     */
    public static function buildSections(array $data, array $options = []): array
    {
        $skipKeys = $options['skipKeys'] ?? [];
        $directorateSlug = $options['directorateSlug'] ?? null;
        $directorateNames = $options['directorateNames'] ?? [];
        $sectionLabels = $options['sectionLabels'] ?? [];
        $groupByDirectorate = (bool) ($options['groupByDirectorate'] ?? false);

        if ($groupByDirectorate) {
            return self::buildDirectorateGroupedSections($data, $skipKeys);
        }

        $sections = [];
        $flatFields = [];

        foreach ($data as $key => $value) {
            if (in_array($key, $skipKeys, true) || self::isEmptyValue($value)) {
                continue;
            }

            if ($key === $directorateSlug && is_array($value)) {
                foreach ($value as $subKey => $subValue) {
                    if (self::isEmptyValue($subValue)) {
                        continue;
                    }
                    if (is_array($subValue)) {
                        $sections[] = [
                            'label' => $sectionLabels[$subKey] ?? self::humanize($subKey),
                            'html' => self::renderData($subValue),
                        ];
                    } else {
                        $flatFields[$subKey] = $subValue;
                    }
                }
                continue;
            }

            if (is_array($value)) {
                $sections[] = [
                    'label' => $sectionLabels[$key] ?? $directorateNames[$key] ?? self::humanize($key),
                    'html' => self::renderData($value),
                ];
            } else {
                $flatFields[$key] = $value;
            }
        }

        if ($flatFields !== []) {
            array_unshift($sections, ['label' => 'Return Details', 'html' => self::kvTable($flatFields)]);
        }

        return $sections;
    }

    /**
     * Group combined state return fields under the 10 official directorates in
     * canonical order, preserving each directorate's subsections underneath.
     *
     * @param array<string, mixed> $data
     * @param array<int, string> $skipKeys
     * @return array<int, array{label: string, html: string}>
     */
    private static function buildDirectorateGroupedSections(array $data, array $skipKeys): array
    {
        $buckets = [];
        foreach (array_keys(self::DIRECTORATE_ORDER) as $slug) {
            $buckets[$slug] = ['arrays' => [], 'scalars' => []];
        }
        $otherArrays = [];
        $otherScalars = [];

        foreach ($data as $key => $value) {
            if (in_array($key, $skipKeys, true) || self::isEmptyValue($value)) {
                continue;
            }

            $dir = self::KEY_TO_DIRECTORATE[$key] ?? null;
            if ($dir === null && is_string($key)) {
                if (str_starts_with($key, 'local_revenue_') || str_starts_with($key, 'foreign_revenue_') || str_starts_with($key, 'finance_')) {
                    $dir = 'finance';
                } elseif (str_starts_with($key, 'works_') || str_starts_with($key, 'store_') || str_starts_with($key, 'transport_') || str_starts_with($key, 'arms_') || str_starts_with($key, 'ammunition_')) {
                    $dir = 'works-logistics';
                } elseif (str_starts_with($key, 'ict_')) {
                    $dir = 'ict';
                }
            }

            if ($dir !== null) {
                if (is_array($value)) {
                    $buckets[$dir]['arrays'][$key] = $value;
                } else {
                    $buckets[$dir]['scalars'][$key] = $value;
                }
            } else {
                if (is_array($value)) {
                    $otherArrays[$key] = $value;
                } else {
                    $otherScalars[$key] = $value;
                }
            }
        }

        $sections = [];
        foreach (self::DIRECTORATE_ORDER as $slug => $label) {
            $html = '';
            $scalars = $buckets[$slug]['scalars'];
            $arrays = $buckets[$slug]['arrays'];

            if ($scalars !== []) {
                $html .= self::kvTable($scalars);
            }

            foreach ($arrays as $subKey => $subVal) {
                $rendered = self::renderData($subVal);
                if ($rendered === '') {
                    continue;
                }
                /* If the key itself is the directorate container (e.g. hrm, prs, passport,
                   visa, migration, border, ict, works, investigation), its child subsections
                   already have titles from renderData(); otherwise add a subsection header. */
                if ($subKey !== $slug && ! ($slug === 'works-logistics' && $subKey === 'works')) {
                    $html .= '<h3 class="sub-section-title">' . e(self::humanize($subKey)) . '</h3>';
                }
                $html .= $rendered;
            }

            $sections[] = [
                'label' => $label,
                'html' => $html,
            ];
        }

        if ($otherArrays !== [] || $otherScalars !== []) {
            $otherHtml = '';
            if ($otherScalars !== []) {
                $otherHtml .= self::kvTable($otherScalars);
            }
            foreach ($otherArrays as $k => $v) {
                $rendered = self::renderData($v);
                if ($rendered !== '') {
                    $otherHtml .= '<h3 class="sub-section-title">' . e(self::humanize($k)) . '</h3>' . $rendered;
                }
            }
            if ($otherHtml !== '') {
                $sections[] = ['label' => 'Other Details', 'html' => $otherHtml];
            }
        }

        return $sections;
    }

    public static function humanize(string|int $key): string
    {
        $words = preg_split('/[_\-\s]+/', (string) $key) ?: [];
        $out = [];
        foreach ($words as $w) {
            $l = strtolower($w);
            if (isset(self::SPECIAL_WORDS[$l])) {
                $out[] = self::SPECIAL_WORDS[$l];
            } elseif (strlen($l) <= 3 && ctype_alpha($l) && ! in_array($l, self::LOWERCASE_WORDS, true)) {
                $out[] = strtoupper($l);
            } else {
                $out[] = ucfirst($l);
            }
        }
        return implode(' ', $out);
    }

    public static function formatValue(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '-';
        }
        if (is_bool($value)) {
            return $value ? 'Yes' : 'No';
        }
        return (string) $value;
    }

    private static function isEmptyValue(mixed $value): bool
    {
        if ($value === null || $value === '') {
            return true;
        }
        if (is_array($value)) {
            foreach ($value as $v) {
                if (! self::isEmptyValue($v)) {
                    return false;
                }
            }
            return true;
        }
        return false;
    }

    private static function isAssoc(array $arr): bool
    {
        return $arr !== [] && array_keys($arr) !== range(0, count($arr) - 1);
    }

    private static function isFlat(array $arr): bool
    {
        foreach ($arr as $v) {
            if (is_array($v)) {
                return false;
            }
        }
        return true;
    }

    private static function kvTable(array $pairs): string
    {
        $out = '<table class="report-table kv-table"><tbody>';
        foreach ($pairs as $key => $value) {
            if (self::isEmptyValue($value)) {
                continue;
            }
            $out .= '<tr><td class="kv-key">' . e(self::humanize($key)) . '</td><td>'
                . e(self::formatValue($value)) . '</td></tr>';
        }
        return $out . '</tbody></table>';
    }

    private static function matrixTable(array $rows): string
    {
        $columns = [];
        foreach ($rows as $row) {
            foreach (array_keys($row) as $col) {
                if (! in_array($col, $columns, true)) {
                    $columns[] = $col;
                }
            }
        }

        $out = '<table class="report-table"><thead><tr><th style="width:32%;">Item</th>';
        foreach ($columns as $col) {
            $out .= '<th>' . e(self::humanize($col)) . '</th>';
        }
        $out .= '</tr></thead><tbody>';
        foreach ($rows as $label => $row) {
            if (self::isEmptyValue($row)) {
                continue;
            }
            $out .= '<tr><td class="row-head">' . e(self::humanize($label)) . '</td>';
            foreach ($columns as $col) {
                $out .= '<td>' . e(self::formatValue($row[$col] ?? null)) . '</td>';
            }
            $out .= '</tr>';
        }
        return $out . '</tbody></table>';
    }

    private static function rowsTable(array $rows): string
    {
        $rows = array_values(array_filter($rows, function ($row) {
            if (! is_array($row)) {
                return ! self::isEmptyValue($row);
            }
            foreach ($row as $v) {
                if (! self::isEmptyValue($v)) {
                    return true;
                }
            }
            return false;
        }));

        if ($rows === []) {
            return '';
        }

        $columns = [];
        foreach ($rows as $row) {
            foreach (array_keys($row) as $col) {
                if (! in_array($col, $columns, true)) {
                    $columns[] = $col;
                }
            }
        }

        $out = '<table class="report-table"><thead><tr><th style="width:44px;">S/N</th>';
        foreach ($columns as $col) {
            $out .= '<th>' . e(self::humanize($col)) . '</th>';
        }
        $out .= '</tr></thead><tbody>';
        foreach ($rows as $i => $row) {
            $out .= '<tr><td>' . ($i + 1) . '</td>';
            foreach ($columns as $col) {
                $cell = is_array($row) ? ($row[$col] ?? null) : null;
                $out .= '<td>' . (is_array($cell) ? self::renderData($cell) : e(self::formatValue($cell))) . '</td>';
            }
            $out .= '</tr>';
        }
        return $out . '</tbody></table>';
    }

    private static function renderData(mixed $value): string
    {
        if (! is_array($value)) {
            return self::isEmptyValue($value) ? '' : '<span>' . e(self::formatValue($value)) . '</span>';
        }
        if ($value === [] || self::isEmptyValue($value)) {
            return '';
        }

        if (! self::isAssoc($value)) {
            $allArrays = count(array_filter($value, 'is_array')) === count($value);
            if (! $allArrays) {
                $filled = array_values(array_filter($value, fn ($v) => ! self::isEmptyValue($v)));
                return $filled === [] ? '' : '<span>' . e(implode(', ', array_map([self::class, 'formatValue'], $filled))) . '</span>';
            }
            return self::rowsTable($value);
        }

        $scalars = [];
        $matrixRows = [];
        $nested = [];
        foreach ($value as $key => $item) {
            if (self::isEmptyValue($item)) {
                continue;
            }
            if (! is_array($item)) {
                $scalars[$key] = $item;
            } elseif (self::isFlat($item)) {
                $matrixRows[$key] = $item;
            } else {
                $nested[$key] = $item;
            }
        }

        $out = '';
        if ($scalars !== []) {
            $out .= self::kvTable($scalars);
        }
        if ($matrixRows !== []) {
            $out .= self::matrixTable($matrixRows);
        }
        foreach ($nested as $key => $item) {
            $label = (strlen((string) $key) === 1 && ctype_alpha((string) $key))
                ? 'Zone ' . strtoupper((string) $key)
                : self::humanize($key);
            $out .= '<div class="sub-section-title">' . e($label) . '</div>';
            $out .= self::renderData($item);
        }

        return $out;
    }
}

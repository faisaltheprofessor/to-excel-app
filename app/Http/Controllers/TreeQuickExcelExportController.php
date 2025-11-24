<?php

namespace App\Http\Controllers;

use App\Models\OrganizationStructure as TreeModel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Symfony\Component\HttpFoundation\Response;

class TreeQuickExcelExportController extends Controller
{
    public function __invoke(TreeModel $tree, Request $request): Response
    {
        // read options from query parameters
        $ge         = $request->boolean('ge', true);
        $ablage     = $request->boolean('ablage', true);
        $rolesSheet = $request->boolean('roles', true);
        $rolesCount = (int) $request->input('rolesCount', 10);

        if ($rolesCount < 1 || $rolesCount > 50) {
            $rolesCount = 10;
        }

        $selectedSheets = [];
        if ($ge) {
            $selectedSheets[] = 'GE';
        }
        if ($ablage) {
            $selectedSheets[] = 'Ablage';
        }
        if ($rolesSheet) {
            $selectedSheets[] = 'Roles';
        }

        if (empty($selectedSheets)) {
            abort(422, 'Keine Arbeitsblätter ausgewählt.');
        }

        $payload = [
            'tree'       => $this->wrapForExport($tree->data ?? []),
            'sheets'     => $selectedSheets,
            'rolesCount' => $rolesSheet ? $rolesCount : 0,
        ];

        $port = (string) config('services.python.backend', '8000');
        $url  = 'http://localhost:' . $port . '/generate-excel';

        $res = Http::accept('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
            ->post($url, $payload);

        if (! $res->successful()) {
            abort(500, 'Excel-Erzeugung fehlgeschlagen.');
        }

        $basename = $this->computeDownloadBasename($tree->title);
        $filename = $basename . '.xlsx';

        return response($res->body(), 200, [
            'Content-Type'        => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    protected function wrapForExport(array $nodes): array
    {
        $clean = $this->stripInternal($nodes);

        return [[
            'name'     => '.PANKOW',
            'appName'  => '.PANKOW',
            'children' => [[
                'name'     => 'ba',
                'appName'  => 'ba',
                'children' => [[
                    'name'     => 'DigitaleAkte-203',
                    'appName'  => 'DigitaleAkte-203',
                    'children' => $clean,
                ]],
            ]],
        ]];
    }

    protected function stripInternal(array $nodes): array
    {
        $out = [];

        foreach ($nodes as $n) {
            $row = [
                'name'     => $n['name']     ?? '',
                'appName'  => $n['appName']  ?? ($n['name'] ?? ''),
                'children' => !empty($n['children'])
                    ? $this->stripInternal($n['children'])
                    : [],
            ];

            if (array_key_exists('enabled', $n)) {
                $row['enabled'] = (bool) $n['enabled'];
            }

            if (isset($n['description'])) {
                $row['description'] = (string) $n['description'];
            }

            $out[] = $row;
        }

        return $out;
    }

    protected function computeDownloadBasename(?string $title): string
    {
        $base = $title ?? '';
        $base = $this->translitUmlauts($base);
        $base = preg_replace('/\.xlsx$/ui', '', $base);
        $base = preg_replace('/[<>:"\/\\\\|?*\x00-\x1F]/u', '-', $base);
        $base = preg_replace('/\s+/u', ' ', $base);
        $base = trim($base, " .-");

        if ($base === '') {
            $base = 'Importer-Datei';
        }

        if (mb_strlen($base) > 120) {
            $base = mb_substr($base, 0, 120);
        }

        $base = str_replace(' ', '_', $base);

        $timestamp = date('Y-m-d_H-i');

        return $timestamp . '_' . $base;
    }

    protected function translitUmlauts(string $s): string
    {
        $map = [
            'Ä' => 'Ae', 'Ö' => 'Oe', 'Ü' => 'Ue',
            'ä' => 'ae', 'ö' => 'oe', 'ü' => 'ue',
            'ß' => 'ss',
        ];

        return strtr($s, $map);
    }
}

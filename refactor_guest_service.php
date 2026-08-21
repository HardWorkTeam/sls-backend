<?php

$file = 'd:/Norton/smp-project/sls-backend/app/Services/GuestService.php';
$content = file_get_contents($file);

// Find the start of importCsv
$startPos = strpos($content, 'public function importCsv(');
// Find the start of exportExcel, which comes after importCsv
$endPos = strpos($content, 'public function exportExcel(');

if ($startPos === false || $endPos === false) {
    die("Could not find importCsv or exportExcel boundaries.\n");
}

$before = substr($content, 0, $startPos);
$importCsvContent = substr($content, $startPos, $endPos - $startPos);
$after = substr($content, $endPos);

$newContentStr = <<<'EOD'
    /**
     * Preview guests from an Excel (XLSX/XLS) or CSV file across all sheets.
     * Returns the parsed guests so the frontend can preview and edit them before confirmation.
     *
     * @return array{parsed: list<array<string, mixed>>, errors: list<string>}
     */
    public function previewImportCsv(Wedding $wedding, UploadedFile $file): array
    {
        $sheets = Excel::readSheets($file->getRealPath());

        if ($sheets === null) {
            return ['parsed' => [], 'errors' => ['Unable to read the uploaded file.']];
        }

        $aliases = [
            'name' => [
                'name', 'guest_name', 'full_name', 'guest', 'names', 'guest name', 'full name', 'fullname',
                'guests', 'guestlist', 'guest list', 'khmer name', 'english name', 'attendee', 'attendees',
                'visitor', 'visitors', 'ឈ្មោះ', 'ភ្ញៀវ', 'ឈ្មោះភ្ញៀវ', 'ឈ្មោះ ភ្ញៀវ', 'អ្នកចូលរួម', 'ឈ្មោះពេញ',
            ],
            'phone' => ['phone', 'tel', 'phone_number', 'mobile', 'contact', 'phone number', 'phonenumber', 'លេខទូរស័ព្ទ', 'ទូរស័ព្ទ', 'លេខទូរសព្ទ'],
            'email' => ['email', 'email_address', 'mail', 'email address', 'អ៊ីមែល'],
            'address' => ['address', 'location', 'addr', 'អាសយដ្ឋាន', 'ទីតាំង'],
            'group' => ['group', 'guest_group', 'category', 'type', 'guest group', 'group_name', 'group name', 'ក្រុម', 'ប្រភេទ'],
            'is_vip' => ['is_vip', 'vip', 'is vip', 'វីអាយភី'],
            'note' => ['note', 'notes', 'remark', 'remarks', 'comment', 'comments', 'ចំណាំ', 'ផ្សេងៗ'],
        ];

        $sequenceAliases = [
            'no', 'no.', 'nº', '#', 'id', 'item', 'num', 'number', 'seq', 'index',
            'ល.រ', 'លរ', 'លរ.', 'ល.រ.', 'លំដាប់', 'លំដាប់លេខ',
        ];

        $parsed = [];
        $errors = [];

        foreach ($sheets as $sheet) {
            $rawSheetName = $sheet['name'];
            $isGenericSheet = (bool) preg_match('/^(sheet|table|worksheet|page)\s*\d*$/i', $rawSheetName);
            $defaultSheetGroup = $isGenericSheet ? null : $rawSheetName;

            $rawRows = array_merge([$sheet['header']], $sheet['rows']);
            $allRows = [];
            foreach ($rawRows as $row) {
                $hasCell = false;
                foreach ($row as $cell) {
                    if ($cell !== null && trim((string) $cell) !== '') {
                        $hasCell = true;
                        break;
                    }
                }
                if ($hasCell) {
                    $allRows[] = array_map(fn ($cell) => $cell !== null ? trim((string) $cell) : '', $row);
                }
            }

            if (count($allRows) === 0) {
                continue;
            }

            $firstRow = $allRows[0];
            $nonEmptyCellsInFirstRow = array_values(array_filter($firstRow, fn ($c) => $c !== ''));

            $isTitleBanner = false;
            if (count($nonEmptyCellsInFirstRow) <= 2) {
                $firstRowText = implode(' ', $nonEmptyCellsInFirstRow);
                if (preg_match('/(?:ឈ្មោះភ្ញៀវ|បញ្ជីឈ្មោះ|guest\s*list|list\s*of\s*guests)/iu', $firstRowText)) {
                    $isTitleBanner = true;
                }
            }

            if ($isTitleBanner) {
                $titleText = trim(implode(' ', $nonEmptyCellsInFirstRow));
                if (! $defaultSheetGroup && $titleText !== '') {
                    $defaultSheetGroup = $titleText;
                }
                array_shift($allRows);
            }

            if (count($allRows) === 0) {
                continue;
            }

            $columnMap = [];
            $hasExplicitNameHeader = false;
            $possibleHeaderRow = $allRows[0];

            foreach ($possibleHeaderRow as $colIndex => $colTextClean) {
                if ($colTextClean === '') continue;
                $colTextLower = mb_strtolower($colTextClean);

                if (in_array($colTextLower, $sequenceAliases, true)) {
                    $columnMap[$colIndex] = '__seq__';
                    continue;
                }

                foreach ($aliases as $key => $keywords) {
                    if (in_array($colTextLower, $keywords, true)) {
                        $columnMap[$colIndex] = $key;
                        if ($key === 'name') $hasExplicitNameHeader = true;
                        break;
                    }
                }
            }

            $mappedKeys = array_values(array_filter($columnMap, fn ($k) => $k !== '__seq__'));
            $isExplicitHeaderRow = count($mappedKeys) > 0 || in_array('__seq__', $columnMap, true);

            if ($isExplicitHeaderRow && $hasExplicitNameHeader) {
                array_shift($allRows);
            } else {
                if ($isExplicitHeaderRow && ! $hasExplicitNameHeader) {
                    array_shift($allRows);
                }
                $colStats = [];
                foreach ($allRows as $r) {
                    foreach ($r as $colIdx => $val) {
                        if ($val === '') continue;
                        if (! isset($colStats[$colIdx])) {
                            $colStats[$colIdx] = ['seq' => 0, 'phone' => 0, 'email' => 0, 'text' => 0, 'total' => 0];
                        }
                        $colStats[$colIdx]['total']++;

                        if (preg_match('/^(?:#?\d+[\.\)\-\/\:]?|\d+)$/u', $val)) {
                            $colStats[$colIdx]['seq']++;
                        } elseif (preg_match('/^\+?\d[\d\s\-\.]{6,14}$/', $val)) {
                            $colStats[$colIdx]['phone']++;
                        } elseif (str_contains($val, '@')) {
                            $colStats[$colIdx]['email']++;
                        } else {
                            $colStats[$colIdx]['text']++;
                        }
                    }
                }
                foreach ($colStats as $colIdx => $stats) {
                    if (isset($columnMap[$colIdx])) continue;
                    if ($stats['seq'] / max(1, $stats['total']) > 0.4) {
                        $columnMap[$colIdx] = '__seq__';
                    } elseif ($stats['phone'] / max(1, $stats['total']) > 0.4) {
                        $columnMap[$colIdx] = 'phone';
                    } elseif ($stats['email'] / max(1, $stats['total']) > 0.4) {
                        $columnMap[$colIdx] = 'email';
                    }
                }
                $bestNameCol = null;
                $maxText = -1;
                foreach ($colStats as $colIdx => $stats) {
                    if (isset($columnMap[$colIdx])) continue;
                    if ($stats['text'] > $maxText) {
                        $maxText = $stats['text'];
                        $bestNameCol = $colIdx;
                    }
                }
                if ($bestNameCol !== null) {
                    $columnMap[$bestNameCol] = 'name';
                } else {
                    foreach ($colStats as $colIdx => $stats) {
                        if (($columnMap[$colIdx] ?? null) !== '__seq__') {
                            $columnMap[$colIdx] = 'name';
                            break;
                        }
                    }
                }
            }

            $line = 1;
            foreach ($allRows as $row) {
                $line++;
                $sheetLabel = count($sheets) > 1 ? "Sheet \"{$rawSheetName}\", Line {$line}" : "Line {$line}";

                $data = [];
                foreach ($columnMap as $colIndex => $key) {
                    if ($key === '__seq__') continue;
                    $val = isset($row[$colIndex]) ? trim((string) $row[$colIndex]) : '';
                    if ($val !== '') $data[$key] = $val;
                }

                $guestName = $data['name'] ?? null;
                if ($guestName !== null) {
                    $guestName = trim(preg_replace('/^(?:#?\d+[\.\)\-\/\:]\s*|#?\d+\s+)/u', '', $guestName));
                }

                if (! $guestName) {
                    $isRowEmpty = count(array_filter($row, fn ($cell) => $cell !== null && trim((string) $cell) !== '')) === 0;
                    if (! $isRowEmpty) {
                        $errors[] = "{$sheetLabel}: missing guest name.";
                    }
                    continue;
                }

                $parsed[] = [
                    'id' => \Illuminate\Support\Str::uuid()->toString(),
                    'name' => $guestName,
                    'phone' => $data['phone'] ?? null,
                    'email' => $data['email'] ?? null,
                    'address' => $data['address'] ?? null,
                    'note' => $data['note'] ?? null,
                    'is_vip' => filter_var($data['is_vip'] ?? false, FILTER_VALIDATE_BOOLEAN),
                    'group_name' => $data['group'] ?? $defaultSheetGroup,
                ];
            }
        }

        return ['parsed' => $parsed, 'errors' => $errors];
    }

    /**
     * Finalize the import by inserting the previewed guests into the database.
     *
     * @param list<array<string, mixed>> $guestsData
     * @return array{imported: int, skipped: int, errors: list<string>}
     */
    public function importConfirm(Wedding $wedding, array $guestsData): array
    {
        $imported = 0;
        $skipped = 0;
        $errors = [];

        $limit = PlanCapabilities::forWedding($wedding)->guestLimit;
        $remaining = $limit === null ? PHP_INT_MAX : max(0, $limit - $wedding->guests()->count());

        $groups = $wedding->guestGroups()->pluck('id', 'name')->toArray();

        DB::transaction(function () use ($wedding, $guestsData, &$groups, $limit, &$remaining, &$imported, &$skipped, &$errors) {
            foreach ($guestsData as $data) {
                if ($remaining <= 0) {
                    $skipped++;
                    $errors[] = "Plan limit of {$limit} guests reached.";
                    continue;
                }
                
                $guestName = $data['name'] ?? null;
                if (!$guestName) {
                    $skipped++;
                    $errors[] = "Missing guest name.";
                    continue;
                }

                $groupName = $data['group_name'] ?? null;
                $groupId = null;

                if ($groupName) {
                    if (isset($groups[$groupName])) {
                        $groupId = $groups[$groupName];
                    } else {
                        $newGroup = $wedding->guestGroups()->create(['name' => $groupName]);
                        $groupId = $newGroup->id;
                        $groups[$groupName] = $groupId;
                    }
                }

                $wedding->guests()->create([
                    'name' => $guestName,
                    'phone' => $data['phone'] ?? null,
                    'email' => $data['email'] ?? null,
                    'address' => $data['address'] ?? null,
                    'note' => $data['note'] ?? null,
                    'is_vip' => filter_var($data['is_vip'] ?? false, FILTER_VALIDATE_BOOLEAN),
                    'guest_group_id' => $groupId,
                ]);

                $imported++;
                $remaining--;
            }
        });

        return ['imported' => $imported, 'skipped' => $skipped, 'errors' => $errors];
    }

EOD;

file_put_contents($file, $before . $newContentStr . $after);
echo "Successfully updated GuestService.php\n";

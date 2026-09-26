<?php

declare(strict_types=1);

namespace MuchoCoreMigration;

use PDO;

final class SourceDetector
{
    /**
     * Detect a source database by schema, not by product name.
     *
     * GDPS-Maker commonly provisions a Cvolton-compatible database, so both
     * should be reported as the same import family when the schema matches.
     *
     * @return array{
     *   engine:string,
     *   label:string,
     *   confidence:string,
     *   required_tables:string[],
     *   optional_tables:string[]
     * }
     */
    public function detect(PDO $source): array
    {
        $tables = $this->tables($source);

        $hasAccounts = in_array('accounts', $tables, true);
        $hasUsers = in_array('users', $tables, true);
        $hasLevels = in_array('levels', $tables, true);
        $hasRegularScores = in_array('levelscores', $tables, true);
        $hasPlatformerScores = in_array('platscores', $tables, true);

        if ($hasAccounts && $hasUsers && $hasLevels) {
            $optional = [];
            if ($hasRegularScores) {
                $optional[] = 'levelscores';
            }
            if ($hasPlatformerScores) {
                $optional[] = 'platscores';
            }

            return [
                'engine' => 'cvolton',
                'label' => 'Cvolton / GDPS-Maker compatible',
                'confidence' => 'high',
                'required_tables' => ['accounts', 'users', 'levels'],
                'optional_tables' => $optional,
            ];
        }

        return [
            'engine' => 'unknown',
            'label' => 'Unknown GDPS schema',
            'confidence' => 'none',
            'required_tables' => [],
            'optional_tables' => [],
        ];
    }

    /**
     * @return string[]
     */
    private function tables(PDO $source): array
    {
        $rows = $source->query('SHOW TABLES')->fetchAll(PDO::FETCH_NUM);
        $tables = [];

        foreach ($rows as $row) {
            if (isset($row[0]) && is_string($row[0])) {
                $tables[] = $row[0];
            }
        }

        sort($tables, SORT_STRING);
        return $tables;
    }
}

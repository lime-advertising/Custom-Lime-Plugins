<?php

namespace LimeRM\Agent\Snapshot;

use wpdb;

class DatabaseDumper
{
    /**
     * Dump tables related to blog into SQL file.
     */
    public function dump(int $blogId, string $destination): void
    {
        global $wpdb;

        $handle = fopen($destination, 'w');

        if (! $handle) {
            throw new \RuntimeException('Unable to write database dump file.');
        }

        $prefix = $blogId > 0 ? $wpdb->get_blog_prefix($blogId) : $wpdb->prefix;
        $like = $prefix . '%';

        $tables = $wpdb->get_col($wpdb->prepare('SHOW TABLES LIKE %s', $like));

        foreach ($tables as $table) {
            $this->dumpTable($wpdb, $table, $handle);
        }

        fclose($handle);
    }

    private function dumpTable(wpdb $wpdb, string $table, $handle): void
    {
        $create = $wpdb->get_row('SHOW CREATE TABLE `' . $table . '`', ARRAY_N);

        fwrite($handle, "DROP TABLE IF EXISTS `{$table}`;\n");
        fwrite($handle, $create[1] . ";\n\n");

        $limit = 500;
        $offset = 0;

        do {
            $rows = $wpdb->get_results("SELECT * FROM `{$table}` LIMIT {$limit} OFFSET {$offset}", ARRAY_A);

            if (! $rows) {
                break;
            }

            foreach ($rows as $row) {
                $values = array_map([$this, 'formatValue'], $row);
                $columns = array_map(function ($col) {
                    return '`' . $col . '`';
                }, array_keys($row));

                $sql = sprintf(
                    'INSERT INTO `%s` (%s) VALUES (%s);',
                    $table,
                    implode(', ', $columns),
                    implode(', ', $values)
                );

                fwrite($handle, $sql . "\n");
            }

            $offset += $limit;
        } while (count($rows) === $limit);

        fwrite($handle, "\n");
    }

    private function formatValue($value): string
    {
        if ($value === null) {
            return 'NULL';
        }

        if (is_numeric($value) && ! is_string($value)) {
            return (string) $value;
        }

        return "'" . addslashes((string) $value) . "'";
    }
}

<?php

namespace Porter\Storage;

use Porter\StorageInterface;

class File implements StorageInterface
{
    /** Comment character in the import file. */
    public const COMMENT = '//';

    /** Delimiter character in the import file. */
    public const DELIM = ',';

    /** Escape character in the import file. */
    public const ESCAPE = '\\';

    /** Newline character in the import file. */
    public const NEWLINE = "\n";

    /** Null character in the import file. */
    public const NULL = '\N';

    /** Quote character in the import file. */
    public const QUOTE = '"';

    /**
     * @var array Storage for sloppy data passing.
     * @deprecated
     */
    public array $currentRow = [];

    /**
     * @var resource File pointer
     */
    public $file = null;

    /**
     * @var string The path to the export file.
     */
    public string $path = '';

    /**
     * @var bool Whether or not to use compression when creating the file.
     */
    protected bool $useCompression = true;

    /**
     * Whether or not to use compression on the output file.
     *
     * @param  bool $value The value to set or NULL to just return the value.
     * @return bool
     */
    public function useCompression($value = null)
    {
        if ($value !== null) {
            $this->useCompression = $value;
        }

        return $this->useCompression && function_exists('gzopen');
    }

    /**
     * Create the export file and begin the export.
     */
    public function begin()
    {
        // Build file name.
        $this->path = 'export_' . date('Y-m-d_His') . '.txt' . ($this->useCompression() ? '.gz' : '');

        // Start the file pointer.
        if ($this->useCompression()) {
            $fp = gzopen($this->path, 'wb');
        } else {
            $fp = fopen($this->path, 'wb');
        }
        $this->file = $fp;

        // Add meta info to the output.
        fwrite($fp, 'Nitro Porter Export' . self::NEWLINE . self::NEWLINE);
    }

    /**
     * End the export and close the export file.
     *
     * This method must be called if BeginExport() has been called or else the export file will not be closed.
     */
    public function end()
    {
        if ($this->useCompression()) {
            gzclose($this->file);
        } else {
            fclose($this->file);
        }
    }

    /**
     * Start table write to file.
     *
     * @param resource $fp
     * @param string $tableName
     * @param array $exportStructure
     */
    public function writeBeginTable($fp, $tableName, $exportStructure)
    {
        $tableHeader = '';

        foreach ($exportStructure as $key => $value) {
            if (is_numeric($key)) {
                $column = $value;
                $type = '';
            } else {
                $column = $key;
                $type = $value;
            }

            if (strlen($tableHeader) > 0) {
                $tableHeader .= self::DELIM;
            }

            if ($type) {
                $tableHeader .= $column . ':' . $type;
            } else {
                $tableHeader .= $column;
            }
        }

        fwrite($fp, 'Table: ' . $tableName . self::NEWLINE);
        fwrite($fp, $tableHeader . self::NEWLINE);
    }

    /**
     * End table write to file.
     *
     * @param resource $fp
     */
    public function writeEndTable($fp)
    {
        fwrite($fp, self::NEWLINE . self::NEWLINE);
    }

    /**
     * Write a table's row to file.
     *
     * @param resource $fp
     * @param array $row
     * @param array $structure
     * @param array $map
     * @param array $filter
     */
    public function writeRow($fp, array $row, array $structure, array $map, array $filter)
    {
        $this->currentRow =& $row;

        // Loop through the columns in the export structure and grab their values from the row.
        $exRow = array();
        foreach ($structure as $field => $dest) {
            // Get the value of the export.
            $value = $row[$field] ?? null;

            // Check to see if there is a callback filter.
            if (isset($filter[$field])) {
                $callback = $filter[$field];

                $row2 =& $row;
                $value = call_user_func($callback, $value, $field, $row2, $field);
                $row = $this->currentRow;
            }

            // Format the value for writing.
            if (is_null($value)) {
                $value = self::NULL;
            } elseif (is_integer($value)) {
                // Do nothing, formats as is.
                // Only allow ints because PHP allows weird shit as numeric like "\n\n.1"
            } elseif (is_string($value) || is_numeric($value)) {
                // Check to see if there is a callback filter.
                if (!isset($map[$field])) {
                    //$value = call_user_func($Filters[$field], $value, $field, $row);
                } else {
                    if (function_exists('mb_detect_encoding') && mb_detect_encoding($value) != 'UTF-8') {
                        $value = utf8_encode($value);
                    }
                }

                $value = str_replace(array("\r\n", "\r"), array(self::NEWLINE, self::NEWLINE), $value);
                $value = $this->escapedValue($value);
            } elseif (is_bool($value)) {
                $value = $value ? 1 : 0;
            } else {
                // Unknown format.
                $value = self::NULL;
            }

            $exRow[] = $value;
        }
        // Write the data.
        fwrite($fp, implode(self::DELIM, $exRow));
        // End the record.
        fwrite($fp, self::NEWLINE);
    }

    /**
     * Write an entire single table's data to file.
     *
     * @param string $name
     * @param array $structure
     * @param object $data
     * @param array $map
     * @param array $filter
     * @return int
     */
    public function store(string $name, array $structure, object $data, array $map = [], array $filter = []): int
    {
        $rowCount = 0;
        while ($row = $data->nextResultRow()) {
            $row = (array)$row;
            $this->currentRow =& $row;
            $rowCount++;
            $this->writeRow($this->file, $row, $structure, $map, $filter);
        }
        $this->writeEndTable($this->file);

        return $rowCount;
    }

    /**
     * Write CSV header row.
     *
     * @param string $name
     * @param array $structure
     */
    public function prepare(string $name, array $structure): void
    {
        $this->writeBeginTable($this->file, $name, $structure);
    }

    /**
     * @param $value
     * @return string
     */
    public function escapedValue($value): string
    {
        // Set the search and replace to escape strings.
        $escapeSearch = [
            self::ESCAPE, // escape must go first
            self::DELIM,
            self::NEWLINE,
            self::QUOTE
        ];
        $escapeReplace = [
            self::ESCAPE . self::ESCAPE,
            self::ESCAPE . self::DELIM,
            self::ESCAPE . self::NEWLINE,
            self::ESCAPE . self::QUOTE
        ];

        return self::QUOTE
            . str_replace($escapeSearch, $escapeReplace, $value)
            . self::QUOTE;
    }
}

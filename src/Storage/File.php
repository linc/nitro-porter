<?php

namespace Porter\Storage;

use Couchbase\Result;
use Porter\Database\ResultSet;
use Porter\ExportModel;
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
     */
    public function writeRow($fp, array $row, array $structure)
    {
        // Loop through the columns in the export structure and grab their values from the row.
        $exRow = array();
        foreach ($structure as $field => $dest) {
            // Get the value of the export.
            $value = $row[$field] ?? null;

            // Format the value for writing.
            $exRow[] = $this->formatValue($value);
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
     * @param array $map
     * @param array $structure
     * @param ResultSet $data
     * @param array $filters
     * @param ExportModel $exportModel
     * @return int
     */
    public function store(
        string $name,
        array $map,
        array $structure,
        ResultSet $data,
        array $filters,
        ExportModel $exportModel
    ): int {
        $rowCount = 0;
        while ($row = $data->nextResultRow()) {
            $rowCount++;
            $row = $exportModel->normalizeRow($map, $structure, $row, $filters);
            $this->writeRow($this->file, $row, $structure);
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

    /**
     * Format the value for file storage.
     *
     * @param mixed $value
     * @return int|string
     */
    public function formatValue($value)
    {
        if (is_integer($value)) {
            // Do nothing, formats as is.
            // Only allow ints because PHP allows weird shit as numeric like "\n\n.1"
            return $value;
        } elseif (is_string($value) || is_numeric($value)) {
            // Fix encoding if needed.
            if (function_exists('mb_detect_encoding') && mb_detect_encoding($value) != 'UTF-8') {
                $value = utf8_encode($value);
            }
            // Fix carriage returns for file storage.
            $value = str_replace(array("\r\n", "\r"), array(self::NEWLINE, self::NEWLINE), $value);
            // Fix special chars in our file storage format.
            $value = $this->escapedValue($value);
        } elseif (is_bool($value)) {
            $value = $value ? 1 : 0;
        } else {
            // Unknown format or null.
            $value = self::NULL;
        }
        return $value;
    }
}
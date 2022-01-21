<?php

/**
 * @license http://opensource.org/licenses/gpl-2.0.php GNU GPL2
 */

namespace NitroPorter;

class CommandLine
{
    public const RUN_OPTIONS = [
        // Used shortcodes: s, t, p, o, h
        'source' => [
            'Alias of a connection in the config.',
            'Req' => true,
            'Sx' => ':',
            'Short' => 's',
        ],
        'target' => [
            'Alias of a connection in the config.',
            'Req' => true,
            'Sx' => ':',
            'Short' => 't',
            'Default' => 'vanilla-csv',
        ],
        'package' => [
            'Source software package alias.',
            'Req' => true,
            'Sx' => ':',
            'Short' => 'p',
        ],
        'output' => [
            'Target software package alias.',
            'Req' => true,
            'Sx' => ':',
            'Short' => 'o',
        ],
        'help' => [
            'Show this help, duh.',
            'Short' => 'h',
        ],
        'src-prefix' => [
            'Source database table prefix.',
            'Sx' => ':',
            'Default' => '',
        ],
        'tar-prefix' => [
            'Target database table prefix.',
            'Sx' => ':',
            'Default' => '',
        ],
        'tables' => [
            'Selective export, limited to specified tables, if provided.',
            'Sx' => ':',
        ],
    ];

    /**
     * @return array
     */
    public function getGlobalOptions(): array
    {
        $options = self::RUN_OPTIONS;
        $options['package']['Values'] = array_keys(SupportManager::getInstance()->getSupport());
        return $options;
    }

    /**
     * @param bool $sections
     * @return array
     */
    public function getAllOptions($sections = false): array
    {
        $globalOptions = $this->getGlobalOptions();
        $supported = SupportManager::getInstance()->getSupport();
        $result = [];

        if ($sections) {
            $result['Global Options'] = $globalOptions;
        } else {
            $result = $globalOptions;
        }

        foreach ($supported as $type => $options) {
            $commandLine = v('options', $options);
            if (!$commandLine) {
                continue;
            }

            if ($sections) {
                $result[$options['name']] = $commandLine;
            } else {
                // We need to add the types to each command line option for validation purposes.
                foreach ($commandLine as $longCode => $row) {
                    if (isset($result[$longCode])) {
                        $result[$longCode]['Packages'][] = $type;
                    } else {
                        $row['Packages'] = array($type);
                        $result[$longCode] = $row;
                    }
                }
            }
        }

        return $result;
    }

    /**
     * @param array $options
     * @return array
     */
    public function getOptCodes(array $options): array
    {
        $shortCodes = '';
        $longCodes = array();

        foreach ($options as $longCode => $row) {
            $sx = v('Sx', $row, '');
            $short = v('Short', $row, '');

            if ($short) {
                $shortCodes .= $short . $sx;
            }
            $longCodes[] = $longCode . $sx;
        }

        return array($shortCodes, $longCodes);
    }

    /**
     * Main process for bootstrap.
     */
    public function parse(): void
    {
        // Get the inputs.
        $commandOptions = $this->getAllOptions();
        list($shortCodes, $longCodes) = $this->getOptCodes($commandOptions);
        $opts = getopt($shortCodes, $longCodes);

        // Output help.
        if (array_key_exists('help', $opts) || array_key_exists('h', $opts)) {
            $options = $this->getAllOptions(true);
            $this->outputHelp($options);
            die();
        }

        $_POST = $this->validate($opts, $commandOptions);
    }

    /**
     * @param array $values
     * @param array $options
     * @return array
     */
    public function validate($values, $options)
    {
        $errors = array();
        $result = array();

        $type = v('package', $values, v('t', $values));

        foreach ($options as $longCode => $row) {
            $req = v('Req', $row);
            $short = v('Short', $row);

            $types = v('Types', $row);

            if ($types && !in_array($type, $types)) {
                continue;
            }

            $value = null;
            if (array_key_exists($longCode, $values)) {
                $value = $values[$longCode];
                if ($value === null) {
                    $value = true;
                }
            } elseif ($short && array_key_exists($short, $values)) {
                $value = $values[$short];
                if ($value === null) {
                    $value = true;
                }
            } elseif (isset($row['Default'])) {
                $value = $row['Default'];
            }

            if ($value === null) {
                $default = v('Default', $row, null);
                if ($default === null) {
                    if ($req) {
                        $errors[] = "Missing required parameter: $longCode";
                    }

                    continue;
                } else {
                    $value = $default;
                }
            }

            if ($allowedValues = v('Values', $row)) {
                if (!in_array($value, $allowedValues)) {
                    $errors[] = "Invalid value for parameter: $longCode. Must be one of: " .
                        implode(', ', $allowedValues);
                    continue;
                }
            }

            $field = v('Field', $row, $longCode);
            $result[$field] = $value;
        }

        // Badly handle errors.
        if (count($errors)) {
            echo implode("\n", $errors) . "\n";
            die();
        }

        return $result;
    }

    /**
     * Output help to the CLI.
     *
     * @param array $options Multi-dimensional array of CLI options.
     */
    public function outputHelp(array $options)
    {
        $output = '';

        foreach ($options as $section => $options) {
            $output .= $section . "\n";

            foreach ($options as $longname => $properties) {
                $output .= $this->buildSingleOptionOutput($longname, $properties) . "\n";
            }

            $output .= "\n";
        }

        echo $output;
    }

    /**
     * Build a single line of help output for a single CLI command.
     *
     * @param string $longname
     * @param array $properties
     * @return string
     */
    public function buildSingleOptionOutput(string $longname, array $properties): string
    {
        // Indent.
        $output = "  ";

        // Short code.
        if (isset($properties['Short'])) {
            $output .= '-' . $properties['Short'] . ', ';
        }

        // Long code.
        $output .= "--$longname";

        // Align descriptions by padding.
        $output = str_pad($output, 20, ' ');

        // Whether param is required.
        if (v('Req', $properties)) {
            $output .= 'Required. ';
        }

        // Description.
        $output .= "{$properties[0]}";

        // List valid values for --type.
        if ($values = v('Values', $properties)) {
            //$output .= ' (Choose from: ' . implode(', ', $values) . ')';
        }

        return $output;
    }
}

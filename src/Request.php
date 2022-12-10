<?php

/**
 * @license http://opensource.org/licenses/gpl-2.0.php GNU GPL2
 */

namespace Porter;

class Request
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

    private static $instance = null;

    protected $request = [];

    public static function instance(): self
    {
        if (self::$instance == null) {
            self::$instance = new Request();
        }

        return self::$instance;
    }

    /**
     * Get a single value from the request.
     *
     * @param string $name
     * @return ?string
     */
    public function get(string $name): ?string
    {
        $value = null;

        if (isset($this->request[$name])) {
            $value = $this->request[$name];
        }

        return $value;
    }

    /**
     * Get all data from request.
     *
     * @return array
     */
    public function getAll(): array
    {
        return $this->request;
    }

    /**
     * @return array
     */
    public function getGlobalOptions(): array
    {
        $options = self::RUN_OPTIONS;
        $options['package']['Values'] = array_keys(Support::getInstance()->getSources());
        return $options;
    }

    /**
     * @param bool $sections
     * @return array
     */
    public function getAllOptions($sections = false): array
    {
        $globalOptions = $this->getGlobalOptions();
        $supported = Support::getInstance()->getSources();
        $result = [];

        if ($sections) {
            $result['Run Options'] = $globalOptions;
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
     * Get the request from the CLI.
     *
     * @return array
     */
    public function parseCli(): array
    {
        // Short-circuit the help flag.
        global $argv;
        if ($argv[1] === '--help') {
            Render::cliHelp();
            return [];
        }

        // Get the options for 'run'.
        $commandOptions = $this->getAllOptions();
        list($shortCodes, $longCodes) = $this->getOptCodes($commandOptions);
        $opts = getopt($shortCodes, $longCodes);

        return $this->validateRunOptions($opts, $commandOptions);
    }

    /**
     * Get the request from a web form submission.
     *
     * @return array
     */
    public function parseWeb(): array
    {
        return $_REQUEST;
    }

    /**
     * Set request data on this object.
     *
     * @param array $request
     */
    public function load(array $request): void
    {
        $this->request = $request;
    }

    /**
     * @todo Refactor logic.
     *
     * @param array $values
     * @param array $options
     * @return array
     */
    public function validateRunOptions($values, $options)
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
}

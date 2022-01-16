<?php

/**
 * @license http://opensource.org/licenses/gpl-2.0.php GNU GPL2
 */

namespace NitroPorter;

class CommandLine
{
    public function getAllCommandLineOptions($sections = false)
    {
        $globalOptions = SupportManager::getInstance()->getOptions();
        $supported = SupportManager::getInstance()->getSupport();

        if ($sections) {
            $result['Global Options'] = $globalOptions;
        } else {
            $result = $globalOptions;
        }

        foreach ($supported as $type => $options) {
            $commandLine = v('CommandLine', $options);
            if (!$commandLine) {
                continue;
            }

            if ($sections) {
                $result[$options['name']] = $commandLine;
            } else {
                // We need to add the types to each command line option for validation purposes.
                foreach ($commandLine as $longCode => $row) {
                    if (isset($result[$longCode])) {
                        $result[$longCode]['Types'][] = $type;
                    } else {
                        $row['Types'] = array($type);
                        $result[$longCode] = $row;
                    }
                }
            }
        }

        return $result;
    }

    public function getOptCodes($options)
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
     *
     * @param null $options
     * @param null $files
     * @return array
     */
    public function parseCommandLine($options = null, $files = null)
    {
        global $argv; // @see https://www.php.net/manual/en/reserved.variables.argv.php
        $globalOptions = SupportManager::getInstance()->getOptions();
        $supported = SupportManager::getInstance()->getSupport();

        if (isset($options)) {
            $globalOptions = $options;
        }

        $commandOptions = $this->getAllCommandLineOptions();

        list($shortCodes, $longCodes) = $this->getOptCodes($commandOptions);

        $opts = $this->getOptFromArgv($shortCodes, $longCodes);

        if (array_key_exists('help', $opts) || array_key_exists('h', $opts)) {
            $this->writeCommandLineHelp();
            die();
        }

        // Spawn new packages from the command line!
        if (isset($opts['spawn']) || isset($opts['s'])) {
            $name = (isset($opts['spawn'])) ? $opts['spawn'] : $opts['s'];
            $this->spawnPackage($name);
            die();
        }

        $opts = $this->validateCommandLine($opts, $commandOptions);

        if (is_array($files)) {
            $opts2 = array();
            foreach ($files as $name) {
                $value = array_pop($argv);
                if (!$value) {
                    echo "Missing required parameter: $name";
                } else {
                    $opts2[$name] = $value;
                }
            }
            if ($opts2) {
                if ($opts === false) {
                    $opts = $opts2;
                } else {
                    $opts = array_merge($opts, $opts2);
                }
            }
        }

        if ($opts === false) {
            die();
        }

        $_POST = $opts;

        return $opts;
    }

    /**
     * Take the template package, add our new name, and make a new package from it.
     *
     * @param string $name
     */
    public function spawnPackage($name)
    {

        if ($name && strlen($name) > 2) {
            $name = preg_replace('/[^A-Za-z0-9]/', '', $name);
            $template = file_get_contents(__DIR__ . '/../tpl_package.txt');
            file_put_contents(__DIR__ . '/../Package/' . $name . '.php', str_replace('__NAME__', $name, $template));
            echo "Created new package: " . $name . "\n";
        } else {
            echo "Invalid name: 2+ alphanumeric characters only.";
        }
    }

    /**
     * Basically does the same thing than getopt() with one minor difference.
     *
     * The difference is that an empty option (-o="" || -o= || --option="" || --option=)
     * will show up in the result as if the the option is set with an empty value.
     *
     * @see getopt
     *
     * @param  $shortCodes
     * @param  $longCodes
     * @return array
     */
    public function getOptFromArgv($shortCodes, $longCodes)
    {
        global $argv; // @see https://www.php.net/manual/en/reserved.variables.argv.php

        $options = array();

        $shortCodesArray = array();
        $longCodesArray = array();

        $matches = array();
        if (
            strlen($shortCodes) > 1 &&
            preg_match_all('#([a-z\d])(:{0,2})#i', $shortCodes, $matches, PREG_SET_ORDER) != false
        ) {
            foreach ($matches as $match) {
                $shortCodesArray[$match[1]] = strlen($match[2]);
            }
        }

        foreach ($longCodes as $longCode) {
            $explodedLongCode = explode(':', $longCode);
            $longCodesArray[$explodedLongCode[0]] = count($explodedLongCode) - 1;
        }

        $argvCount = count($argv);
        for ($i = 1; $i < $argvCount; $i++) {
            $currentArg = $argv[$i];

            $matches = array();
            if (preg_match('#^(-{1,2})([a-z\d]+)(?:=(.*))?$#i', $currentArg, $matches) === 1) {
                $optionType = $matches[1];
                $optionName = $matches[2];

                $optionValue = isset($matches[3]) ? $matches[3] : null;

                if ($optionType === '-') {
                    $argType = 'short';
                } else {
                    $argType = 'long';
                }

                if (!isset(${$argType . 'CodesArray'}[$optionName])) {
                    continue;
                }

                $optionValueRequirement = ${$argType . 'CodesArray'}[$optionName];

                if ($optionValueRequirement === 0) { // 0 = No value permitted
                    if ($optionValue !== null) {
                        continue;
                    }
                } elseif ($optionValueRequirement === 1) { // 1 = Value required
                    if ($optionValue === null) {
                        continue;
                    }
                }

                $options[$optionName] = $optionValue;
            }
        }

        return $options;
    }

    public function validateCommandLine($values, $options)
    {
        $errors = array();
        $result = array();

        $type = v('type', $values, v('t', $values));

        foreach ($options as $longCode => $row) {
            $req = v('Req', $row);
            $short = v('Short', $row);

            $types = v('Types', $row);

            if ($types && !in_array($type, $types)) {
                continue;
            }

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
            } else {
                $value = null;
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

        if (count($errors)) {
            echo implode("\n", $errors) . "\n";

            return false;
        }


        return $result;
    }

    public function writeCommandLineHelp($options = null, $section = '')
    {
        if ($options === null) {
            $options = $this->getAllCommandLineOptions(true);
            foreach ($options as $section => $options) {
                $this->writeCommandLineHelp($options, $section);
            }

            return;
        }

        echo "$section\n";
        foreach ($options as $longname => $options) {
            $output = "  ";

            if (isset($options['Short'])) {
                $output .= '-' . $options['Short'] . ', ';
            }

            $output .= "--$longname";

            // Align our descriptions by passing
            $output = str_pad($output, 18, ' ');

            if (v('Req', $options)) {
                $output .= 'Required. ';
            }

            $output .= "{$options[0]}\n";

            if ($values = v('Values', $options)) {
                $output .= '    Valid Values: ' . implode(', ', $values) . "\n";
            }

            echo $output;
        }

        echo "\n";
    }
}

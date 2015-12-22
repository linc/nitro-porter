<?php

$globalOptions = array(
    // Used shortcodes: t, n, u, p, h, x, a, c, f, d, o, s
    'type' => array(
        'Type of forum we\'re freeing you from.',
        'Req' => true,
        'Sx' => ':',
        'Field' => 'type',
        'Short' => 't'
    ),
    'dbname' => array('Database name.', 'Req' => true, 'Sx' => ':', 'Field' => 'dbname', 'Short' => 'n'),
    'user' => array('Database connection username.', 'Req' => true, 'Sx' => ':', 'Field' => 'dbuser', 'Short' => 'u'),
    'password' => array(
        'Database connection password.',
        'Sx' => '::',
        'Field' => 'dbpass',
        'Short' => 'p',
        'Default' => ''
    ),
    'host' => array(
        'IP address or hostname to connect to. Default is 127.0.0.1.',
        'Sx' => ':',
        'Field' => 'dbhost',
        'Short' => 'o',
        'Default' => '127.0.0.1'
    ),
    'prefix' => array(
        'The table prefix in the database.',
        'Field' => 'prefix',
        'Sx' => ':',
        'Default' => '',
        'Short' => 'x'
    ),
    'avatars' => array(
        'Enables exporting avatars from the database if supported.',
        'Sx' => '::',
        'Field' => 'avatars',
        'Short' => 'a',
        'Default' => ''
    ),
    'cdn' => array(
        'Prefix to be applied to file paths.',
        'Field' => 'cdn',
        'Sx' => ':',
        'Short' => 'c',
        'Default' => ''
    ),
    'files' => array(
        'Enables exporting attachments from database if supported.',
        'Sx' => '::',
        'Short' => 'f',
        'Default' => ''
    ),
    'destpath' => array('Define destination path for the export file.', 'Sx' => '::', 'Short' => 'd', 'Default' => ''),
    'spawn' => array('Create a new package with this name.', 'Sx' => '::', 'Short' => 's', 'Default' => ''),
    'help' => array('Show this help, duh.', 'Short' => 'h'),
    'tables' => array(
        'Selective export, limited to specified tables, if provided',
        'Sx' => ':',
        'Short' => 's',
        'Default' => ''
    )
);

// Go through all of the supported types and add them to the type description.
if (isset($supported)) {
    $globalOptions['type']['Values'] = array_keys($supported);
}

function getAllCommandLineOptions($sections = false) {
    global $globalOptions, $supported;

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

function getOptCodes($options) {
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

function parseCommandLine($options = null, $files = null) {
    global $globalOptions, $supported, $argv;

    if (isset($options)) {
        $globalOptions = $options;
    }
    if (!isset($globalOptions)) {
        $globalOptions = array();
    }
    if (!isset($supported)) {
        $supported = array();
    }

    $commandOptions = getAllCommandLineOptions();
    list($shortCodes, $longCodes) = getOptCodes($commandOptions);

//   print_r($LongCodes);

    $opts = getopt($shortCodes, $longCodes);

    if (isset($opts['help']) || isset($opts['h'])) {
        writeCommandLineHelp();
        die();
    }

    // Spawn new packages from the command line!
    if (isset($opts['spawn']) || isset($opts['s'])) {
        $name = (isset($opts['spawn'])) ? $opts['spawn'] : $opts['s'];
        spawnPackage($name);
        die();
    }

    $opts = validateCommandLine($opts, $commandOptions);

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

function validateCommandLine($values, $options) {
    $errors = array();
    $result = array();

//   print_r($Values);
//   print_r($Options);

    $type = v('type', $values, v('t', $values));

    foreach ($options as $longCode => $row) {
        $req = v('Req', $row);
        $short = v('Short', $row);

        $sx = v('Sx', $row);
        $types = v('Types', $row);

        if ($types && !in_array($type, $types)) {
//         echo "Skipping $LongCode\n";
            continue;
        }

        if (isset($values[$longCode])) {
            $value = $values[$longCode];
            if (!$value) {
                $value = true;
            }
        } elseif ($short && isset($values[$short])) {
            $value = $values[$short];
            if (!$value) {
                $value = true;
            }
        } elseif (isset($row['Default'])) {
            $value = $row['Default'];
        } else {
            $value = null;
        }

        if (!$value) {
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
                $errors[] = "Invalid value for parameter: $longCode. Must be one of: " . implode(', ', $allowedValues);
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

function writeCommandLineHelp($options = null, $section = '') {
    if ($options === null) {
        $options = getAllCommandLineOptions(true);
        foreach ($options as $section => $options) {
            writeCommandLineHelp($options, $section);
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

function v($name, $array, $default = null) {
    if (isset($array[$name])) {
        return $array[$name];
    }

    return $default;
}

?>

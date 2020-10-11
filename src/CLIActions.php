<?php

/**
 * File for class CLIActions.
 *
 * @author    Proximify Inc <support@proximify.com>
 * @copyright Copyright (c) 2020, Proximify Inc
 * @license   MIT
 * @version   1.1.0 CLIActions Library
 */

namespace Proximify;

/**
 * Interactive command-line interface (CLI) to manage Composer events and
 * regular CLI events.
 *
 * The actions and their parameters are defined in JSON files located in a
 * settings/cli-actions folder in the working directory where composer is run.
 *
 * In composer.json, add
 * "scripts": {
 *       "ACTION-NAME1": "Proximify\\CLIActions::auto",
 *       "ACTION-NAME2": "Proximify\\CLIActions::auto",
 *       ...
 *   }
 *
 * In the terminal, run:
 *    composer ACTION-NAME ARGUMENT ARGUMENT ... -- --KEY=VALUE --KEY=VALUE ...
 *
 * - ACTION-NAME: one of the script actions defined in composer.json.
 * - ARGUMENT: actions-specific argument that is defined by its position. That is,
 *   first argument, second argument and so on have each a specific meaning.
 * - KEY-VALUE: action-specific named argument with a value or with no value (for
 *   boolean arguments that are set to true).
 */
class CLIActions
{
    /** @var string Special key for arguments. */
    const ARGS_KEY = 'arguments';

    /** @var array<string> Names of all standard Composer actions. */
    const COMPOSER_ACTIONS = [
        'install', 'update', 'status', 'archive', 'create-project', 'dump-autoload'
    ];

    /** @var string Name of this package. */
    const PACKAGE_NAME = 'cli-actions';

    /**
     * Magic method triggered when invoking inaccessible methods in a
     * static context.
     *
     * If triggered by composer, the first argument will be an Event.
     *
     * @link https://github.com/composer/composer/tree/master/src/Composer
     *
     * $event = $arguments[0] ?? null;
     *
     * $action = $event->getName();
     * $options = $event->getArguments();
     * $flags = $event->getFlags();
     * $isDev = $event->isDevMode();
     * // Get the "extra" parameters in composer.json
     * $extra = $event->getComposer()->getPackage()->getExtra();
     *
     * @param string $name The name of the method.
     * @param array $arguments The arguments of the method.
     * @return mixed
     */
    public static function __callStatic(string $name, array $arguments)
    {
        // Get command-line options. Works with and without Composer.
        [$action, $options] = self::getopt();

        // Check that the method called and the CLI action match.
        // Method names treat ':' as '-' so action A:B a calls method A-B.
        if (str_replace(':', '-', $action) != $name) {
            if (in_array($action, self::COMPOSER_ACTIONS)) {
                $action = $name;
            } else {
                // Unexpected type of missing method. Bail out.
                return null;
            }
        }

        // If the action is in the form A:B, the A part is an app namespace.
        // App namespaces map to settings sub-folders
        $action = str_replace(':', '/', $action);

        // Get the definition of arguments for the given action
        $cmd = self::readCommandSchema($action);

        // Load Compose parameters defined under the "extras" key of the
        // composer.json file.
        if ($event = $arguments[0] ?? false) {
            if (is_a($event, 'Composer\\Script\\Event', false)) {
                $extra = $event->getComposer()->getPackage()->getExtra();

                if (is_array($extra)) {
                    $options = $extra + $options;
                }

                // Pass down the event as a special option
                $options['_event'] = $event;

                // $composer = $event->getComposer();
                // $options['_env'] = [
                //     'event' => $event,
                //     'vendor-dir' => $composer->getConfig()->get('vendor-dir'),
                //     // 'extra' => $composer->getPackage()->getExtra(),
                // ];
            }
        }

        // Run the action command with the given options
        return self::runCommand($cmd, $options);
    }

    /**
     * Get the absolute path to the folder with action definitions.
     * 
     * The default is to return ROOT-DIR/settings/cli, where ROOT-DIR
     * is assumed to be 2 levels up from the **called class** filename. 
     * 
     * Override this method if the default path is not appropriate.
     * 
     * @return string Path to "settings" folders to visit.
     */
    public static function getActionFolder(): string
    {
        // dirname(__DIR__) would work for this class, but not for extended ones
        $refClass = new \ReflectionClass(get_called_class());

        // Assuming root/src/filename, move 2 levels up... 
        $rootDir = dirname($refClass->getFileName(), 2);

        return "$rootDir/settings/" . self::PACKAGE_NAME;
    }

    /**
     * Trigger a method with the name of the given event.
     * It provides an easy way to define several composer events
     * and map all of them to the same handler.
     *
     * @param Composer\Script\Event $event A composer event.
     * @return void
     */
    public static function auto($event)
    {
        // Disable the Composer timeout when running in auto mode
        if (class_exists('Composer\Config', false)) {
            \Composer\Config::disableProcessTimeout();
        }

        // Treat an app namespaces, A:, as a method prefix A-.
        $name = str_replace(':', '-', $event->getName());

        // The composer event is the first parameter when methods are
        // called directly, so kep the same format.
        $args = $event->getArguments();
        array_unshift($args, $event);

        return static::__callStatic($name, $args);
    }

    /**
     * Dummy action to test basic functionality.
     *
     * @return void
     */
    public function dummyTestAction(array $options): void
    {
        echo "\nThe dummy test works!\n\n";
    }

    /**
     * Undocumented function
     *
     * @param array $cmd
     * @param array $options
     * @return mixed
     */
    public static function runCommand(array $cmd, array $options)
    {
        // Complete missing options with default values and with
        // values provided by the user interactively.
        self::readInteractiveOptions($cmd, $options);

        // Get the selected $key option
        if ($cmdKey = $cmd['commandKey'] ?? false) {
            $cmdVal = $options[$cmdKey] ?? false;

            // Find the class and method for the selected $key
            $data = $cmd[self::ARGS_KEY][$cmdKey]['options'][$cmdVal] ?? [];

            $class = $data['class'] ?? get_called_class();
            $method = $data['method'] ?? false;
        } else {
            $class = $cmd['class'] ?? get_called_class();
            $method = $cmd['method'] ?? false;
        }

        if (!$class || !$method) {
            self::throwError("Invalid empty class or method", $cmd);
        }

        // If required, ask the user for confirmation to proceed
        if (!empty($cmd['askConfirm']) && !self::confirm()) {
            return;
        } elseif (is_a($class, self::class)) {
            // Avoid accidental recursion by check that it's not an 'auto' 
            // method when the class is a CLIActions (self) class.
            if ($method == 'auto' || !method_exists($class, $method)) {
                self::throwError("Invalid self method '$method'");
            }
        }

        self::echoMsg("> $class::$method", ['separator' => true]);

        $isStatic = (new \ReflectionMethod($class, $method))->isStatic();

        return $isStatic ?
            $class::$method($options) : (new $class())->$method($options);
    }

    /**
     * Runs the command-line action. Useful to run commands without Composer.
     *
     * @param string|null The name of a default action to use if no action is
     * given from the command line. The default fallback is 'build'.
     * @return mixed
     */
    public static function run(?string $defaultAction = null)
    {
        [$action, $options] = self::getopt();

        if (!$action) {
            $action = $defaultAction ?: 'build';
        }

        return self::$action();
    }

    /**
     * Get the current options set from the command line.
     *
     * @return array
     */
    private static function getopt(): array
    {
        $args = $_SERVER['argv'] ?? [];
        $cmd = $args ? array_shift($args) : '';
        $action = $args ? array_shift($args) : '';
        $options = [];

        foreach ($args as $index => $arg) {
            if (substr($arg, 0, 2) == '--') {
                if ($arg = substr($arg, 2)) {
                    $parts = explode('=', $arg);
                    $options[$parts[0]] = $parts[1] ?? true;
                }
            } else {
                $options[$index] = $arg;
            }
        }

        return [$action, $options];
    }

    /**
     * Ask the user for a value.
     *
     * @param array $info Details for the prompt and response validation.
     * @return string The response (if valid) or the empty string.
     */
    private static function prompt(array $info): string
    {
        $prompt = $info['prompt'] ?? 'QUESTION?';
        $options = $info['options'] ?? [];

        if ($options) {
            if (($info['displayType'] ?? '') == 'list' &&
                self::isAssocArray($options)
            ) {
                $prompt .= "\n";

                foreach ($options as $value) {
                    $label = $value['label'] ?? $value;

                    $prompt .= $label . "\n";
                }

                $options = array_keys($options);
            } else {
                if (self::isAssocArray($options)) {
                    $options = array_keys($options);
                }

                $prompt .= ' [' . implode('|', $options) . ']';
            }
        }

        do {
            self::echoMsg("\n$prompt - ", ['newline' => false]);

            $stdin = fopen('php://stdin', 'r');
            $response = trim(fgets($stdin));

            //select value by a number instead of the real value.
            $selectByIndex = $info['selectByIndex'] ?? null;

            if ($selectByIndex && is_numeric($response)) {
                //index is started by 1.
                $response = $options[$response - 1] ?? $response;
            }

            if ($response && $options && !in_array($response, $options)) {
                self::echoMsg("Invalid option");
                $valid = false;
            } else {
                $valid = true;
            }
        } while (!$valid);

        return $response;
    }

    /**
     * Undocumented function
     *
     * @param array $args
     * @param array $params
     * @return void
     */
    private static function readInteractiveOptions(array $args, array &$params): void
    {
        if (isset($args[self::ARGS_KEY])) {
            $args = $args[self::ARGS_KEY];
        }

        foreach ($args as $name => $info) {
            // Some arguments are defined by their position (index).
            // script-name action param param ... --arg=xyz --arg=xyz
            $index = $info['index'] ?? null;
            $value = $params[$name] ?? null;
            $options = $info['options'] ?? false;

            if ($value === null && $index !== null) {
                // The index is an alias for the named argument
                $value = $params[$index] ?? null;
                $params[$name] = $value;
            }

            // Skip arguments that have a valid value
            if ($value !== null) {
                if (!$options) {
                    continue;
                }

                if (in_array($value, $options) || isset($options[$value])) {
                    if (is_array($options[$value])) {
                        self::readInteractiveOptions($options[$value], $params);
                    }

                    continue;
                }
            }

            //the info is just meta data instead of deeper options.
            if (is_array($info)) {
                $value = $info['value'] ?? self::prompt($info);

                $params[$name] = $value;

                if (isset($options[$value]) && is_array($options[$value])) {
                    self::readInteractiveOptions($options[$value], $params);
                }
            }
        }
    }

    /**
     * Find the file name of a settings file with given relative path from
     * the settings folder. Consider several root directories. First, the
     * current working directory, second the one relative to the called class,
     * and lastly, the one relative to this base class.
     *
     * @param string $relPath
     * @return string|null
     */
    private static function findSettingsFile(string $relPath): ?string
    {
        $dirs = [];
        $class = get_called_class();

        // Add the methods of the called class and its ancestor classes
        do {
            $dirs[] = $class::getActionFolder();
        } while ($class = get_parent_class($class));

        // As a fallback, consider a default settings folder
        // in current working directory of the called script
        $dirs[] = getcwd() . '/settings/cli';

        // Eliminate duplicate folders
        $dirs = array_unique($dirs);

        foreach ($dirs as $dir) {
            $path = "$dir/$relPath";

            if (is_file($path)) {
                return realpath($path);
            }
        }

        return null;
    }

    /**
     * Read the definition of commands arguments form the settings.
     *
     * @param string $cmdName The target command name.
     * @return array A map of argument names to argument definitions.
     */
    private static function readCommandSchema(string $cmdName): array
    {
        $relPath = "$cmdName.json";
        $filename = self::findSettingsFile($relPath);

        if (!$filename) {
            self::throwError("Cannot find command file '$relPath'");
        }

        $data = self::readJSONFile($filename);

        if (isset($data[self::ARGS_KEY])) {
            $args = &$data[self::ARGS_KEY];
        } else {
            $args = &$data;
        }

        foreach ($args as $name => $specs) {
            $class = $specs['class'] ?? '';
            $method = $specs['method'] ?? '';

            //get options dynamically if class and method are provided
            if ($class && $method) {
                $handler = new $class();
                $options = $handler->$method();

                $args[$name]['options'] = $options;
            } else {
                $options = $specs['options'] ?? false;
            }

            if (!$options || !self::isAssocArray($options)) {
                continue;
            }

            // Each option can point to a filename to lead, be set to true
            // to use a default filename, or be false to skip the option.
            foreach ($options as $key => $value) {
                if ($value === true) {
                    // Define create a file name from the command and option
                    $value = $cmdName . '/' . $key;
                } elseif ($value === false) {
                    // The option is disabled
                    unset($args[$name]['options'][$key]);
                    continue;
                }

                // Load a file with the nested specs if $value is an string
                $args[$name]['options'][$key] = ($value && is_string($value)) ?
                    self::readCommandSchema($value) : $value;
            }
        }

        return $data;
    }

    /**
     * Ask the user for conformation to proceed.
     *
     * @return boolean True if the users answered 'y' and false otherwise.
     */
    private static function confirm(): bool
    {
        $cmd = self::readCommandSchema('confirm');
        $options = [];

        self::readInteractiveOptions($cmd, $options);

        return $options['status'] === 'y';
    }

    /**
     * Check if there is at least one string key. If so, $a is associative.
     *
     * @param array $a The array to evaluate.
     * @return boolean True if and only if $array has at least one string key.
     */
    private static function isAssocArray(array $a): bool
    {
        // Move the array's internal pointer to the first non INT key.
        for (reset($a); is_int(key($a)); next($a));

        // If it's not the array's end, then it's not associative.
        return !is_null(key($a));
    }

    /**
     * Undocumented function
     *
     * @param string $filename
     * @return mixed
     */
    private static function readJSONFile(string $filename)
    {
        $data = file_get_contents($filename);

        if ($data === null) {
            self::throwError("Cannot read file '$filename'");
        }

        return json_decode($data, true, 512, JSON_THROW_ON_ERROR);
    }

    /**
     * Throw an error message.
     *
     * @param string $msg The message to display.
     * @param array $context Optional contextual data.
     * @return void
     */
    protected static function throwError(string $msg, array $context = []): void
    {
        if ($context) {
            $msg = "\n" . print_r($context, true);
        }

        $className = static::class;

        throw new \Exception("$className:\n$msg");
    }

    /**
     * Echo a message to the console.
     *
     * @param string $msg The message to echo.
     * @param array $options Boolean options: separator and newline. If true,
     * they are added to the output as a suffix.
     * @return void
     */
    protected static function echoMsg(string $msg, array $options = []): void
    {
        if ($options['separator'] ?? false) {
            $msg .= "\n" . str_repeat('-', min(80, strlen($msg)));
        }

        if ($options['newline'] ?? true) {
            $msg .= "\n";
        }

        echo $msg;
    }
}

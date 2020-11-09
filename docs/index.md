# CLI Actions

Command-line interface (CLI) with custom actions and interactive prompts (e.g. composer app:action argument).

## Objective

The standard `composer.json` file allows for the declaration of CLI scripts. The scripts can then be called from the terminal. For example,

```bash
$ composer action-name arg1 arg2 ...
```

calls the static method `action-name` of a PHP script registered in the `composer.json`.

The procedure to declare the custom [Composer scripts](https://getcomposer.org/doc/articles/scripts.md) is simple but a bit repetitive. **CLI Actions** simplifies that process and adds functionality to create rich interactive CLI behaviors that are defined in JSON setting files.

## Getting started

Add the **CLI Actions** to your project.

```bash
$ composer require proximify/cli-actions
```

Then, add the scripts that you want to enable from the CLI.

In your project's `composer.json`, add

```json
"scripts": {
     "ACTION-NAME-1": "Proximify\\CLIActions::auto",
     "ACTION-NAME-2": "Proximify\\CLIActions::auto",
     "...": "..."
 }
```

All actions can map to the same "auto" method of **CLI Actions**. That is one way in which **CLI Actions** simplifies the declaration of custom scripts.

The next step is to define what script to run and with what parameters. The arguments to a script can also be provided via the CLI and/or by prompting the user for them. The prompter checks what arguments were given in the CLI and only prompts the user for the missing ones.

The **action definitions** are JSON files that define the prompts for each argument and the acceptable values for them. In addition, the arguments can require sub-arguments based on the options selected for them. **CLI Actions** allows for nested definitions of arguments and sub-arguments.

The actions and their parameters are defined in JSON files. By default, the files in a root-level `settings/cli` folder are considered. The name of the file must match the name of the action. Each file defines a possible CLI action and their arguments.

```
MyProject
├── settings
│   └── cli-actions
│       ├── action1.json
│       ├── action1
│       │   └── sub-action1.json
│       └── namespaceA
│           ├── action-1.json
│           └── action-2.json
├── src
│   ├── helpers.php
│   └── MyProjectCLI.php
└── composer.json
```

### Action definition object

-   `class` **String** - An optional, fully qualified, class name. If not given, the method is assumed to belong to the calling (\$this) object.
-   `method` **String** - A callback method of the selected class that will be called to execute the action. The method can be static or dynamic. The method recieves an array with all user options and an array with environment options `method-name(array $options, array $env)`. A ReflectionMethod is used to determine how to call the method of the given class. If the method is dynamic, an object of the class is created by invoking its constructor with no arguments.
-   `askConfirm` **Boolean** - Whether to ask the user for confirmation to execute the action.
-   `value` **String** - An optional "default" value to assume of the argument is not given. When a default value is defined, the user is not prompted for the value if the argument is not given in the CLI.
-   Proceed the action based on the argument which is declared in the `commandKey`.
-   `arguments` **Object** - KEY/VALUE pairs with the definition of all the arguments needed to execute the action. The key of each argument item is the **name** of the argument and the value is an object that collects the information of the argument. The valid options of the argument are as below:
    -   `prompt` **String** - A message requesting a value for the argument. It is shown to the user iteratively when an argument value is not already present from the CLI options provided by the user. That is, the prompter checks what arguments were given in the CLI and only prompts the user for the missing ones. The message instructs the user to input the value.
    -   `index` **Integer** - An optional **CLI position** for the argument. It is the absolute position with respect to all the unnamed arguments given in the CLI (starting from 0). If given, it means that the argument can be specified by name or position. If not given, the argument can only be specified by name.
    -   `displayType` **String** - Enum of **array** and **list**. The way to preset the prompt message.
    -   `options` **Array** - An array of strings or of [option definitions](#Option-definition). A user can select the value from the given options.
    -   `selectByIndex` **Boolean** - Wether to allow the user to input the index of an option instead of its value in order to select an option when prompted for it.
-   `commandKey` **String** - Optional **argument name** in `arguments`. It is an advanced behavior use to defined the `class` and `method` of the action based on a user-selected option from the given argument. For example, the action `create` might have a `type` arguments with options `widget` and `plugin`. The class and method used by create depend on whether the user wants to create a widget or a plugin. In this case, `commandKey=type` says that the class and method definition are to be read from the sub-action defined by `widget` or `plugin` once the user selection is known.

### Option definition

The options of an **action argument** can be can defined in terms of an **option definition object** with Key/Value pairs representing a map from a sub-action name to a nested sub-action defined inline or in another file:

1. **Inline** - An object with a nested [action definition](#action-definition-object);
2. **External** - The boolean **true** is interpreted as a nested sub-action defined in another file located at **config/cli-actions/MAIN-ACTION/SUB-ACTION/.../SUB-ACTION**.

#### Additional Properties

In addition to [action definition](#action-definition-object) properties, an **option definition** allows for the following properties:

-   `label` **String** - The displayed name of the option used when prompting the user for a selection.

### Example action definition

```yml
{
    'class': 'Proximify\\Glot\\Builder\\BuilderCLI',
    'method': 'create',
    'commandKey': 'type',
    'askConfirm': true,
    'arguments':
        {
            'type':
                {
                    'options':
                        {
                            'host': true,
                            'widget': true,
                            'plugin': true,
                            'streamer': true,
                            'provider': true,
                            'dataSource': true,
                        },
                    'prompt': 'Type of component to create?',
                    'index': 0,
                },
            'name': { 'prompt': 'Name of the component?' },
            'verbose': { 'value': true },
        },
}
```

```yml
class: Proximify\Glot\Builder\BuilderCLI
method: delete
commandKey: type
askConfirm: true
arguments:
    type:
        options:
            host:
                class: Proximify\Glot\Publisher\Publisher
                method: deleteHost
                arguments:
                    label:
                        Delete a host will delete the host folder in you project, delete
                        the related remote in rclone and delete the bucket in the host.
            widget:
                label: Delete the widget.
            plugin:
                label: Delete the plugin
            streamer:
                label: Delete the streamer
            provider:
                label: Delete the provider
        prompt: Type of component to delete?
        index: 0
    name:
        prompt: Name of the component?
```

```json
{
    "class": "SOME-NAMESPACE\\SOME-CLASS-NAME",
    "method": "SOME-METHOD-NAME",
    "askConfirm": true,
    "arguments": {
        "type": {
            "options": ["a", "b"],
            "prompt": "What dummy type do you want?",
            "index": 0
        },
        "name": {
            "prompt": "Name of the dummy type?"
        },
        "verbose": {
            "value": true
        }
    }
}
```

<!-- ### Action schema

There is a [schema for action definitions](docs/action_schema.md) that explains all valid options. -->

### Creating a CLI for your Composer packages

Let's say you have a compose package named Publisher and you want to create a CLI for it. To do that, you have to create a class that extends the CLIActions. Let's call it, PublisherCLI. The only task of that class is to define the folders where the actions definitions are located.

```php
namespace XYZ;

class PublisherCLI extends \Proximify\CLIActions
{
    static public function getActionFolder(): string
    {
        // E.g. own settings folder, one level up from __DIR__
        return dirname(__DIR__) . '/settings/cli';
    }
}
```

That's it!

> The action folder of ancestor classes are added recursively. That is, if the example PublisherCLI class extends another class that also has CLI actions, both paths return will be considered when searching for actions. Child paths are considered before parent paths.

In your project's `composer.json` use your own CLI class

```json
"scripts": {
     "ACTION-NAME-1": "XYZ\\PublisherCLI::auto",
     "ACTION-NAME-2": "XYZ\\PublisherCLI::auto",
     "...": "..."
 }
```

By using your class, you will be adding your own action definitions and that of the ancestor classes of your class.

#### Action namespaces

It is recommended to use namespaces when defining actions. In the command line, an action namespace is given in the form A:B, where A is the namespace and B is the action name. The namespaces are evaluated as sub-folders. For example, if the command is

```bash
$ composer app:update
```

CLI Actions will try to load a settings file named `update.json` in a parent folder named `app`. By default, that would be the path `settings\cli\app\update.json`.

Using **action namespaces** is recommended in order to avoid collisions with standard composer actions, such as _update_ and _install_.

#### The Composer event object

When a CLI Actions method is called as a result of a Composer **event**, a `Composer\Script\Event` object is provided as argument to the **script callbacks** defined in the `composer.json` file.

In some situations, the event object is needed by the **action callbacks**, so it is passed to them as an environment property named `event`. The **event** object can be used to obtain the Composer app object as well as information about the project.

```php
public function someActionCallback(array $options, array $env)
{
    // Note: 'event' exits only for Composer-triggered actions
    $event = $env['event'];

    // Get the Composer app object
    $composer = $event->getComposer();

    // Get the path to the project's vendor folder
    $vendorDir = $composer->getConfig()->get('vendor-dir');

    // Get the "extra" property of the composer.json
    $extra = $composer->getPackage()->getExtra();

    // Get the package being installed or updated
    $installedPackage = $event->getOperation()->getPackage();

    // Output a message to the console
    $event->getIO()->write("Some message");
}
```

#### Pre-stored extra arguments

The schema of a `composer.json` file allows for a special [extra](https://getcomposer.org/doc/04-schema.md#extra) property.

    extra: Arbitrary extra data for consumption by scripts.

**CLI Actions** automatically sets \$env['extra'] to the `extra` property of `composer.json`.

The `extra` property in `composer.json` can be used to set default values for arguments or to provide additional arguments that are constant for a given project.

#### Action methods

In addition to action definitions provided via JSON files, it is possible to defined custom methods directly by extending the CLIActions class. The method can be declared by name in `composer.json`. For example, assuming that PublisherCLI extends CLIActions, a `methodName` can be given as an action callback by:

```json
"scripts": {
     "ACTION-NAME-1": "XYZ\\PublisherCLI::methodName"
 }
```

The existence of the public method `methodName` is checked first and, only if it doesn't exist, a JSON action file is evaluated.

<!-- #### Validating VSCode action definitions

A Visual Studio Code schema is provided in `schemas/cli-actions.json` and declared in `.vscode/settings.json`. -->

### Standard Composer Events

Composer dispatches several [named events](https://getcomposer.org/doc/articles/scripts.md#event-names) during its execution process. All callbacks for all those events can be defined in by JSON action files named as the event the represent.

For example, to defined an action for the **post-create-project-cmd**, which occurs after the `create-project` command has been executed, simple create a JSON file named `post-create-project-cmd.json` and set the script callback for the event in the `composer.json` file.

In `composer.json`

```json
"post-create-project-cmd": "Proximify\\CLIActions::auto"
```

In `settings/cli/post-create-project-cmd.json`

```json
{
    "class": "XYZ\\MyClass",
    "method": "methodName"
}
```

## Example projects

Projects using **CLI Actions**.

-   [Uniweb API](https://packagist.org/packages/proximify/uniweb-api): default behavior (without custom a CLI class).
-   [Foreign Packages](https://packagist.org/packages/proximify/foreign-packages): definition of actions for standard composer events.
-   [GLOT Builder](https://packagist.org/packages/proximify/glot-builder): defines a CLI class.
-   [GLOT Publisher](https://packagist.org/packages/proximify/glot-publisher): defines two-levels of custom CLI classes.

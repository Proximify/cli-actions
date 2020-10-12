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
│   └── cli
│       ├── action1.json
│       ├── action1
│       │   └── sub-action1.json
│       └── action2.json
├── src
│   ├── helpers.php
│   └── MyProjectCLI.php
└── composer.json
```

### Example action definition

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

> The **action callback method** can be static or dynamic. A ReflectionMethod is used to determine how to call the method of the given class. If the method is dynamic, an object of the class is created by invoking its constructor with no arguments.

### Action schema

There is a [schema for action definitions](docs/action_schema.md) that explains all valid options.

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

In some situations, the event object is needed by the **action callbacks**, so it is passed to them as an option property named `_event`. The **event** object can be used to obtain the Composer app object as well as information about the project.

```php
public function someActionCallback(array $options)
{
    // Note: '_event' exits only for Composer-triggered actions
    $event = $options['_event'];

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

**CLI Actions** automatically merges the CLI arguments with the `extra` property of `composer.json` with priority given to the former.

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

---

## Contributing

This project welcomes contributions and suggestions. Most contributions require you to agree to a Contributor License Agreement (CLA) declaring that you have the right to and actually do, grant us the rights to use your contribution. For details, visit our [Contributor License Agreement](https://github.com/Proximify/community/blob/master/docs/proximify-contribution-license-agreement.pdf).

When you submit a pull request, we will determine whether you need to provide a CLA and decorate the PR appropriately (e.g., label, comment). Simply follow the instructions provided. You will only need to do this once across all repos using our CLA.

This project has adopted the [Proximify Open Source Code of Conduct](https://github.com/Proximify/community/blob/master/docs/code_of_conduct.md). For more information see the Code of Conduct FAQ or contact support@proximify.com with any additional questions or comments.

## License

Copyright (c) Proximify Inc. All rights reserved.

Licensed under the [MIT](https://opensource.org/licenses/MIT) license.

**CLI Actions** is made by [Proximify](https://proximify.com). We invite the community to participate.

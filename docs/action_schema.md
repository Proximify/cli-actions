[CLI Actions](../README.md) / CLI action schema

# CLI Actions: schema

The action definitions are files that define the prompts for each argument and the acceptable values for them. In addition, the arguments can require sub-arguments based on the options selected for them. CLI Actions allows for nested definitions of arguments and sub-arguments.

## Available options

-   `class` **String** - The class name
-   `method` **String** - Action callback method. The method should be declared in the given class.
-   `askConfirm` **Boolean** - Whether to ask the user for confirmation to proceed the action.
-   `commandKey` **String** - Proceed the action based on the argument which is declared in the `commandKey`.
-   `arguments` **Object** - Mandatory arguments to proceed the action. The key of each argument item is the **name** of the argument and the value is an object that collects the information of the argument. The valid options of the argument are as below:
    -   `options` **Array** - The options property can be a simple array of strings. Users can select the value from the given options. It can also be a sub action definition object. The keys of the object are the option values and the value of the object can be either:
        -   **true** - Define the sub-action in another definition file. The path of the file is **MAIN_ACTION/KEY_OF_THE_OBJECT**.
        -   **Object** - Action definition objec.
    -   `prompt` **String** - Prompting message. The prompter checks what arguments were given in the CLI and only prompts the user for the missing ones. The prompting message instructs users to input the value.
    -   `selectByIndex` **Boolean** - Enable the way to let users input the number to select the option.
    -   `displayType` **String** - Enum of **array** and **list**. The way to preset the prompt message.

### Examples

#### AWS host provider

```yml
---
accessKeyId:
    prompt: AWS Access Key ID
secretAccessKey:
    prompt: AWS Secret Access Key (password)
region:
    options:
        us-east-1:
            label: "1 | US Region, Northern Virginia or Pacific Northwest. \n \"us-east-1\""
        us-east-2:
            label: "2 | US East (Ohio) Region. Needs location constraint us-east-2. \n \"us-east-2\""
        us-west-2:
            label: "3 | US West (Oregon) Region. Needs location constraint us-west-2. \n \"us-west-2\""
        us-west-1:
            label: "4 | US West (Northern California). Needs location constraint us-west-1. \n \"us-west-1\""
    displayType: list
    selectByIndex: true
    prompt: Region to connect to? Choose a number from below, or type in your own value.
CDN:
    prompt:
        Do you want to set up CDN? To set up the CDN, you need to have a SSL certificate
        for the domain.
    options:
        y:
            domain:
                prompt: Domain name?
            certificate:
                prompt: AWS SSL certificate ARN?
        n: {}
```

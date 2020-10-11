# CLI Actions Schema

Definition of a CLI action.

```json
"class": {
    "type": "string",
    "description": "Name of the class that has the method to run."
},
"method": {
    "type": "string",
    "description": "The name of the method to run for the action."
},
"isStatic": {
    "type": "boolean",
    "description": "Whether the method to run is static or not."
}
```

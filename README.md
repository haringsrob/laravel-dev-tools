# Laravel lsp

The Laravel/Blade lsp provides:

Blade:
- [x] Diagnostics on missing components + action to create it.
- [x] Autocomplete for components and their arguments.
- [x] Hover shows the path to the view.
- [x] Goto definition on components to jump to the view or component class.

Livewire:
- [x] Autocomplete for livewire components and their arguments.
- [x] Autocomplete for livewire wireable properties.
- [x] Goto definition to the livewire class (not yet the view).
- [x] Hover shows the path to the view.
- [x] Diagnostics on missing wire:model rules/parameters or missing wire:click methods.
- [x] Diagnostics on missing components + action to create it.

Extractors for (external) lsp:
- [x] routes() autocomplete
- [x] views() autocomplete
- [x] container autocomplete
- [ ] config() autocomplete
- [x] Php autocomplete in blade
- [x] Model information, relation builders

## Demo

![Demo](./screenshots/demo-blade-lsp.gif)

## Status

This LSP is still to be considered unstable. If you find issues, you are welcome to provide a
issue/pull request with a *reproducable example*.

Issues without clear steps to reproduce may be closed without answer.

As it is experimental, there is no guarantee whatsoever that this will work. Please always ensure
your code is under version control.

Thing may be slow.

## Installation

### Requirements

PHP 8.0, older versions will NOT work. (not even trying).
LARAVEL 8.0, older versions will NOT work.

This LSP is based on php in your runtime. I have not tested this with docker so for now assume it
will not work from outside.

Your application needs to be bootable. This LSP will run commands in your codebase to get all the
information it needs. (Much like running laravel-ide-helper).

### Vscode

!! Not up to date, so best to build it yourself for now.

Install Name: [laravel-blade syntax](https://marketplace.visualstudio.com/items?itemName=cjhowe7.laravel-blade)

Download [the extension](https://marketplace.visualstudio.com/items?itemName=haringsbe-haringsrob.laravel-blade-livewire-lsp) from the vscode extensions.

### (Neo)vim

Download the compiled version from the releases then use it to setup the lsp:

This depends on your setup, below are instruction for using it with `nvim-lspconfig`

``` lua
local lspconfig = require'lspconfig'
local configs = require 'lspconfig.configs'

-- Configure it
configs.blade = {
  default_config = {
    -- Path to the executable: laravel-dev-generators
    cmd = { "laravel-dev-generators", "lsp" },
    filetypes = {'blade'};
    root_dir = function(fname)
      return lspconfig.util.find_git_ancestor(fname)
    end;
    settings = {};
  };
}
-- Set it up
lspconfig.blade.setup{
  -- Capabilities is specific to my setup.
  capabilities = capabilities
}
```

## Building from source

This LSP is based on the great work in [phpactor/language-server](https://github.com/phpactor/language-server)

As it is php it actually does not need building, but we can still do this by makeing a phar so it is easier to distribute.

To build the phar you run:

```
./laravel-dev-tools app:build
```

### Building the vscode extension

To build the vscode extension we have to build the phar and copy it to the extension's directory:

```
./laravel-dev-tools app:build --build-version=1 && cp builds/laravel-dev-tools extensions/vscode/laravel-dev-tools
```

Then in the `extensions/vscode` directory we do:

Install npm modules: `npm install`

Then make the package: `npm run package`

(for me: publish using `vsce publish`)

## Licence notes

This project is based on [Laravel Zero](https://github.com/laravel-zero/laravel-zero)

It uses [phpactor/language-server](https://github.com/phpactor/language-server) for the LSP layer.

Other packages used are:
- [Spatie invade](https://github.com/spatie/invade)
    - To extract information out of laravel's core.
- [Laravel](https://github.com/laravel/framework)
    - Best php framework ever, and needed for it's types.
- [Soyhuce/next-ide-helper](https://github.com/Soyhuce/next-ide-helper)
    - To extract model information.

## Todo's

- [ ] Auto copy the readme/Licence to the extension folder when building.

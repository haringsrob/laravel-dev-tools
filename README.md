# Laravel dev generators

Laravel dev generators is a globally installed package that you can use to ease your Laravel development.

This package is to be considered unstable, or might not even work for you at all. But if you encounter
issues, please let me know via the issues queue and I will try to resolve them.

## Install

```
composer global require haringsrob/laravel-dev-generators
```

Make sure your global composer bin directory is linked in your `$PATH`.

## Usage

### Snippets

```
laravel-dev-generators snippets [path to laravel install]
```

This will generate snippets for blade components, blade directives and livewire components in:

```
projectpath/vendor/haringsrob/laravel-dev-generators/snippets/blade.json
```

Now you can use that path to load snippets for example using **vim-vsnip** `init.lua`:

```lua
local snipDirs = {}

local function file_exists(name)
   local f=io.open(name,"r")
   if f~=nil then io.close(f) return true else return false end
end

if file_exists(vim.fn.getcwd() .. '/app/Providers/AppServiceProvider.php') then
    os.execute("laravel-dev-generators snippets " .. vim.fn.getcwd())
    table.insert(snipDirs, (vim.fn.getcwd() .. '/vendor/haringsrob/laravel-dev-generators/snippets/'))
end

-- Optional: Load additional snippets from the ~/.config/nvim/snippets folder.
CONFIG_PATH = vim.fn.stdpath('config')
table.insert(snipDirs, CONFIG_PATH .. '/snippets')

vim.g.vsnip_snippet_dirs = snipDirs
```

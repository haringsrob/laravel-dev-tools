# Laravel lsp

The laravel lsp provides:

- [ ]Blade/livewire component autocomplete and goto definition
- Blade directvies autocomplete
- [ ] Suggestions for config() and view(), and goto definition for view() calls.

## Obfuscate

`/Users/rob/Sites/test/yakpro-po/yakpro-po.php`

Example:
```
php -d memory_limit=-1 /Users/rob/Sites/test/yakpro-po/yakpro-po.php laravel-dev-generators -o laravel-dev-generators-obf --no-obfuscate-constant-name
```

## Building

1. Clone https://github.com/php/php-src (8.1)
2. Clone `git clone git@github.com:dixyes/phpmicro.git sapi/icro`
3. Patch:
```
patch -p1 < sapi/micro/patches/cli_checks.patch
patch -p1 < ./sapi/micro/patches/vcruntime140_80.patch
patch -p1 < ./sapi/micro/patches/win32_80.patch
patch -p1 < ./sapi/micro/patches/zend_stream.patch
```
4. Run `./buildconf --force`
4. Configure: `./configure --disable-all --enable-micro --disable-zts --enable-ctype --enable-filter --enable-mbstring --enable-session --enable-tokenizer --enable-phar`
5. Build `make micro -j8`

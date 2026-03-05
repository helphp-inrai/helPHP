# Modification history

## 2026/03/05

- Added process of hash in init.php to automatically transfer the connection.

## 2026/03/04

- Modified files that include `config/main.php` to replace use of relative path by use of `__DIR__`
- Added creation of `jsgz/` folder in minify.php if not found
- Added default value to css variable `--fhd-vr` and `--fhd-hr` at load of the page to stop flickering of size on elements that use them
- Added transfer of connection hash in preview to transfer the connection to context's preview

## 2026/03/03

- Removing unused file in `/modules/core/public`

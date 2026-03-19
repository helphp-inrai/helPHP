# Modification history

This file list all the modification we make, classified by commit.
Each time you see "commit xxx" thats mean all the modification below are inside these commit. __Be aware of the patches link next to the commit, indicates that there is something to install to properly work with this commit.__
If you are up to date and a new version is out, __the modifications that you need to read are those before the first "commit xxxx" line.__

---

## commit [aad4f42](https://github.com/helphp-inrai/helPHP/commit/aad4f429efbec362aa70582a26d0f3ef52afc8fa) - [patch link](http://www.helphp.org/patch/patch-2026_03_19-1.zip)

- __Block news__ Added field to choose the number of element to display (be careful to add the new field in block news, see patch)
- __Indexation admin__ Activate list parameter for media / clean code
- __Csseditor admin__ Added timestamp to url for downloading theme to ignore cached version
- __Deco public__ Added load of block's css
- __MEDIA__ Added possibility to add a media by language

## commit [cbc2a07](https://github.com/helphp-inrai/helPHP/commit/cbc2a07b55c3a0ede0d7799028538da2f4e39f4a)

- Added process of hash in init.php to automatically transfer the connection.
- Modified files that include `config/main.php` to replace use of relative path by use of `__DIR__`
- Added creation of `jsgz/` folder in minify.php if not found
- Added default value to css variable `--fhd-vr` and `--fhd-hr` at load of the page to stop flickering of size on elements that use them
- Added transfer of connection hash in preview to transfer the connection to context's preview
- Removing unused file in `/modules/core/public`

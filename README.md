# Important note
This is built specifically for pxls.space. If your pxls.space install is older than Sept 8, 2018, then you'll be missing some columns and tables on your database. See this commit for details: [xSke/Pxls 4f22e](https://github.com/xSke/Pxls/commit/4f22e996bc7bbbb39649300c0214dea15a619a43)

# Getting up and running
## Required tools
* [PHP](https://php.net/)
* [Composer](https://getcomposer.org/)
## Configuration
1) Copy or rename `src/settings.example.php` to `src/settings.php`
2) Modify `settings.php` with all the necessary values (config/etc). Values to pay attention to:
    * webroots
    * db
## Building
3) Install dependencies with composer
    * `composer i`
## Running
There are two ways to get this working.
    1) Use the php CLI to run (for debugging)
        * `php -S 0.0.0.0:5000 -t public public/index.php`
    2) Serve the PHP with something like nginx/apache (for production)

# Frameworks used
* Backend:
    * [Slim Framework v3](https://www.slimframework.com)
* Frontend:
    * [AdminLTE](https://adminlte.io/)

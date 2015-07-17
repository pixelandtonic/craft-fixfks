Craft Foreign Key Restoration Tool
----------------------------------

This tool is composed of two scripts:

* **dumpfks.php** – used to analyze a fresh Craft install and save a JSON dump
  of all the foreign keys in the database. Results will be stored in the
  fkdumps/ folder.
* **fixfks.php** – used to analyze a Craft install and find/repair any missing
  foreign keys, based on the dump created by **dumpfks.php**. A report will be
  given of all the missing FKs, as well as any invalid table values that would
  cause a foreign key constraint violation if the FKs were to exist. How the
  tool deals with that invalid data depends on the column: if it’s nullable,
  then those values will be set to null. Otherwise their rows will be deleted.

## How to use these scripts

If you have a database that is missing some foreign keys, follow these steps:

1. Check the fkdumps/ folder and see if a foreign key dump exists for your
   installed version of Craft. If so, skip to step 8.
2. Create a new database, which will be used to install a temporary version of
   Craft.
3. If craft/config/db.php isn’t already a [multi-environment config] file, move
   the config settings within it into a sub-array with the key `'*'`:

        '*' => array(
            'server'      => '127.0.0.1',
            'user'        => 'mysql_user',
            'password'    => 'password',
            'database'    => 'my_site',
            'tablePrefix' => 'craft'
        ),

4. Add the following environment config to craft/config/db.php:

        'fresh' => array(
            'database' => 'new_database_name',
            'tablePrefix' => '',
        ),

5. Upload dumpfks.php to your web server and access it with your browser.
6. Click the “Dump FKs for Craft X.Y.Z” button.
7. Once the dump has been created, you can delete the database created in step
   2, and the `'fresh'` environment config created in step 3.
8. Upload fixfks.php to your web server and access it with your browser.
9. Click the “Restore missing FKs” button at the bottom of the report.

Once the script is finished, your database should all set.

If you had to create your own foreign key dump (steps 2-7), please submit a pull
request with the new file, or send it to support@buildwithcraft.com. Thank you!

[multi-environment config]: http://buildwithcraft.com/docs/multi-environment-configs
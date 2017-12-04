In order to run tests:

-  create databse:
   CREATE DATABASE "sortable_test" WITH OWNER "postgres";

   User postgres must not have a password.
   Or modify the following files to feet your needs:
   - tests/unit.suite.yml
   - tests/unit/SortableTest.php


- in terminal change direcroty to Sortable
- run the comman: codecept run unit SortableTest
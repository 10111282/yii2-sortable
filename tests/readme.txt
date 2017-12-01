In order to run tests:

-  create databse:
   CREATE DATABASE "sortable_test" WITH OWNER "postgres";

   User postgres must not have a password. Or modify unit.suite.yml to your needs.

- in terminal change direcroty to Sortable
- run the comman: codecept run unit SortableTest
In order to run tests:

-  create databse:
   CREATE DATABASE "sortable_test" WITH OWNER "postgres";

   User postgres must not have a password.
   Or modify the following files to feet your needs:
   - tests/unit.suite.yml
   - tests/unit/SortableTest.php

- in terminal change direcroty to Sortable
- run the comman: php vendor/bin/codecept run unit SortablePostgersTest


Running tests with Docker

- Install docker and docker-compose
- in terminal change direcroty to Sortable
- build containers: docker-compose run -d --build
- enter the container: docker exec -it sortable-php-fpm bash
- run MySql test:  php vendor/bin/codecept run unit SortableMySqlTest
- run Postgres test:  php vendor/bin/codecept run unit SortablePostgersTest
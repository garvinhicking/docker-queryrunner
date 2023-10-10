# docker-queryrunner

Executes SQL queries on a plethora of dockerized SQL servers (mysql, postgres, mariadb, sqlite).

Uses doctrine/dbal, not raw SQL queries, because you need abstraction when dealing with multiple DBs.

This is a dirty little helper, use at your own risk.

# Usage

`php docker-composer-db.php > docker-compose.yml` creates a `docker-compose.yml` file with the setup.

It reads the configured image names/versions from a `config.inc.php` file.

`docker-compose up` will then start it, fetch the images, and expose each host with a localized port. The port number gets increased for each instance.

If you're on ARM64 you may need to pull specific images before and tag them:

```
docker pull biarms/mysql:5.7
docker tag biarms/mysql:5.7 mysql:5.7
```

After the containers are up you can run:

`php queryrunner.php [task] [dockerPrefix] [dockerSuffix]`

The first parameter references what task is executed. The subdirectory `tasks` groups the files `query.php`, `init.php` and `fixture.csv` so that you can switch between multiple query types.

The second and third parameters is a docker prefix/suffix, if your docker-containers are actually outside of this repository. This is used for accessing a docker container by name: "[dockerPrefix]-[database-type][database-version]-[dockerSuffix]"

# TODO

* Refactor out old pdo code
* Create a better event/hook api instead of requiring init.php/query.php files

This script was purely conceived for a quick-and-dirty check of a specific QueryBuilder syntax

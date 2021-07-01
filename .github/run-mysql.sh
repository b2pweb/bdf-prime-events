#!/bin/bash

mysql() {
  docker exec mysql mysql --protocol=tcp "${@}"
}

docker run -p 0.0.0.0:3306:3306 -e MYSQL_ALLOW_EMPTY_PASSWORD=yes -e MYSQL_ROOT_HOST=% --name=mysql -d mysql:5.7 \
mysqld \
  --datadir=/var/lib/mysql \
  --user=mysql \
  --server-id=1 \
  --log-bin=/var/lib/mysql/mysql-bin.log \
  --binlog-format=row \
  --max_allowed_packet=64M

while :
do
  sleep 3
  mysql -e 'select version()' && break
done

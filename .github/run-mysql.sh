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

mysql -e "create database test"
mysql -e "create user test_events IDENTIFIED BY 'password'"
mysql -e "grant all privileges on test.* to test_events"
mysql -e "grant replication slave, replication client on *.* to test_events"
mysql -e "create database other"
mysql -e "create user other_user IDENTIFIED BY 'other_pass'"
mysql -e "grant all privileges on other.* to other_user"
mysql -e "grant replication slave, replication client on *.* to other_user"

#!/bin/sh

sudo fallocate -l 1G /swapfile
sudo chmod 600 /swapfile
sudo mkswap /swapfile
sudo swapon /swapfile

DB_ROOT_USER="root"
DB_ROOT_PASS="nopass"
DBNAME="vitto"
DBDUMP="/var/www/html/.vagrant/db.sql"

sudo apt-get update

sudo apt-get install -y curl openssl git mc

sudo echo mysql-server mysql-server/root_password password ${DB_ROOT_PASS} | sudo debconf-set-selections
sudo echo mysql-server mysql-server/root_password_again password ${DB_ROOT_PASS} | sudo debconf-set-selections

sudo apt-get install -y lamp-server^ php5-curl

sudo apt-get install -y php5-dev php-pear
sudo pecl install -Z xdebug

sudo ln -s /var/www/html/.vagrant/20-xdebug.ini /etc/php5/apache2/conf.d
sudo ln -s /var/www/html/.vagrant/30-xdebug.addition.ini /etc/php5/apache2/conf.d

sudo apt-get install php5-gd

curl -s http://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer

mkdir -p ~/.composer/
ln -s /var/www/html/.vagrant/auth.json ~/.composer/

if [ ! -f /var/log/dbinstalled ];
then
    echo "CREATE DATABASE IF NOT EXISTS $DBNAME" | mysql -u${DB_ROOT_USER} -p${DB_ROOT_PASS}
    echo "CREATE USER '$DBNAME'@'$DBNAME' IDENTIFIED BY '$DBNAME'" | mysql -u${DB_ROOT_USER} -p${DB_ROOT_PASS}
    echo "GRANT ALL PRIVILEGES ON $DBNAME.* TO '$DBNAME'@'$DBNAME' IDENTIFIED BY '$DBNAME'" | mysql -u${DB_ROOT_USER} -p${DB_ROOT_PASS}
    echo "GRANT ALL ON $DBNAME.* TO '$DBNAME'@'localhost'" | mysql -u${DB_ROOT_USER} -p${DB_ROOT_PASS}
    echo "flush privileges" | mysql -u${DB_ROOT_USER} -p${DB_ROOT_PASS}

    echo "GRANT ALL PRIVILEGES ON *.* TO 'root'@'%' IDENTIFIED BY 'nopass';" | mysql -u${DB_ROOT_USER} -p${DB_ROOT_PASS}

    sudo sed -i "s/bind-address.*/bind-address = 0.0.0.0/" /etc/mysql/my.cnf
    sudo service mysql restart

    sudo touch /var/log/dbinstalled

    if [ -f "$DBDUMP" ];
    then
        sudo mysql -u${DB_ROOT_USER} -p${DB_ROOT_PASS} ${DBNAME} < ${DBDUMP}
    fi
fi
sudo rm -f /etc/apache2/sites-enabled/000-default.conf
sudo ln -s /var/www/html/.vagrant/httpd.conf /etc/apache2/sites-enabled/000-default.conf

sudo rm -f /etc/apache2/envvars
sudo cp /var/www/html/.vagrant/envvars /etc/apache2/envvars

# install node
sudo apt-get install nodejs -y
sudo ln -s `which nodejs` /usr/bin/node

# install npm
sudo apt-get install npm -y

# install bower
sudo npm install -g bower

# install grunt-cli + grunt
sudo npm install -g grunt-cli
sudo chmod 777 ~/tmp/

sudo service apache2 restart
sudo service mysql restart
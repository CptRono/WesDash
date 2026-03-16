#!/bin/bash
apt-get update && apt-get install -y php php-mysqli php-pdo php-mysql php-curl
php -S 0.0.0.0:$PORT

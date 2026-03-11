#!/bin/bash
apt-get update && apt-get install -y php php-mysqli
php -S 0.0.0.0:$PORT

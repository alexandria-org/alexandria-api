#!/bin/bash

cd `dirname $0`

echo "Copying nginx config";

echo "server {
	listen 80 default_server;
	listen [::]:80 default_server;

	root /alexandria-api;
	index index.html;
	server_name _;

	location / {
		fastcgi_pass unix:/var/run/php/php7.4-fpm.sock;
		fastcgi_index index.php;
		fastcgi_param  GATEWAY_INTERFACE  CGI/1.1;
		fastcgi_param  SERVER_SOFTWARE    nginx;
		fastcgi_param  QUERY_STRING       \$query_string;
		fastcgi_param  REQUEST_METHOD     \$request_method;
		fastcgi_param  CONTENT_TYPE       \$content_type;
		fastcgi_param  CONTENT_LENGTH     \$content_length;
		fastcgi_param  SCRIPT_FILENAME    /alexandria-api/index.php;
		fastcgi_param  SCRIPT_NAME        index.php;
		fastcgi_param  REMOTE_ADDR        \$remote_addr;
		include fastcgi_params;
	}
}
" > /etc/nginx/sites-enabled/default 

echo "server {
	listen 81;
	listen [::]:81;

	root /usr/share/phpmyadmin/;

	# Add index.php to the list if you are using PHP
	index index.php index.html index.htm index.nginx-debian.html;

	server_name _;

	location / {
		# First attempt to serve request as file, then
		# as directory, then fall back to displaying a 404.
		try_files \$uri \$uri/ =404;
	}

	location ~ \.php$ {
		# First attempt to serve request as file, then
		# as directory, then fall back to displaying a 404.
		try_files \$uri \$uri/ =404;
		fastcgi_pass unix:/var/run/php/php7.4-fpm.sock;
		fastcgi_index index.php;
		fastcgi_param  GATEWAY_INTERFACE  CGI/1.1;
		fastcgi_param  SERVER_SOFTWARE    nginx;
		fastcgi_param  QUERY_STRING       \$query_string;
		fastcgi_param  REQUEST_METHOD     \$request_method;
		fastcgi_param  CONTENT_TYPE       \$content_type;
		fastcgi_param  CONTENT_LENGTH     \$content_length;
		fastcgi_param  SCRIPT_FILENAME    \$document_root\$fastcgi_script_name;
		fastcgi_param  SCRIPT_NAME        \$fastcgi_script_name;
		fastcgi_param  REMOTE_ADDR        \$remote_addr;
		include fastcgi_params;
	}
}
" > /etc/nginx/sites-enabled/phpmyadmin

/etc/init.d/nginx restart
/etc/init.d/php7.4-fpm restart
/etc/init.d/redis-server restart
/etc/init.d/mysql start

mysql -u root -e "create user if not exists alexandria@localhost identified by ''"
mysql -u root -e "grant all privileges on *.* to alexandria@localhost with grant option"
mysql -u root -e "create table if not exists alexandria"

# Write phpmyadmin config.

echo "<?php

\$cfg['blowfish_secret'] = '';

\$cfg['Servers'][1]['auth_type'] = 'config';
\$cfg['Servers'][1]['host'] = 'localhost';
\$cfg['Servers'][1]['compress'] = false;
\$cfg['Servers'][1]['user'] = 'alexandria';
\$cfg['Servers'][1]['password'] = '';
\$cfg['Servers'][1]['AllowNoPassword'] = true;

\$cfg['UploadDir'] = '';
\$cfg['SaveDir'] = '';
" > /etc/phpmyadmin/config.inc.php

curl https://api.alexandria.org/schema | mysql alexandria


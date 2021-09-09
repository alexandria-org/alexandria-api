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
" > /etc/nginx/sites-available/default 

/etc/init.d/nginx restart
/etc/init.d/php7.4-fpm restart
/etc/init.d/redis-server restart


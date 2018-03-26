#!/usr/bin/env bash

declare -A params=$6     # Create an associative array
paramsTXT=""
if [ -n "$6" ]; then
   for element in "${!params[@]}"
   do
      paramsTXT="${paramsTXT}
      fastcgi_param ${element} ${params[$element]};"
   done
fi

block="server {
    listen  ${3:-80};
    listen  ${4:-443} ssl http2;

    server_name .$1;
    root \"$2\";

    set \$mux \"mx\";
    
    access_log off;
    error_log  /var/log/nginx/$1-error.log error;

    gzip on;
    gzip_min_length 1000;
    gzip_types text/plain text/xml application/xml;
    client_max_body_size 25m;
    
    try_files \$uri \$uri/ /index.php?\$args;
    index ${7:-index}.php;
    
    location / {
        index index.php;
    }
    
    # Zugriff auf sensible Dateien verwehren
    location ~ (\.inc\.php|\.tpl|\.sql|\.tpl\.php|\.db)$ {
        deny all;
    }
    
    # Die htaccess brauchen wir nicht mehr - und wenn sie noch da is
    # sollte sie nicht angezeigt werden
    location ~ \.htaccess {
        deny all;
    }

    # Die eigentliche RewriteRule für das Zend Framework
    if (!-e \$request_filename) {
        rewrite ^.*$ /index.php last;
    }

    location ~ \.php$ {
        fastcgi_cache  off;
        fastcgi_pass   unix:/var/run/php/php$5-fpm.sock;
        fastcgi_index  index.php;
        include        fastcgi_params;
        
        fastcgi_param  SCRIPT_FILENAME \$document_root\$fastcgi_script_name;
        $paramsTXT

        fastcgi_param  APPLICATION_ENV dev;
        fastcgi_param  APPLICATION_MUX \$mux;
        fastcgi_param  APPLICATION_STORE \$mux;
        fastcgi_param  HTTPS \$https;

        proxy_read_timeout 300;
        proxy_connect_timeout 300;
        fastcgi_read_timeout 9999;
    }

    ssl_certificate     /etc/nginx/ssl/$1.crt;
    ssl_certificate_key /etc/nginx/ssl/$1.key;
}
"

echo "$block" > "/etc/nginx/sites-available/$1"
ln -fs "/etc/nginx/sites-available/$1" "/etc/nginx/sites-enabled/$1"

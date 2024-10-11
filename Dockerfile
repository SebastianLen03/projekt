FROM php:8.2-cli

# Wyłącz niebezpieczne funkcje PHP
RUN echo "disable_functions = exec,passthru,shell_exec,system,proc_open,popen,proc_close,proc_get_status,proc_nice,proc_terminate,proc_get_status,ini_alter,ini_restore,dl,openlog,syslog,readlink,symlink,popepassthru,stream_socket_server,fsocket,pfsockopen" > /usr/local/etc/php/conf.d/disable_functions.ini

# Ustaw limit czasu wykonania skryptu PHP
RUN echo "max_execution_time = 5" > /usr/local/etc/php/conf.d/timeout.ini

# Wyłącz wyświetlanie błędów na standardowym wyjściu
RUN echo "display_errors = Off" > /usr/local/etc/php/conf.d/display_errors.ini
RUN echo "log_errors = On" >> /usr/local/etc/php/conf.d/display_errors.ini

WORKDIR /app

# Uruchamiaj jako nieuprzywilejowany użytkownik
RUN useradd -ms /bin/bash sandboxuser
USER sandboxuser

CMD ["php", "code.php"]

#! /bin/sh
# /etc/init.d/mailpit
#
# MailPit init script.
[ -f /etc/default/mailpit ] && . /etc/default/mailpit
PID=/var/run/mailpit.pid
USER=nobody
MAILPIT_PATH=/usr/local/bin
BIN=$MAILPIT_PATH/mailpit
MAILPIT_OPTS=""

# Carry out specific functions when asked to by the system
case "$1" in
  start)
    echo "Starting mailpit."
    start-stop-daemon --start --pidfile $PID --make-pidfile --user $USER --background --exec $BIN -- $MAILPIT_OPTS
    ;
  stop)
    if [ -f $PID ]; then
      echo "Stopping mailpit.";
      start-stop-daemon --stop --pidfile $PID
    else
      echo "MailPit is not running.";
    fi
    ;
  restart)
    echo "Restarting mailpit."
    start-stop-daemon --stop --pidfile $PID
    start-stop-daemon --start --pidfile $PID --make-pidfile --user $USER --background --exec $BIN -- $MAILPIT_OPTS
    ;
  status)
    if [ -f $PID ]; then
      echo "MailPit is running.";
    else
      echo "MailPit is not running.";
      exit 3
    fi
    ;
  *)
    echo "Usage: /etc/init.d/mailpit {start|stop|status|restart}"
    exit 1
    ;
esac

exit 0
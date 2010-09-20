#!/bin/sh
# protect against execution
if [ -n "$GATEWAY_INTERFACE" ]; then
  echo "Content-type: text/html"
  echo ""
  echo "<html><head><title>ERROR</title></head><body><h1>ERROR</h1></body></html>"
  exit 0
fi

export UDI_LOG=1
cd unit_tests
#if [ -d /tmp ]; then
#    if [ -f /tmp/phpldapadmin.log ]; then
#        sudo rm -f /tmp/phpldapadmin.log
#    fi 
#    sudo phpunit "RunAllTests"
# else
    sudo phpunit "RunAllTests"
# fi


#!/bin/bash
# Put this in your bitbar plugins folder
# Make sure it is executable
# Make sure tl is available in your path from /usr/local/bin or /usr/bin
export PATH='/usr/local/bin:/usr/bin:$PATH'
export LANG="${LANG:-en_US.UTF-8}"
if [[ "$1" = "stop" ]]; then
  output=`tl stop`
  osascript -e "display notification \"$output\" with title \"Stopped timer\"" &> /dev/null
fi
tl bitbar
echo "---"
echo "◾  Stop |bash=$0 param1=stop terminal=false refresh=true"

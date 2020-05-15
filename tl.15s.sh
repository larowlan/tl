#!/bin/bash
# Put this in your bitbar plugins folder 🥃
# Make sure it is executable
# Make sure tl is available in your path from /usr/local/bin or /usr/bin
export PATH='/usr/local/bin:/usr/bin:$PATH'
export LANG="${LANG:-en_US.UTF-8}"
if [[ "$1" = "stop" ]]; then
  output=`tl stop`
  osascript -e "display notification \"$output\" with title \"Stopped timer\"" &> /dev/null
fi
if [[ "$1" = "visit" ]]; then
  output=`tl visit`
  osascript -e "display notification \"$output\" with title \"Opening ticket\"" &> /dev/null
fi
OUTPUT="$(tl bitbar)"
COLOR="green"
ICON="🎫"
if [[ ${OUTPUT:0:8} = "Inactive" ]]; then
	COLOR="red"
	# ICON="☠"
	ICON="🎟"
fi
echo "${ICON} ${OUTPUT} | color=${COLOR}"
echo "---"
echo "Stop|bash=$0 param1=stop terminal=false refresh=true"
echo "Refresh|refresh=true"
if [[ ${OUTPUT:0:8} != "Inactive" ]]; then
  echo "Visit Task|bash=$0 param1=visit terminal=false"
fi

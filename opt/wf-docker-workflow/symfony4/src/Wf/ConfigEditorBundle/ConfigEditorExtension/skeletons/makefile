.PHONY: edit-config
edit-config:
	echo -e "\n  \033[32mEditor URL: \033[1;97mhttp://$$(/sbin/ip route|awk '/src/ { print $$7 }'):8000\033[33m ↤ click on the URL to open in browser\033[0m\n"
	php -S $$(/sbin/ip route|awk '/src/ { print $$7 }'):8000 -t {{ doc_root }} || echo "1"

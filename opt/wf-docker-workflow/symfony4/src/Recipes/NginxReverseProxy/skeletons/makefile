# It is just for help! Format: [host]:[port]
DOCKER_HTTP_HOST := {% for service, data in settings %} {{ data.host | split(' ') | join(':' ~ data.port ~ ' ') }}:{{ data.port }}{% endfor %}

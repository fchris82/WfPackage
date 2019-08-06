MailHog
=======

Project repository: https://github.com/mailhog/MailHog

| Port | Service |
|:---- |:------- |
| `1025` | SMTP |
| `8025` | Web UI |

Enable this recipe:

```yaml
recipes:
    mailhog: ~
```

Default domain: `mailhog.[project].loc`

You can change the domain:

```yaml
recipes:
    mailhog:
        nginx_reverse_proxy_host: mailhog.custom.loc
```

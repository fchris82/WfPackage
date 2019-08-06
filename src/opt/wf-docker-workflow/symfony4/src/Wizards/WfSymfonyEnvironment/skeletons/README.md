## Install

> Az alábbiakban egy dockeres telepítés leírása látható, ami a **Docker Workflow**-t használja.

```bash
wf init
# Szerkeszd a fájlokat
wf install
```

Probléma esetén használd:

```bash
wf reinstall
```

{% if sf_version >= 4 %}
## Yarn

Ha használni szeretnéd a Yarn-t, akkor a `.wf.yml` fájlodban importáld be a `.wf.dev.yml` fájlt is! A `wf up`-ra automatikusan elindul a `yarn run watch` parancs.

{% endif %}
### XDebug használat

Alapból települ az **XDebug**. A probléma, hogy az eZ admin felülettel nem túl hatékony a működése, ezért alapból ki van kapcsolva.

1. A `Languages & Frameworks > PHP > Servers` résznél a zöld `+` jellel adj hozzá egy szervert.
    - Amit itt megadsz **Name**-nek, azt kell majd megadnod a `.wf.yml` fájlban a `XDEBUG_IDE_SERVER_NAME` értékének. Javaslat: `Docker`
2. Menü: `Run > Edit configurations` résznél a zöld `+` jellel adj hozzá egy **PHP Web Application**-t
    - Válaszd ki az előbb megadott szervert
    - Adj meg egy tetszőleges nevet
    - Adj meg egy URL-t, amit szeretnél tesztelni
3. Kapcsold be: a `.wf.yml` fájlban a `recipes.symfony{{ sf_version }}.server.xdebug` értékét állítsd át `true`-ra és indítsd újra a container-eket a `wf reload` paranccsal:

```yaml
recipes:
    symfony{{ sf_version }}:
        server:
            xdebug: true
```

**Tesztelés**

1. A `Run > Break at first line in PHP scripts`-re (alsó rész) kattintva bekapcsolod azt, hogy a futás megálljon az első parancsnál.
2. A `Run > Start listening for PHP Debug Connections`-re kattintva bekapcsolod azt, hogy figyelje a parancssorból érkező Xdebug "jeleket"
3. A `wf sf` parancsra most majd meg kell állnia a futásnak a PHPStorm-ban. Ezzel tesztelted a parancssori működést. Ha megáll, de nem nyílik meg a `console` file, akkor valószínűleg rosszul állítottad be a **Use path mappings**-et. Ha nem áll meg, akkor lehet, hogy a portok beállításával nem stimmel vmi.
4. A `Run > Debug {PHP Web Application name}`-nel elkezdi betölteni a böngészőben a megadott oldalt és meg kell állnia a futásnak.

> **Tipp**
>
> Hozz létre egy szájízednek megfelelő általános `xdebug.ini` fájlt a saját home könyvtáradban, pl: `~/.docker/xdebug.ini`. A `.wf.yml` fájlban a `docker_compose.extension.services.engine.volumes` beállításnál add hozzá az alább látható módon (nem elírás, `dist` legyen a cél!). Az `xdebug.remote_host` értékével annyira nem kell foglalkozni, mert automatikusan felül lesz írva minden indulásnál.
> `"~/xdebug.ini:/usr/local/etc/php/conf.d/xdebug.ini.dist:ro"`

```yaml
[...]

docker_compose:
    extension:
        services:
            engine:
                volumes:
                    - "~/xdebug.ini:/usr/local/etc/php/conf.d/xdebug.ini.dist:ro"
```

### HTTP AUTH használata

Lehetőség van arra, hogy HTTP AUTH-tal levédd a felületet. Ehhez szükségünk van egy `.htpasswd` fájlra, amit automatikusan létrehoz a program a `.wf.yml`-ben megadott beállítások alapján:

```yaml
[...]

recipes:
    symfony{{ sf_version }}:
        http_auth:
            enabled: true
            title: Védett tartalom
            # http://www.htaccesstools.com/htpasswd-generator/
            htpasswd: "test:$apr1$JspDJcrr$2c8nNMq8zECtQSIHmAwTT0"
```

## Work

| Parancs | Leírás |
|:------- |:------ |
| `wf help` | Részletes help |
| `wf up` | Docker container-ek indítása |
| `wf debug-*` | Debug parancsok |
| `wf logs <container>` | A megadott container logját kilistázza |
| `wf [ php / composer / sf / mysql ]` | A megadott parancsokat futtatja |

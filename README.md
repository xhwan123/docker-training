# Formación de Docker

Para completar la formación de Docker este es un ejemplo un poco más complejo. El objetivo es demostrar cómo crear imágenes
personalizadas a través de los ```Dockerfile```, que son usados para crear imágenes complejas. El ejemplo tiene dos partes:

* La aplicación se puede ejecutar a través de un solo contenedor
* La aplicación se puede ejecutar a través de tres contenedores:
  * Uno para el código de la aplicación
  * Otro para redis
  * Otro para apache

## Prerequisitos

Para que la aplicación funcione hace falta instalar las dependencias con composer

```bash
$ wget http://getcomposer.org/composer.phar
$ php composer.phar install
```

## Un solo container

**NOTA: Este ejemplo no crea imágenes personalizadas. Y no se puede vincular con Redis**

Para el ejemplo vamos a usar la imagen [php:5.6-cli](https://registry.hub.docker.com/_/php/) para crear un contenedor que ejecuta la aplicación a través del built-in webserver de PHP:

```bash
$ docker run -d -p 80:80 -v "$(pwd):/var/www" -w /var/www php:5.6-cli php -d date.timezone=Europe/Madrid app/console server:run 0.0.0.0:80 --verbose
```

* ```-d```. Cómo ya vimos, con este parámetro le indicamos que el contenedor tiene que ejecutarse en seguando plano.
* ```-p 80:80```. Cómo ya vimos, con este parámetro le indicamos que vamos a bindear el puerto ```80``` del contenedor con el puerto ```80``` de la máquina local (en el caso de ```boot2docker``` la máquina virtual.
* ```-v "$(pwd):/var/www"```. Con este parámetro le indicamos que queremos crear un ```data volume```. Los ```data volumes``` son usados por Docker para compartir directorios entre contenedores. En este caso, le indicamos que queremos montar el directorio raíz de la máquina local en el directorio ```/var/www``` del contenedor y crear un ```data volume```.
* ```-w /var/www```. Le indicamos al contenedor que el directorio desde el que debe ejecutar los commands que le pasamos deberá ser el ```/var/www```.
* ```php:5.6-cli```. Con este parámetro le indicamos qué imagen debe ejecutar.
* ```php -d date.timezone=Europe/Madrid app/console server:run 0.0.0.0:80 --verbose```. Con este parámetro le indicamos cómo vamos a ejecutar el servidor web interno de PHP.

Habiendo levantado este contenedor, si accedemos al puerto 80 de la máquina local deberíamos poder visualizar la aplicación

```bash
$ open "http://$(boot2docker ip)"
```

# Tres contenedores

En este ejemplo vamos a ejecutar la aplicación a través de tres contenedores. Dos contenedores no necesitarán crearse a través de una imagen personalizada: el del código fuente y el de redis. Mientras que el de apache, necesitará crear previamente una imagen personalizada para preparar todo el runtime.

## El código fuente y Redis

Crear un contenedor para el código fuente y para redis es, cómo ya hemos visto en la formación, a través de la acción ```run```.

```bash
$ docker run -v "$(pwd):/var/www" --name source_code ubuntu
$ docker run -d --name redis redis:2.8
```

## Corriendo el servidor web

Para ejecutar el servidor web, previamente vamos a tener que crear una imagen personalizada. Para ello vamos a usar la acción ```build``` de Docker, la cuál nos permitirá crear nuevas imágenes. La acción ```build``` de Docker precisa de los ```Dockerfile``` para crear las imágenes. Los ```Dockerfile``` son generalmente agrupaciones de acciones ```run```, aunque también pueden contener otras directivas. El ```Dockerfile``` para Apache se encuentra en la ruta [app/config/docker/apache/Dockerfile](https://github.com/atrapalo/docker-training/blob/master/app/config/docker/apache/Dockerfile).

Para el caso usamos las directivas:

* ```FROM```. Le indicamos a Docker cuál va a ser la imágen base a usar.
* ```ENV```. Con esta directiva le indicamos a Docker que vamos a establecer una variable de entorno.
* ```RUN```. Con esta directiva le decimos a Docker que queremos ejecutar un command.
* ```ADD```. Con esta directiva podemos copiar archivos y directorios desde la máquina local al contenedor.
* ```EXPOSE```. Con esta directiva le decimos a Docker que el contenedor va a exponer un puerto en concreto.
* ```CMD```. Con esta directiva le decimos a Docker qué command line por defecto tiene que ejecutar cuándo cree o
  reinicie el contenedor.

Hay muchas más directivas. Para ver una referencia, podéis acceder [aquí](https://docs.docker.com/reference/builder/). Para crear la imagen del contenedor que va a ejecutar Apache hay que ejecutar

```bash
$ docker build --rm -t "atrapalo/docker-tutorial-apache" app/config/docker/apache
```

* ```--rm```. Cada directiva que especifiquemos en el ```Dockerfile``` va a crear un *contendor intermedio*. Cómo generalmente no nos interesará manetener esos contenedores intermedios, podemos usar este parámetro para decirle a Docker que los limpie cuándo acabe de construir la imagen.
* ```-t "atrapalo/docker-tutorial-apache"```. Con este parámetro le estamos indicando a Docker qué nombre va a tener esa imagen.
* ```app/config/docker/apache```. Con este parámetro le indicamos a Docker en qué directorio está localizado el ```Dockerfile``` con el que va a construir la imagen.

Una vez que tenemos la imagen creada, solo tenemos que ejecutar un contenedor basado en esa imagen

```bash
$ docker run -d -p 80:80 --volumes-from="source_code" --link redis:redis --name apache atrapalo/docker-tutorial-apache
```

* ```--volumes-from="source_code"```. Con este parámetro le decimos a Docker que **monte los directorios compartidos por el contenedor _source_code_ en el contenedor ```apache```**. Es decir, con este parámetro vamos a crear un directorio ```/var/www``` dentro del nuevo contenedor que estará vinculado con el directorio ```/var/www``` del contenedor ```source_code```.
* ```--link redis:redis```. Con este parámetro Docker establece un *enlace* entre el contendor ```redis``` anteriormente creado y el contenedor ```apache```. Este parámetro, toma la forma de *NOMBRE-DEL-CONTENEDOR:ALIAS*. Además establece una nueva entrada en el archivo ```/etc/hosts``` del contenedor ```apache``` con un nombre de host del ALIAS apuntando a la IP del contenedor vinculado. Es decir, dentro del archivo de hosts, en este caso, existirá una entrada parecida a: ```redis <IP del contenedor de redis>```. Además establece variables de entorno con la IP y el puerto expuestos por el contenedor ```redis``` para que podamos interactuar con él
    * ```<name>_PORT_<port>_<protocol>``` contendrá la URL de referencia al puerto. Dónde <name> es el alias especificados (por ej. ```tcp://172.17.0.82:8080```). Está URL se dividirá luego en 3 variables de entorno. En este caso se establecerá la variable de entorno: ```REDIS_PORT_6379_TCP```.
    * ```<name>_PORT_<port>_<protocol>_ADDR``` contendrá la dirección IP de la url: ```REDIS_PORT_6379_TCP_ADDR=172.17.0.82```.
    * ```<name>_PORT_<port>_<protocol>_PORT``` contendrá solo el número de puerto de la URL: ```REDIS_PORT_6379_TCP_PORT=6379```.
    * ```<name>_PORT_<port>_<protocol>_PROTO``` contendrá solo el protocolo de la URL: ```REDIS_PORT_6379_TCP_PROTO=tcp```.

Con esto si ejecutamos esto

```bash
$ open "http://$(boot2docker ip)"
$ open "http://$(boot2docker ip)/redis/set-value"
$ open "http://$(boot2docker ip)/redis/get-value"
```

Deberíamos poder ejecutar la aplicación sin problemas.

# Modo alternativo. Usando docker-compose.

Docker-compose nos permite manejar la ejecución de nuestros contenedores de modo sencillo a partir de un fichero de configuración.

## Instalar docker-compose.

```bash
$ curl -L https://github.com/docker/compose/releases/download/1.1.0/docker-compose-`uname -s`-`uname -m` > /usr/local/bin/docker-compose
$ chmod +x /usr/local/bin/docker-compose
```

## Definir los contenedores en un fichero.

Creamos un fichero docker-compose.yml con el siguiente contenido:

```yaml
redis:
  image: redis:2.8
sourcecode:
  image: ubuntu
  volumes:
  - ".:/var/www"
apache:
  image: atrapalo/docker-tutorial-apache
  ports:
  - 80:80
  volumes_from:
  - "sourcecode"
  links:
  - redis
```

**NOTA: Es importante haber creado previamente la imagen atrapalo/docker-tutorial-apache ya que es la única que no tenemos disponible en los repositorios de docker.**

## Ejecutar.

**Ejecución normal.**

```bash
$ docker-compose up
```

**Ejecución modo demonio.**

```bash
$ docker-compose up -d
```

**Opciones:**

```bash
$ docker-compose
  Fast, isolated development environments using Docker.
  
  Usage:
    docker-compose [options] [COMMAND] [ARGS...]
    docker-compose -h|--help
  
  Options:
    --verbose                 Show more output
    --version                 Print version and exit
    -f, --file FILE           Specify an alternate compose file (default: docker-compose.yml)
    -p, --project-name NAME   Specify an alternate project name (default: directory name)
  
  Commands:
    build     Build or rebuild services
    help      Get help on a command
    kill      Kill containers
    logs      View output from containers
    port      Print the public port for a port binding
    ps        List containers
    pull      Pulls service images
    rm        Remove stopped containers
    run       Run a one-off command
    scale     Set number of containers for a service
    start     Start services
    stop      Stop services
    restart   Restart services
    up        Create and start containers
```

## Referencias

* **[The Docker UserGuide](https://docs.docker.com/userguide/)**
* **[The Docker CommandLine](https://docs.docker.com/reference/commandline/cli/)**
* **[The Dockerfile Reference](https://docs.docker.com/reference/builder/)**
* **[The Docker Compose Reference](https://docs.docker.com/compose/)**

Happy *Dockering*! :)

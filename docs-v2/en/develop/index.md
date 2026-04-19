# Start Developing

Developing this project requires the installation and deployment of a PHP environment, 
as well as some extensions and Composer commonly used in PHP projects.

The development environment and running environment of the project are almost exactly the same. 
You can refer to the **Manual Build** section to install system PHP or use the pre-built static PHP of this project as the environment. 
I will not go into details here.

Regardless of its purpose, this project itself is actually a `php-cli` program. You can edit and develop it as a normal PHP project. 
At the same time, you need to understand the Shell languages of different systems.

The current purpose of this project is to compile statically compiled independent PHP, 
but the main part also includes compiling static versions of many dependent libraries, 
so you can reuse this set of compilation logic to build independent binary versions of other programs, such as Nginx, etc.

## Environment preparation

A PHP environment is required to develop this project. You can use the PHP that comes with the system, 
or you can use the static PHP built by this project.

Regardless of which PHP you use, in your development environment you need to install these extensions:

```
curl,dom,filter,mbstring,openssl,pcntl,phar,posix,sodium,tokenizer,xml,xmlwriter
```

The static-php-cli project itself does not require so many extensions, but during the development process, 
you will use tools such as Composer and PHPUnit, which require these extensions.

> For micro self-executing binaries built by static-php-cli itself, only `pcntl,posix,mbstring,tokenizer,phar` is required.

## Start development

Continuing down to see the project structure documentation, you can learn how `static-php-cli` works.

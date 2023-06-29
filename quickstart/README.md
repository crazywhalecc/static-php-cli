# quick start

## linux container build （with alpine)

```shell

bash quickstart/linux/run-alpine-container.sh
bash quickstart/linux/connection-static-php-cli.sh

bash quickstart/linux/alpine-init.sh
# or use china mirror 
bash quickstart/linux/alpine-init.sh --mirror china 


bash bin/setup-runtime
# or china mirror 
bash bin/setup-runtime --mirror china


cp quickstart/prepare-example.sh  quickstart/prepare.sh
# vi quickstart/prepare.sh
bash quickstart/prepare.sh

```

## macos build

## prepare PHP runtime

```shell

bash bin/setup-runtime
# or china mirror 
bash bin/setup-runtime --mirror china


cp quickstart/prepare-example.sh  quickstart/prepare.sh
# vi quickstart/prepare.sh
bash quickstart/prepare.sh


```
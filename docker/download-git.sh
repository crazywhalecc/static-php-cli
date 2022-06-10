#!/usr/bin/env bash

self_dir=$(cd "$(dirname "$0")";pwd)

test "$3" != "yes" && GITHUB_ADDR="hub.fastgit.xyz" || GITHUB_ADDR="github.com"

git clone https://$GITHUB_ADDR/$1.git --depth=1 $self_dir/source/$2
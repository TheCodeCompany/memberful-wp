#!/bin/bash

set -e

wp-env run cli wp config set MEMBERFUL_APPS_HOST "http://apps.memberful.localhost"
wp-env run cli wp config set MEMBERFUL_EMBED_HOST "http://js.memberful.localhost"
wp-env run cli wp config set MEMBERFUL_SSL_VERIFY false --raw
wp-env run cli wp rewrite structure /%year%/%monthnum%/%postname%/

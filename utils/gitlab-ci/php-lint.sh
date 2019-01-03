#!/bin/sh

set -e

exec find . -name "*.php" -not -path "*/lib/*" -print0 | xargs -0 -n1 php -l

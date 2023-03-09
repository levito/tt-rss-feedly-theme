#!/bin/sh

branch=dist
origin=$(git remote get-url origin)
srcsha=$(git rev-parse --short HEAD)

# Exit if no dist branch is found
if [[ ! $(git ls-remote --heads --exit-code $origin $branch) ]]; then
  echo "No $branch branch found"
  exit 1
fi

# Clone and configure dist branch
clone() {
  pushd dist
  if [ ! -d ".git" ]; then
    git clone $origin -b $branch --depth 1 --bare .git
    git config --unset core.bare
    git reset
  fi
  popd
}

# Push changes to dist branch
push() {
  pushd dist
  if [[ $(git status --porcelain) ]]; then
    git commit -am "build css from $srcsha"
    git push origin $branch
  else
    echo "No changes to commit"
  fi
  popd
}

# Handle incorrect usage
usage() {
  echo "usage: $0 [-cp]"
  exit 2
}

# Read options
while getopts "cp" option; do
  case $option in
    c) clone;;
    p) push;;
    ?) usage;;
  esac
done

# Handle no options given
if [ $OPTIND -eq 1 ]; then
  usage
fi

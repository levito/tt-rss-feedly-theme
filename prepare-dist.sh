#!/bin/sh

origin=$(git remote get-url origin)
branch=dist

if [[ $(git ls-remote --heads --exit-code $origin $branch) ]]; then
  pushd dist
  if [ ! -d ".git" ]; then
    git clone $origin -b $branch --depth 1 --bare .git
    git config --unset core.bare
    git reset
  fi
  popd
fi

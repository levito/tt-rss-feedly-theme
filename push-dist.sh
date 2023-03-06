#!/bin/sh

origin=$(git remote get-url origin)
branch=dist

if [[ $(git ls-remote --heads --exit-code $origin $branch) ]]; then
  pushd dist
  if [[ $(git status --porcelain) ]]; then
    git commit -am "build css"
    git push origin $branch
  else
    echo "No changes to commit"
  fi
  popd
else
  echo "No $branch branch found"
fi

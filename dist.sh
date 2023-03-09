#!/bin/bash

# Distinguish " ", "\t" from "\n"
IFS=
branch=dist
origin=$(git remote get-url origin)
mainsha=$(git rev-parse --short HEAD)
message=
msgsuffix=

# Exit if no dist branch is found
if [[ ! $(git ls-remote --heads --exit-code "$origin" $branch) ]]; then
  echo
  echo "No $branch branch found, won't push."
  exit
fi

# Clone and configure dist branch
clone() {
  pushd dist || exit
  if [ ! -d ".git" ]; then
    git clone "$origin" -b $branch --depth 1 --bare .git
    git config --unset core.bare
    git reset
  fi
  popd || exit
}

# If main is dirty, confirm push to dist
confirm-dirty() {
  if [[ $(git status --porcelain) ]]; then
    echo
    echo "Uncommitted changes found, push to $branch anyway? [yN]"
    while read -rsn1 key; do
      case $key in
        "") exit;;
        n) echo "$key"; exit;;
        y) echo "$key"; break;;
      esac
    done
    msgsuffix="(wip)"
    echo
  fi
}

# Build commit message with sha from main and changes since last dist push, set as $message
get-message() {
  pushd dist > /dev/null || exit
  lastsha=$(git log -1 --pretty=format:%s | cut -d" " -f4)
  popd > /dev/null || exit
  changes=$([ -n "$lastsha" ] && git log "$lastsha".."$mainsha" --pretty=format:"%h  %s")
  body=$([ -n "$changes" ] && printf "\n\nChanges since %s:\n%s" "$lastsha" "$changes")
  message="build css from $mainsha $msgsuffix $body"
}

# Push changes to dist branch
push() {
  confirm-dirty
  get-message
  pushd dist || exit
  if [[ $(git status --porcelain) ]]; then
    git commit -am "$message"
    git push origin $branch
  else
    echo
    echo "No changes to commit."
    echo
  fi
  popd || exit
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

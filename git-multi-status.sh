#!/bin/bash

if [ $# -eq 0 ] ; then
  ARGS="."
else
  ARGS=$@
fi
for i in $ARGS ; do
  for gitdir in `find $i -name .git` ; do
    ( working=$(dirname $gitdir)
      cd $working
      DiffAuthor=$(git show | grep Author | grep -v home-user)
      if [ "$DiffAuthor" != "" ] ; then
        continue
      fi
      RES=$(git status | grep -E '(Changes|Untracked|Your branch)')
      STAT=""
      grep -e 'Untracked' <<<${RES} >/dev/null 2>&1
      if [ $? -eq 0 ] ; then
        STAT=" Untracked,"
      fi
      grep -e 'Changes not staged for commit' <<<${RES} >/dev/null 2>&1
      if [ $? -eq 0 ] ; then
        STAT="$STAT Changes not staged,"
      fi
      grep -e 'Changes to be committed' <<<${RES} >/dev/null 2>&1
      if [ $? -eq 0 ] ; then
        STAT="$STAT Changes to be committed,"
      fi
      grep -e 'Your branch is ahead' <<<${RES} >/dev/null 2>&1
      if [ $? -eq 0 ] ; then
        STAT="$STAT Branch is ahead"
      fi
      grep -e 'Your branch is behind' <<<${RES} >/dev/null 2>&1
      if [ $? -eq 0 ] ; then
        STAT="$STAT Branch is behind"
      fi

      if [ -n "$STAT" ] ; then
        echo -e "$working :$STAT"
      fi
    )
  done
done

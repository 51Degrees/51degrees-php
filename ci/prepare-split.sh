#!/bin/bash
# Run before git subtree split of a package directory. Pushes nothing.
#
# The packages were imported with git subtree add and later moved into
# product folders. The move commits carry trailers that map the moved
# directory to its mirror's head, so a split continues the mirror's history.
#
# git subtree split walks parents it has not seen. From a commit made before
# the move it reaches an import merge, whose second parent is the mirror's
# own history, and from there it takes every older commit of this repository
# for a commit of the package. The split then becomes a merge of the mirror's
# head with this repository's history, and pushing it would copy the whole
# repository into the mirror. This happens for any merge commit with a parent
# from before the move, such as the merge of pull request #2.
#
# Cutting the second parent off each import merge, for this checkout only,
# stops that walk. The replace refs are local to the clone and change no
# commit.
set -euo pipefail
while read -r merge first_parent; do
  git replace --graft "$merge" "$first_parent"
done <<'LIST'
24c06a94dbc90bf7b3a9a60778cf7b82dd2ed10e 3cfe0a8f60d99ed025c0ed97c9e38962b179bae9
2abe6efcce258fd9dbee7e8f7690dbe56e533ec9 24c06a94dbc90bf7b3a9a60778cf7b82dd2ed10e
1677b92027fec616bd791fb480c1856da5b87ec1 2abe6efcce258fd9dbee7e8f7690dbe56e533ec9
9bff6e2d1e9a19c89cadacceb16d28b630915ce7 1677b92027fec616bd791fb480c1856da5b87ec1
d15e015e4e44e9ce3454a656d0fc9d099a721a7e 4fce9d2ac58eb737ccbce259955b3e64932480d0
2e6e8c1883cfb6980c074e6c14dbeda217b96f7e e1ca54ceb64b1f4e0b76274509e8182eb909cd07
LIST

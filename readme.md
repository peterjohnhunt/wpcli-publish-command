Publish version of site and theme

## OPTIONS
<version>...
: Version or subcommand followed by version number
[--dryrun=<dryrun>]
: whether to actually update files, or run a dry run with not actual file changes.
---
default: false
options:
   - true
   - false
---
[--message=<message>]
: release notes if full editor is not needed
[--repo=<repo>]
: Git repo url for changelog compare link

## EXAMPLES
   wp publish major|minor|patch|1.0.0
   wp publish style major|minor|patch|1.0.0
   wp publish changelog major|minor|patch|1.0.0
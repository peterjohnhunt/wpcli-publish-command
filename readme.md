## WP-CLI Publish Command
Publish release notes, change log version, and theme version in one easy command

## Install:
`wp package install git@github.com:peterjohnhunt/wpcli-publish-command.git`

## Getting Started
run `wp publish help` after installing

## WP-CLI Docs
```
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
[--repository=<repository>]
: Git repository url for changelog compare link

## EXAMPLES
   wp publish major|minor|patch|1.0.0
   wp publish site major|minor|patch|1.0.0
   wp publish theme <theme> major|minor|patch|1.0.0
   wp publish plugin <plugin> major|minor|patch|1.0.0
```
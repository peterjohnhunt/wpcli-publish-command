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
---
[--message=<message>]
: release notes if full editor is not needed
---
[--command=<command>]
: set command
[--folder=<folder>]
: set folder for plugin or theme

## EXAMPLES
   wp publish major|minor|patch|1.0.0
   wp publish site major|minor|patch|1.0.0
   wp publish theme <theme> major|minor|patch|1.0.0
   wp publish plugin <plugin> major|minor|patch|1.0.0
```

## SSL errors when installing

If you’re getting a SSL error saying the certificate is expired, here’s how to fix (on macOS):
 - Download the latest certificate [here](https://curl.se/docs/caextract.html)
 - Move that certificate to `/Applications/MAMP/Library/OpenSSL/certs/cacert.pem`
 - Open your current `php.ini` file (to check the right folder, use `which php`)
 - Update the `openssl.cafile` line so it's like this: `openssl.cafile="/Applications/MAMP/Library/OpenSSL/certs/cacert.pem"`
 - Restart MAMP server

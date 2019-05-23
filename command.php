<?php
/**
 * Implements publish command.
 */
class Publish_Command {

    const SEMVERSIONS = array('patch', 'minor', 'major');

    const VERSIONREG  = '[0-9]+\.[0-9]+\.[0-9]+';

    protected $theme;

    protected $new_version;

    protected $theme_dir;

    protected $repository;

    protected $message;
    
    /**
     * Publish version of site and theme
     * 
     * ## OPTIONS
     * <version>...
     * : Version or subcommand followed by version number
     * [--dryrun=<dryrun>]
     * : whether to actually update files, or run a dry run with not actual file changes.
     * ---
     * default: false
     * options:
     *    - true
     *    - false
     * ---
     * [--message=<message>]
     * : release notes if full editor is not needed
     * [--repo=<repo>]
     * : Git repo url for changelog compare link
     * 
     * ## EXAMPLES
     *    wp publish major|minor|patch|1.0.0
     *    wp publish style major|minor|patch|1.0.0
     *    wp publish changelog major|minor|patch|1.0.0
     */
    function __invoke( $args, $assoc_args ) {
        $command = 'site';
        $theme   = wp_get_theme();

        $this->theme           = $theme;
        $this->current_version = $theme->version;
        $this->theme_dir       = $theme->get_stylesheet_directory();
        $this->dryrun          = WP_CLI\Utils\get_flag_value($assoc_args, 'dryrun');
        $this->message         = WP_CLI\Utils\get_flag_value($assoc_args, 'message');
        $this->repository      = WP_CLI\Utils\get_flag_value($assoc_args, 'repository');

        if ( count($args) > 1 ) {
            list( $command, $new_version ) = $args;
        } else {
            list( $new_version ) = $args;
        }

        $new_version = WP_CLI\Utils\increment_version($this->current_version, $new_version);

        if ( !preg_match('/^'.self::VERSIONREG.'/', $new_version) ) WP_CLI::error('incorrect version command');

        if ( version_compare($this->current_version,$new_version) !== -1 ) WP_CLI::error("New version number: {$new_version} is below current version: {$this->current_version}");

        if ( !method_exists( $this, $command ) ) WP_CLI::error("{$command} command not found");

        $updated = $this->$command($new_version);

        if ( !$updated ) WP_CLI::error('unable to publish version');

        WP_CLI::success( "{$command} updated from {$this->current_version} to {$new_version}" );
    }

    protected function _update_file($path, $find, $replace, $count=1){
        if ( !file_exists($path) ) return WP_CLI::line( "{$path} doesn't exist or is empty" );

        $contents = file_get_contents($path);

        if (!$contents) return WP_CLI::line( "{$path} doesn't exist or is empty" );

        $updated  = preg_replace($find, $replace, $contents, $count);

        if (!$updated || $contents == $updated) return WP_CLI::line( "{$path} failed or wasn't changed" );

        $saved = !$this->dryrun ? file_put_contents($path, $updated) : true;
        
        return ($saved !== false);
    }

    function site($new_version){
        if ( !$this->dryrun ){
            $this->dryrun = true;
            $verified = $this->site($new_version);
            if ( !$verified ) WP_CLI::error('Unable to complete the release');
            $this->dryrun = false;
        }

        $style        = $this->style($new_version);
        $changelog    = $this->changelog($new_version);

        return ($style && $changelog);
    }

    function style($new_version){
        $path     = trailingslashit($this->theme_dir) . 'style.css';
        $find     = '/^(\s*Version:\s?)'.$this->current_version.'$/m';
        $replace  = '${1}'.$new_version;
        return $this->_update_file($path, $find, $replace);
    }

    function changelog($new_version){
        if ( !$this->message ) {
            $message = WP_CLI\Utils\launch_editor_for_input("\n\n# Please enter the release notes.\n# Lines starting with '#' will be ignored and an empty message aborts the release.\n# current version: v{$this->current_version}\n# releasing version: v{$new_version}");
            $message = preg_replace('/^#[^#].*$\s+/m', '', $message);
            $this->message = trim($message);
        }

        if ( !$this->message ) WP_CLI::error('Aborting release due to empty release notes');

        $date    = current_time('Y-m-d');
        $folder  = is_multisite() ? $this->theme_dir : ABSPATH;
        $path    = trailingslashit( $folder ) . 'changelog.md';
        $find    = '/^(## \[v'.$this->current_version.'\])/m';
        $link    = $this->repository ? "[v{$new_version}]($this->repository/compare/v{$this->current_version}...v{$new_version})" : "v{$new_version}";
        $replace = "## {$link} {$date}\n{$this->message}\n\n\\1";
        return $this->_update_file($path, $find, $replace);
    }
}

WP_CLI::add_command( 'publish', 'Publish_Command' );
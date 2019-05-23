<?php
/**
 * Implements publish command.
 */
class Publish_Command {

    const SEMVERSIONS = array('patch', 'minor', 'major');

    const VERSIONREG  = '[0-9]+\.[0-9]+\.[0-9]+';

    protected $theme;

    protected $version;

    protected $theme_dir;

    protected $repository;

    protected $message;

    function __invoke( $args, $assoc_args ) {
        $command = 'site';
        $theme   = wp_get_theme();

        $this->theme       = $theme;
        $this->version     = $theme->version;
        $this->theme_dir   = $theme->get_stylesheet_directory();
        $this->dryrun      = WP_CLI\Utils\get_flag_value($assoc_args, 'dryrun');
        $this->message     = WP_CLI\Utils\get_flag_value($assoc_args, 'message');
        $this->repository  = WP_CLI\Utils\get_flag_value($assoc_args, 'repository');

        if ( count($args) > 1 ) {
            list( $command, $version ) = $args;
        } else {
            list( $version ) = $args;
        }

        if ( in_array($version, self::SEMVERSIONS) ) {
            $version = $this->_sem_version( $version );
        }

        if ( !preg_match('/^'.self::VERSIONREG.'/', $version) ) WP_CLI::error('incorrect version command');

        if ( version_compare($this->version,$version) !== -1 ) WP_CLI::error("New version number: {$version} is below current version: {$this->version}");

        if ( !method_exists( $this, $command ) ) WP_CLI::error("{$command} command not found");

        $updated = $this->$command($version);

        if ( !$updated ) WP_CLI::error('unable to publish version');

        WP_CLI::success( "{$command} updated from {$this->version} to {$version}" );
    }

    protected function _sem_version($version){
        $pieces = explode('.', $this->version);

        if (count($pieces) != 3) WP_CLI::error('Unable to parse theme version');

        list($major, $minor, $patch) = $pieces;

        if ( in_array($version, array('major', 'minor')) ){
            $patch = '0';
        }

        if ( $version == 'major' ){
            $minor = '0';
        }
        
        if ($version == 'major'){
            $major++;
        } elseif ($version == 'minor'){
            $minor++;
        } elseif ($version == 'patch'){
            $patch++;
        }

        return implode('.', array($major, $minor, $patch));
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

    function site($version){
        if ( !$this->dryrun ){
            $this->dryrun = true;
            $verified = $this->site($version);
            if ( !$verified ) WP_CLI::error('Unable to complete the release');
            $this->dryrun = false;
        }

        $style        = $this->style($version);
        $changelog    = $this->changelog($version);

        return ($style && $changelog);
    }

    function style($version){
        $path     = trailingslashit($this->theme_dir) . 'style.css';
        $find     = '/^(\s*Version:\s?)'.$this->version.'$/m';
        $replace  = '${1}'.$version;
        return $this->_update_file($path, $find, $replace);
    }

    function changelog($version){
        if ( !$this->message ) {
            $message = WP_CLI\Utils\launch_editor_for_input("\n\n# Please enter the release notes.\n# Lines starting with '#' will be ignored and an empty message aborts the release.\n# current version: v{$this->version}\n# releasing version: v{$version}");
            $message = preg_replace('/^#[^#].*$\s+/m', '', $message);
            $this->message = trim($message);
        }

        if ( !$this->message ) WP_CLI::error('Aborting release due to empty release notes');

        $date    = current_time('Y-m-d');
        $folder  = is_multisite() ? $this->theme_dir : ABSPATH;
        $path    = trailingslashit( $folder ) . 'changelog.md';
        $find    = '/^(## \[v'.$this->version.'\])/m';
        $link    = $this->repository ? "[v{$version}]($this->repository/compare/v{$this->version}...v{$version})" : "v{$version}";
        $replace = "## {$link} {$date}\n{$this->message}\n\n\\1";
        return $this->_update_file($path, $find, $replace);
    }
}

WP_CLI::add_command( 'publish', 'Publish_Command' );